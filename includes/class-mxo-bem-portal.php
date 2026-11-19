<?php
/**
 * Das Frontend-Portal, in dem Externe ihre Bemusterungen selbst verwalten.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode und REST-Routen des Portals.
 */
class MXO_Bem_Portal {

	const REST_NS = 'mxo-bemusterung/v1';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'mxo_bemusterung_portal', array( __CLASS__, 'render' ) );
		// Alter Name, damit bestehende Seiten weiterlaufen.
		add_shortcode( 'mxo_bemusterung_upload', array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Liest das Token aus der Anfrage und prüft es.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return string|WP_Error Zugangs-ID.
	 */
	public static function authenticate( WP_REST_Request $request ) {
		return MXO_Bem_Access::verify( (string) $request->get_param( 'k' ) );
	}

	/**
	 * Zugangsprüfung für alle Portal-Routen.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return true|WP_Error
	 */
	public static function check_permission( WP_REST_Request $request ) {
		$access = self::authenticate( $request );
		return is_wp_error( $access ) ? $access : true;
	}

	/**
	 * Registriert die Routen des Portals.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$args = array(
			'k' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			self::REST_NS,
			'/promos',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_promos' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => $args,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_promo' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => $args,
				),
			)
		);

		register_rest_route(
			self::REST_NS,
			'/promos/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_promo' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::REST_NS,
			'/promos/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'set_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			self::REST_NS,
			'/promos/(?P<id>\d+)/tracks/(?P<track>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_track' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * Stellt eine Bemusterung als Datensatz für das Portal zusammen.
	 *
	 * @param int $promo_id ID der Bemusterung.
	 * @return array<string, mixed>
	 */
	protected static function shape( $promo_id ) {
		$tracks = array();

		foreach ( MXO_Bem_CPT::get_tracks( $promo_id ) as $track ) {
			$preview = (int) get_post_meta( $track->ID, 'mxo_bem_preview_id', true );
			$master  = (int) get_post_meta( $track->ID, 'mxo_bem_master_id', true );

			$tracks[] = array(
				'id'         => $track->ID,
				'title'      => $track->post_title,
				'preview'    => $preview ? wp_get_attachment_url( $preview ) : '',
				'hasMaster'  => (bool) $master,
				'masterName' => $master ? basename( (string) get_attached_file( $master ) ) : '',
				'download'   => $master ? MXO_Bem_Download::url( $track->ID ) : '',
				'downloads'  => (int) get_post_meta( $track->ID, 'mxo_bem_downloads', true ),
			);
		}

		$cover_id = (int) get_post_meta( $promo_id, 'mxo_bem_cover_id', true );

		return array(
			'id'          => $promo_id,
			'title'       => get_the_title( $promo_id ),
			'status'      => get_post_status( $promo_id ),
			'artist'      => (string) get_post_meta( $promo_id, 'mxo_bem_artist', true ),
			'label'       => (string) get_post_meta( $promo_id, 'mxo_bem_label', true ),
			'releaseDate' => (string) get_post_meta( $promo_id, 'mxo_bem_release_date', true ),
			'contact'     => (string) get_post_meta( $promo_id, 'mxo_bem_contact', true ),
			'text'        => (string) get_post_field( 'post_content', $promo_id ),
			'cover'       => $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '',
			'link'        => 'publish' === get_post_status( $promo_id ) ? get_permalink( $promo_id ) : '',
			'tracks'      => $tracks,
		);
	}

	/**
	 * Liefert alle Bemusterungen des Zugangs.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response
	 */
	public static function list_promos( WP_REST_Request $request ) {
		$access = self::authenticate( $request );
		$out    = array();

		foreach ( MXO_Bem_Access::promos( $access ) as $promo ) {
			$out[] = self::shape( $promo->ID );
		}

		return new WP_REST_Response( array( 'promos' => $out ), 200 );
	}

	/**
	 * Liefert eine einzelne Bemusterung.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_promo( WP_REST_Request $request ) {
		$access   = self::authenticate( $request );
		$promo_id = absint( $request->get_param( 'id' ) );

		if ( ! MXO_Bem_Access::owns( $access, $promo_id ) ) {
			return new WP_Error( 'mxo_bem_forbidden', __( 'Diese Bemusterung gehört nicht zu deinem Zugang.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( self::shape( $promo_id ), 200 );
	}

	/**
	 * Legt eine Bemusterung an oder speichert ihre Felder.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_promo( WP_REST_Request $request ) {
		$access   = self::authenticate( $request );
		$promo_id = absint( $request->get_param( 'id' ) );

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( '' === $title ) {
			return new WP_Error( 'mxo_bem_no_title', __( 'Bitte einen Titel eintragen.', 'mxo-bemusterung' ), array( 'status' => 400 ) );
		}

		$data = array(
			'post_type'    => MXO_Bem_CPT::PROMO,
			'post_title'   => $title,
			'post_content' => wp_kses_post( (string) $request->get_param( 'text' ) ),
		);

		if ( $promo_id ) {
			if ( ! MXO_Bem_Access::owns( $access, $promo_id ) ) {
				return new WP_Error( 'mxo_bem_forbidden', __( 'Diese Bemusterung gehört nicht zu deinem Zugang.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
			}
			$data['ID'] = $promo_id;
		} else {
			$data['post_status'] = 'draft';
		}

		$result = wp_insert_post( $data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$promo_id = (int) $result;
		update_post_meta( $promo_id, MXO_Bem_Access::META_OWNER, $access );

		$fields = array(
			'mxo_bem_artist'       => 'artist',
			'mxo_bem_label'        => 'label',
			'mxo_bem_release_date' => 'releaseDate',
			'mxo_bem_contact'      => 'contact',
		);

		foreach ( $fields as $meta_key => $param ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $promo_id, $meta_key, sanitize_text_field( (string) $value ) );
			}
		}

		return new WP_REST_Response( self::shape( $promo_id ), 200 );
	}

	/**
	 * Veröffentlicht eine Bemusterung oder nimmt sie zurück.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_status( WP_REST_Request $request ) {
		$access   = self::authenticate( $request );
		$promo_id = absint( $request->get_param( 'id' ) );

		if ( ! MXO_Bem_Access::owns( $access, $promo_id ) ) {
			return new WP_Error( 'mxo_bem_forbidden', __( 'Diese Bemusterung gehört nicht zu deinem Zugang.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		$wanted = sanitize_key( (string) $request->get_param( 'status' ) );
		$status = in_array( $wanted, array( 'publish', 'draft' ), true ) ? $wanted : 'draft';

		wp_update_post(
			array(
				'ID'          => $promo_id,
				'post_status' => $status,
			)
		);

		return new WP_REST_Response( self::shape( $promo_id ), 200 );
	}

	/**
	 * Entfernt einen Track samt seiner Dateien.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_track( WP_REST_Request $request ) {
		$access   = self::authenticate( $request );
		$promo_id = absint( $request->get_param( 'id' ) );
		$track_id = absint( $request->get_param( 'track' ) );

		if ( ! MXO_Bem_Access::owns( $access, $promo_id ) ) {
			return new WP_Error( 'mxo_bem_forbidden', __( 'Diese Bemusterung gehört nicht zu deinem Zugang.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		if ( (int) wp_get_post_parent_id( $track_id ) !== $promo_id ) {
			return new WP_Error( 'mxo_bem_bad_track', __( 'Dieser Titel gehört nicht zu dieser Bemusterung.', 'mxo-bemusterung' ), array( 'status' => 400 ) );
		}

		foreach ( array( 'mxo_bem_preview_id', 'mxo_bem_master_id' ) as $meta_key ) {
			$attachment_id = (int) get_post_meta( $track_id, $meta_key, true );
			if ( $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		wp_delete_post( $track_id, true );

		return new WP_REST_Response( self::shape( $promo_id ), 200 );
	}

	/**
	 * Gibt das Portal aus.
	 *
	 * @return string
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Das Token in der URL ist hier das Authentifizierungsmerkmal.
		$token = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '';

		$access = MXO_Bem_Access::verify( $token );

		if ( is_wp_error( $access ) ) {
			return '<div class="mxo-bem mxo-bem--error"><p>'
				. esc_html( $access->get_error_message() )
				. '</p></div>';
		}

		$all   = MXO_Bem_Access::all();
		$label = isset( $all[ $access ]['label'] ) ? $all[ $access ]['label'] : '';

		wp_enqueue_style( 'mxo-bem-portal', MXO_BEM_URL . 'assets/css/portal.css', array(), MXO_BEM_VERSION );
		wp_enqueue_script( 'mxo-bem-portal', MXO_BEM_URL . 'assets/js/portal.js', array(), MXO_BEM_VERSION, true );
		wp_add_inline_script(
			'mxo-bem-portal',
			'window.mxoBem = ' . wp_json_encode(
				array(
					'root'     => rest_url( self::REST_NS ),
					'token'    => $token,
					'maxBytes' => MXO_Bem_Upload::max_bytes(),
					'label'    => $label,
				)
			) . ';',
			'before'
		);

		return '<div class="mxo-bem" id="mxo-bem-app"><p class="mxo-bem__loading">'
			. esc_html__( 'Wird geladen …', 'mxo-bemusterung' )
			. '</p></div>';
	}
}
