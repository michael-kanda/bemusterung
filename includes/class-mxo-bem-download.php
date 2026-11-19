<?php
/**
 * Auslieferung der Masterdateien samt Downloadzähler.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liefert die WAV-Datei aus, ohne ihren echten Pfad preiszugeben.
 *
 * Es gibt zwei Wege zur selben Datei: eine sprechende Adresse der Form
 * /bemusterung/<release>/download/<titel>/ für den Versand an Redaktionen,
 * und eine Abfragevariable als Rückfallweg für Installationen ohne
 * sprechende Permalinks.
 */
class MXO_Bem_Download {

	const QUERY_VAR   = 'mxo_bem_dl';
	const SLUG_VAR    = 'mxo_bem_dl_slug';
	const RULE_OPTION = 'mxo_bem_rewrite_version';
	const RULE_VERSION = '2';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
	}

	/**
	 * Meldet die Abfragevariablen an.
	 *
	 * @param string[] $vars Bekannte Abfragevariablen.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::SLUG_VAR;
		return $vars;
	}

	/**
	 * Liefert den Permalink-Anteil des Inhaltstyps.
	 *
	 * @return string
	 */
	protected static function base_slug() {
		$object = get_post_type_object( MXO_Bem_CPT::PROMO );

		if ( $object && ! empty( $object->rewrite['slug'] ) ) {
			return (string) $object->rewrite['slug'];
		}

		return 'bemusterung';
	}

	/**
	 * Registriert die sprechende Download-Adresse.
	 *
	 * @return void
	 */
	public static function add_rules() {
		$base = self::base_slug();

		add_rewrite_rule(
			'^' . $base . '/([^/]+)/download/([^/]+)/?$',
			'index.php?' . MXO_Bem_CPT::PROMO . '=$matches[1]&' . self::SLUG_VAR . '=$matches[2]',
			'top'
		);
	}

	/**
	 * Erneuert die Schreibregeln einmalig nach einem Update.
	 *
	 * Ohne das müsste nach jedem Update von Hand unter Permalinks gespeichert
	 * werden, damit die neue Adresse greift.
	 *
	 * @return void
	 */
	public static function maybe_flush() {
		if ( get_option( self::RULE_OPTION ) === self::RULE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::RULE_OPTION, self::RULE_VERSION, false );
	}

	/**
	 * Liefert die Download-URL eines Titels.
	 *
	 * @param int $track_id ID des Titels.
	 * @return string
	 */
	public static function url( $track_id ) {
		$track_id = absint( $track_id );
		$promo_id = (int) wp_get_post_parent_id( $track_id );

		if ( ! $promo_id ) {
			return '';
		}

		$permalink = (string) get_permalink( $promo_id );

		// Ohne sprechende Permalinks enthält der Permalink bereits ein ?,
		// dann ist die Abfragevariable der einzige verlässliche Weg.
		if ( get_option( 'permalink_structure' ) && false === strpos( $permalink, '?' ) ) {
			$track = get_post( $track_id );

			if ( $track && $track->post_name ) {
				return trailingslashit( $permalink ) . 'download/' . rawurlencode( $track->post_name ) . '/';
			}
		}

		return add_query_arg( self::QUERY_VAR, $track_id, $permalink );
	}

	/**
	 * Liefert die Adresse der Vorhörversion.
	 *
	 * Die MP3 liegt bewusst öffentlich, damit sie sich ohne Umweg abspielen
	 * und in eine Mail einbetten lässt.
	 *
	 * @param int $track_id ID des Titels.
	 * @return string
	 */
	public static function preview_url( $track_id ) {
		$attachment_id = (int) get_post_meta( absint( $track_id ), 'mxo_bem_preview_id', true );

		return $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '';
	}

	/**
	 * Ermittelt den angefragten Titel aus der Anfrage.
	 *
	 * @return int Track-ID oder 0.
	 */
	protected static function requested_track() {
		$direct = absint( get_query_var( self::QUERY_VAR ) );

		if ( $direct ) {
			return $direct;
		}

		$slug = sanitize_title( (string) get_query_var( self::SLUG_VAR ) );

		if ( '' === $slug ) {
			return 0;
		}

		$promo_id = (int) get_queried_object_id();

		if ( ! $promo_id ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'      => MXO_Bem_CPT::TRACK,
				'post_parent'    => $promo_id,
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return empty( $found ) ? 0 : (int) $found[0];
	}

	/**
	 * Prüft die Anfrage und liefert gegebenenfalls die Datei aus.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		$track_id = self::requested_track();

		if ( ! $track_id || get_post_type( $track_id ) !== MXO_Bem_CPT::TRACK ) {
			return;
		}

		$promo_id = (int) wp_get_post_parent_id( $track_id );

		if ( 'publish' !== get_post_status( $promo_id ) ) {
			return;
		}

		$attachment_id = (int) get_post_meta( $track_id, 'mxo_bem_master_id', true );

		if ( ! $attachment_id ) {
			return;
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			wp_die(
				esc_html__( 'Die Datei ist nicht mehr verfügbar.', 'mxo-bemusterung' ),
				'',
				array( 'response' => 404 )
			);
		}

		self::count( $track_id );
		self::serve( $path, $attachment_id );
	}

	/**
	 * Zählt den Download hoch.
	 *
	 * Direkt als UPDATE, damit zwei gleichzeitige Downloads nicht einer werden.
	 * Es wird nur die Summe gespeichert, keine IP und kein Zeitstempel.
	 *
	 * @param int $track_id ID des Titels.
	 * @return void
	 */
	protected static function count( $track_id ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$track_id,
				'mxo_bem_downloads'
			)
		);

		if ( ! $updated ) {
			add_post_meta( $track_id, 'mxo_bem_downloads', 1, true );
		}

		wp_cache_delete( $track_id, 'post_meta' );
	}

	/**
	 * Schickt die Datei an den Browser.
	 *
	 * @param string $path          Absoluter Pfad der Datei.
	 * @param int    $attachment_id ID des Anhangs.
	 * @return void
	 */
	protected static function serve( $path, $attachment_id ) {
		$filename = basename( $path );
		$mime     = get_post_mime_type( $attachment_id );
		$mime     = $mime ? $mime : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$method = (string) MXO_Bem_Settings::get( 'delivery' );

		if ( 'x-accel' === $method ) {
			$uploads  = wp_upload_dir();
			$relative = str_replace( $uploads['basedir'], '', $path );
			header( 'X-Accel-Redirect: /wp-content/uploads' . $relative );
			exit;
		}

		if ( 'x-sendfile' === $method ) {
			header( 'X-Sendfile: ' . $path );
			exit;
		}

		header( 'Content-Length: ' . (string) filesize( $path ) );

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Gezieltes Streaming einer großen Datei, WP_Filesystem würde sie komplett in den Speicher laden.
		readfile( $path );
		exit;
	}
}
