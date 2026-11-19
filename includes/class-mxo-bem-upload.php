<?php
/**
 * Dateiannahme für das Portal.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nimmt Dateien entgegen, authentifiziert über den Zugangslink.
 */
class MXO_Bem_Upload {

	const META_NEW = 'mxo_bem_pending_review';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Liefert die erlaubte Maximalgröße in Bytes.
	 *
	 * Begrenzt zusätzlich auf das, was der Server tatsächlich annimmt, damit die
	 * Meldung im Browser erscheint statt als abgebrochener Upload.
	 *
	 * @return int
	 */
	public static function max_bytes() {
		$wanted    = absint( MXO_Bem_Settings::get( 'max_mb' ) ) * MB_IN_BYTES;
		$php_limit = (int) wp_max_upload_size();

		if ( $php_limit > 0 ) {
			$wanted = min( $wanted, $php_limit );
		}

		return max( MB_IN_BYTES, $wanted );
	}

	/**
	 * Erlaubte Dateitypen.
	 *
	 * @return array<string, string> Erweiterungsmuster => MIME-Typ.
	 */
	public static function allowed_mimes() {
		return array(
			'mp3'      => 'audio/mpeg',
			'wav'      => 'audio/wav',
			'aif|aiff' => 'audio/aiff',
			'flac'     => 'audio/flac',
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'pdf'      => 'application/pdf',
		);
	}

