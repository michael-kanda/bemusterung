<?php
/**
 * Post Types, Meta-Felder und Indexierungsschutz.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert die Inhaltstypen der Bemusterung.
 */
class MXO_Bem_CPT {

	const PROMO    = 'mxo_bem_promo';
	const TRACK    = 'mxo_bem_track';
	const FEEDBACK = 'mxo_bem_fb';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		// Die Promoseite soll auffindbar sein, wenn man den Link hat, sonst nirgends.
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'filter_sitemap_query' ), 10, 2 );
	}

	/**
	 * Registriert Promoseite, Track und Feedback.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			self::PROMO,
			array(
				'labels'             => array(
					'name'          => __( 'Bemusterungen', 'mxo-bemusterung' ),
					'singular_name' => __( 'Bemusterung', 'mxo-bemusterung' ),
					'add_new_item'  => __( 'Neue Bemusterung', 'mxo-bemusterung' ),
					'edit_item'     => __( 'Bemusterung bearbeiten', 'mxo-bemusterung' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'exclude_from_search' => true,
				'menu_icon'          => 'dashicons-format-audio',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
				'rewrite'            => array(
					'slug'       => 'bemusterung',
					'with_front' => false,
				),
			)
		);

		register_post_type(
			self::TRACK,
			array(
				'labels'              => array(
					'name'          => __( 'Tracks', 'mxo-bemusterung' ),
					'singular_name' => __( 'Track', 'mxo-bemusterung' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::PROMO,
				'show_in_rest'        => true,
				'hierarchical'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'page-attributes', 'custom-fields' ),
			)
		);

		register_post_type(
			self::FEEDBACK,
			array(
				'labels'              => array(
					'name'          => __( 'Rückmeldungen', 'mxo-bemusterung' ),
					'singular_name' => __( 'Rückmeldung', 'mxo-bemusterung' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::PROMO,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Liefert die Meta-Felder der Promoseite.
	 *
	 * Bewusst ohne führenden Unterstrich, damit GenerateBlocks Pro
	 * die Werte per Dynamic Data lesen kann.
	 *
	 * @return array<string, string> Meta-Key => Sanitize-Callback.
	 */
	public static function promo_fields() {
		return array(
			'mxo_bem_artist'       => 'sanitize_text_field',
			'mxo_bem_label'        => 'sanitize_text_field',
			'mxo_bem_release_date' => 'sanitize_text_field',
			'mxo_bem_contact'      => 'sanitize_text_field',
			'mxo_bem_cover_id'     => 'absint',
			'mxo_bem_presskit_id'  => 'absint',
		);
	}

	/**
	 * Liefert die Meta-Felder eines Tracks.
	 *
	 * @return array<string, string> Meta-Key => Sanitize-Callback.
	 */
	public static function track_fields() {
		return array(
			'mxo_bem_isrc'       => 'sanitize_text_field',
			'mxo_bem_preview_id' => 'absint',
			'mxo_bem_master_id'  => 'absint',
			'mxo_bem_downloads'  => 'absint',
		);
	}

	/**
	 * Meldet alle Meta-Felder bei WordPress an.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( self::promo_fields() as $key => $sanitize ) {
			register_post_meta(
				self::PROMO,
				$key,
				array(
					'single'            => true,
					'type'              => 'absint' === $sanitize ? 'integer' : 'string',
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => array( __CLASS__, 'can_edit_promo' ),
				)
			);
		}

		foreach ( self::track_fields() as $key => $sanitize ) {
			register_post_meta(
				self::TRACK,
				$key,
				array(
					'single'            => true,
					'type'              => 'absint' === $sanitize ? 'integer' : 'string',
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => array( __CLASS__, 'can_edit_promo' ),
				)
			);
		}
	}

	/**
	 * Berechtigungsprüfung für das Schreiben von Meta-Werten.
	 *
	 * @param bool   $allowed Bisheriger Wert.
	 * @param string $meta_key Meta-Key.
	 * @param int    $post_id Beitrags-ID.
	 * @return bool
	 */
	public static function can_edit_promo( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Setzt die Promoseite auf noindex.
	 *
	 * @param array<string, mixed> $robots Robots-Direktiven.
	 * @return array<string, mixed>
	 */
	public static function filter_robots( $robots ) {
		if ( is_singular( self::PROMO ) ) {
			return wp_robots_no_robots( $robots );
		}

		if ( self::is_upload_page() ) {
			return wp_robots_no_robots( $robots );
		}

		return $robots;
	}

	/**
	 * Prüft, ob gerade die Upload-Seite ausgeliefert wird und ob sie
	 * laut Einstellungen versteckt werden soll.
	 *
	 * @return bool
	 */
	public static function is_upload_page() {
		if ( ! class_exists( 'MXO_Bem_Settings' ) ) {
			return false;
		}

		if ( ! MXO_Bem_Settings::get( 'noindex_upload' ) ) {
			return false;
		}

		$page_id = MXO_Bem_Settings::upload_page_id();

		return $page_id && is_singular() && get_queried_object_id() === $page_id;
	}

	/**
	 * Nimmt die Upload-Seite aus der XML-Sitemap.
	 *
	 * @param array<string, mixed> $args      Query-Argumente der Sitemap.
	 * @param string               $post_type Betroffener Post Type.
	 * @return array<string, mixed>
	 */
	public static function filter_sitemap_query( $args, $post_type ) {
		if ( ! class_exists( 'MXO_Bem_Settings' ) || ! MXO_Bem_Settings::get( 'noindex_upload' ) ) {
			return $args;
		}

		$page_id = MXO_Bem_Settings::upload_page_id();
		if ( ! $page_id || get_post_type( $page_id ) !== $post_type ) {
			return $args;
		}

		$excluded              = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
		$excluded[]            = $page_id;
		$args['post__not_in']  = array_map( 'absint', $excluded );

		return $args;
	}

	/**
	 * Hält die Promoseiten aus der XML-Sitemap heraus.
	 *
	 * @param array<string, mixed> $post_types Post Types der Sitemap.
	 * @return array<string, mixed>
	 */
	public static function filter_sitemap_post_types( $post_types ) {
		unset( $post_types[ self::PROMO ] );
		return $post_types;
	}

	/**
	 * Liefert die Tracks einer Bemusterung in Sortierreihenfolge.
	 *
	 * @param int $promo_id ID der Bemusterung.
	 * @return WP_Post[]
	 */
	public static function get_tracks( $promo_id ) {
		return get_posts(
			array(
				'post_type'      => self::TRACK,
				'post_parent'    => absint( $promo_id ),
				'post_status'    => 'publish',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 100,
			)
		);
	}
}