	/**
	 * Registriert den Upload-Endpunkt.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			MXO_Bem_Portal::REST_NS,
			'/promos/(?P<id>\d+)/files',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_upload' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'k' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Zugangsprüfung: Token gültig, Bemusterung gehört dazu, Rate eingehalten.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return true|WP_Error
	 */
	public static function check_permission( WP_REST_Request $request ) {
		$token  = (string) $request->get_param( 'k' );
		$access = MXO_Bem_Access::verify( $token );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		if ( ! MXO_Bem_Access::owns( $access, absint( $request->get_param( 'id' ) ) ) ) {
			return new WP_Error(
				'mxo_bem_forbidden',
				__( 'Diese Bemusterung gehört nicht zu deinem Zugang.', 'mxo-bemusterung' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::check_rate_limit( $token ) ) {
			return new WP_Error(
				'mxo_bem_rate_limited',
				__( 'Zu viele Uploads in kurzer Zeit. Bitte später noch einmal versuchen.', 'mxo-bemusterung' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Drosselung pro Zugang, damit ein geleakter Link nicht als Dateiablage dient.
	 *
	 * @param string $token Klartext des Tokens.
	 * @return bool
	 */
	protected static function check_rate_limit( $token ) {
		$key   = 'mxo_bem_rl_' . md5( $token );
		$count = (int) get_transient( $key );
		$limit = 120;

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Nimmt eine Datei entgegen, prüft sie und ordnet sie zu.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_upload( WP_REST_Request $request ) {
		$promo_id = absint( $request->get_param( 'id' ) );
		$files    = $request->get_file_params();

		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'mxo_bem_no_file', __( 'Es kam keine Datei an.', 'mxo-bemusterung' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error(
				'mxo_bem_upload_error',
				__( 'Die Datei kam nicht vollständig an. Meist liegt das am Größenlimit des Servers.', 'mxo-bemusterung' ),
				array( 'status' => 400 )
			);
		}

		$max = self::max_bytes();
		if ( isset( $file['size'] ) && (int) $file['size'] > $max ) {
			return new WP_Error(
				'mxo_bem_too_large',
				sprintf(
					/* translators: %s: erlaubte Maximalgröße, z. B. 300 MB */
					__( 'Die Datei ist zu groß. Erlaubt sind bis zu %s.', 'mxo-bemusterung' ),
					size_format( $max )
				),
				array( 'status' => 413 )
			);
		}

		// Typprüfung serverseitig, unabhängig davon, was der Browser behauptet.
		$mimes   = self::allowed_mimes();
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );

		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error(
				'mxo_bem_bad_type',
				__( 'Dieser Dateityp ist hier nicht vorgesehen. Erlaubt sind Audio, Bilder und PDF.', 'mxo-bemusterung' ),
				array( 'status' => 415 )
			);
		}

		$file['name'] = sanitize_file_name( $checked['proper_filename'] ? $checked['proper_filename'] : $file['name'] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$is_master = in_array( $checked['type'], array( 'audio/wav', 'audio/aiff', 'audio/flac' ), true );

		// Master landen im abgeschirmten Ordner, Vorhör-MP3 und Cover bleiben erreichbar.
		$filter = null;
		if ( $is_master ) {
			$filter = MXO_Bem_Storage::redirect_uploads_to( $promo_id );
		}

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);

		if ( $filter ) {
			MXO_Bem_Storage::restore_uploads( $filter );
		}

		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'mxo_bem_move_failed', $moved['error'], array( 'status' => 500 ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $moved['type'],
				'post_title'     => sanitize_text_field( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
			),
			$moved['file'],
			$promo_id,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $moved['file'] )
		);

		self::assign( $promo_id, $attachment_id, $checked['type'], $file['name'] );

		update_post_meta( $promo_id, self::META_NEW, 1 );
		self::notify( $promo_id, $file['name'] );

		return new WP_REST_Response(
			array(
				'id'   => $attachment_id,
				'name' => $file['name'],
				'type' => $checked['type'],
			),
			201
		);
	}

	/**
	 * Ordnet eine hochgeladene Datei ihrem Platz in der Bemusterung zu.
	 *
	 * Audiodateien mit gleichem Dateinamen landen am selben Track, sodass
	 * "Tiroler Luft.mp3" und "Tiroler Luft.wav" automatisch zusammenfinden.
	 *
	 * @param int    $promo_id      ID der Bemusterung.
	 * @param int    $attachment_id ID des Anhangs.
	 * @param string $mime          Geprüfter MIME-Typ.
	 * @param string $filename      Bereinigter Dateiname.
	 * @return void
	 */
	protected static function assign( $promo_id, $attachment_id, $mime, $filename ) {
		if ( 'application/pdf' === $mime ) {
			update_post_meta( $promo_id, 'mxo_bem_presskit_id', $attachment_id );
			return;
		}

		if ( 0 === strpos( $mime, 'image/' ) ) {
			update_post_meta( $promo_id, 'mxo_bem_cover_id', $attachment_id );
			set_post_thumbnail( $promo_id, $attachment_id );
			return;
		}

		$base     = pathinfo( $filename, PATHINFO_FILENAME );
		$slug     = sanitize_title( $base );
		$track_id = self::find_or_create_track( $promo_id, $slug, $base );

		$meta_key = ( 'audio/mpeg' === $mime ) ? 'mxo_bem_preview_id' : 'mxo_bem_master_id';

		// Eine ersetzte Datei wird entfernt, damit kein Müll liegen bleibt.
		$previous = (int) get_post_meta( $track_id, $meta_key, true );
		if ( $previous && $previous !== $attachment_id ) {
			wp_delete_attachment( $previous, true );
		}

		update_post_meta( $track_id, $meta_key, $attachment_id );
	}

	/**
	 * Sucht einen Track anhand des Dateinamens oder legt ihn an.
	 *
	 * @param int    $promo_id ID der Bemusterung.
	 * @param string $slug     Normalisierter Dateiname.
	 * @param string $title    Anzeigetitel.
	 * @return int Track-ID.
	 */
	protected static function find_or_create_track( $promo_id, $slug, $title ) {
		$existing = get_posts(
			array(
				'post_type'      => MXO_Bem_CPT::TRACK,
				'post_parent'    => $promo_id,
				'name'           => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$count = count( MXO_Bem_CPT::get_tracks( $promo_id ) );

		return (int) wp_insert_post(
			array(
				'post_type'   => MXO_Bem_CPT::TRACK,
				'post_parent' => $promo_id,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
				'menu_order'  => $count + 1,
			)
		);
	}

	/**
	 * Meldet dem Verantwortlichen, dass etwas hochgeladen wurde.
	 *
	 * @param int    $promo_id ID der Bemusterung.
	 * @param string $filename Dateiname.
	 * @return void
	 */
	protected static function notify( $promo_id, $filename ) {
		$to = (string) MXO_Bem_Settings::get( 'notify_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		// Höchstens eine Mail pro Bemusterung und Stunde, sonst wird ein
		// Album-Upload zur Mailflut.
		$throttle = 'mxo_bem_mail_' . $promo_id;
		if ( get_transient( $throttle ) ) {
			return;
		}
		set_transient( $throttle, 1, HOUR_IN_SECONDS );

		$subject = sprintf(
			/* translators: %s: Titel der Bemusterung */
			__( 'Neue Dateien zur Bemusterung: %s', 'mxo-bemusterung' ),
			get_the_title( $promo_id )
		);

		$body = sprintf(
			/* translators: 1: Dateiname, 2: Bearbeitungslink */
			__( "Zuletzt hochgeladen: %1\$s\n\nBearbeiten: %2\$s", 'mxo-bemusterung' ),
			$filename,
			get_edit_post_link( $promo_id, 'raw' )
		);

		wp_mail( $to, $subject, $body );
	}
}
