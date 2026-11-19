<?php
/**
 * Hält Bemusterungen privat: nur erreichbar, wer den Link hat.
 *
 * Der Schutz greift unabhängig davon, welches SEO-Plugin läuft:
 * - X-Robots-Tag als HTTP-Header (wirkt auch, wenn ein SEO-Plugin das
 *   robots-Meta von WordPress überschreibt),
 * - robots-Meta und Sitemap-Ausschluss für AIOSEO, Yoast und Rank Math,
 * - kein Eintrag in der llms.txt von AIOSEO,
 * - keine Auflistung über ?post_type=, Feeds oder Anhangseiten,
 * - kein Aufruf über die Beitrags-ID (?p=123), sonst wären die Seiten
 *   durch Hochzählen auffindbar,
 * - keine Auflistung über die WordPress-REST-API (Beiträge, Tracks,
 *   Mediathek, Suche). Wer Beiträge bearbeiten darf, sieht weiterhin alles.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schirmt die Bemusterungen gegen Auffinden ab.
 */
class MXO_Bem_Privacy {

	/**
	 * Zwischenspeicher der geschützten Beitrags-IDs pro Anfrage.
	 *
	 * @var int[]|null
	 */
	protected static $protected_ids = null;

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		// Frontend: Auflistungen sperren, Header setzen. Vor redirect_canonical (10).
		add_action( 'template_redirect', array( __CLASS__, 'guard_frontend' ), 1 );

		// robots-Meta der SEO-Plugins.
		add_filter( 'aioseo_robots_meta', array( __CLASS__, 'aioseo_robots' ) );
		add_filter( 'wpseo_robots_array', array( __CLASS__, 'index_follow_robots' ) );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'index_follow_robots' ) );

		// Sitemaps der SEO-Plugins.
		add_filter( 'aioseo_sitemap_exclude_post_types', array( __CLASS__, 'aioseo_exclude_post_types' ) );
		add_filter( 'aioseo_sitemap_exclude_posts', array( __CLASS__, 'exclude_upload_page_ids' ) );
		add_filter( 'wpseo_sitemap_exclude_post_type', array( __CLASS__, 'exclude_post_type' ), 10, 2 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude_upload_page_ids' ) );
		add_filter( 'rank_math/sitemap/exclude_post_type', array( __CLASS__, 'exclude_post_type' ), 10, 2 );

		// llms.txt von AIOSEO: ein leerer Titel nimmt den Eintrag heraus.
		add_filter( 'aioseo_llms_post_title', array( __CLASS__, 'aioseo_llms_title' ), 10, 2 );

		// REST-API.
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_rest' ), 10, 3 );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'rest_attachment_query' ) );
		add_filter( 'rest_post_search_query', array( __CLASS__, 'rest_search_query' ) );
	}

	/**
	 * Darf der aktuelle Besucher alles sehen?
	 *
	 * @return bool
	 */
	protected static function is_editor() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Ist dies eine Seite, die nicht in Suchmaschinen gehört?
	 *
	 * @return bool
	 */
	protected static function is_private_view() {
		return is_singular( MXO_Bem_CPT::PROMO ) || MXO_Bem_CPT::is_upload_page();
	}

	/**
	 * Liefert die Upload-Seite, sofern sie versteckt werden soll.
	 *
	 * @return int 0, wenn nicht.
	 */
	protected static function hidden_upload_page_id() {
		if ( ! MXO_Bem_Settings::get( 'noindex_upload' ) ) {
			return 0;
		}

		return (int) MXO_Bem_Settings::upload_page_id();
	}

	/**
	 * Sperrt Auflistungen und den Zugriff per ID, setzt den Robots-Header.
	 *
	 * @return void
	 */
	public static function guard_frontend() {
		if ( is_admin() ) {
			return;
		}

		$post_types = array_filter( (array) get_query_var( 'post_type' ) );

		// Auflistung aller Bemusterungen über ?post_type=, Suche oder Feed.
		if ( ! is_singular() && in_array( MXO_Bem_CPT::PROMO, $post_types, true ) ) {
			self::force_404();
			return;
		}

		// Anhangseiten von Covern, Previews und Masters.
		if ( is_attachment() && ! self::is_editor() ) {
			$parent = (int) wp_get_post_parent_id( get_queried_object_id() );
			if ( $parent && in_array( $parent, self::protected_ids(), true ) ) {
				self::force_404();
				return;
			}
		}

		if ( is_singular( MXO_Bem_CPT::PROMO ) ) {
			// ?p=123 würde sonst auf die Promoseite weiterleiten und die IDs sind fortlaufend.
			$by_id = (int) get_query_var( 'p' );
			if ( $by_id && ! current_user_can( 'edit_post', $by_id ) ) {
				self::force_404();
				return;
			}
		}

		if ( self::is_private_view() && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true );
		}
	}

	/**
	 * Schaltet die laufende Anfrage auf 404.
	 *
	 * @return void
	 */
	protected static function force_404() {
		global $wp_query;

		$wp_query->posts             = array();
		$wp_query->post              = null;
		$wp_query->post_count        = 0;
		$wp_query->found_posts       = 0;
		$wp_query->queried_object    = null;
		$wp_query->queried_object_id = 0;
		$wp_query->set_404();

		// Sonst landet der Titel der Bemusterung über SEO-Plugins im <title> der 404-Seite.
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * AIOSEO: noindex, nofollow auf Promoseite und versteckter Upload-Seite.
	 *
	 * @param array<string, string> $attributes Robots-Attribute.
	 * @return array<string, string>
	 */
	public static function aioseo_robots( $attributes ) {
		if ( ! self::is_private_view() ) {
			return $attributes;
		}

		$attributes             = is_array( $attributes ) ? $attributes : array();
		$attributes['noindex']  = 'noindex';
		$attributes['nofollow'] = 'nofollow';
		$attributes['noarchive'] = 'noarchive';
		unset( $attributes['index'], $attributes['follow'] );

		return $attributes;
	}

	/**
	 * Yoast und Rank Math: beide verwenden die Schlüssel index und follow.
	 *
	 * @param array<string, string> $robots Robots-Werte.
	 * @return array<string, string>
	 */
	public static function index_follow_robots( $robots ) {
		if ( ! self::is_private_view() ) {
			return $robots;
		}

		$robots           = is_array( $robots ) ? $robots : array();
		$robots['index']  = 'noindex';
		$robots['follow'] = 'nofollow';

		return $robots;
	}

	/**
	 * AIOSEO: Bemusterungen aus allen Sitemaps nehmen.
	 *
	 * @param string[] $post_types Ausgeschlossene Post Types.
	 * @return string[]
	 */
	public static function aioseo_exclude_post_types( $post_types ) {
		$post_types   = is_array( $post_types ) ? $post_types : array();
		$post_types[] = MXO_Bem_CPT::PROMO;
		$post_types[] = MXO_Bem_CPT::TRACK;

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Yoast und Rank Math: Post Type ausschließen.
	 *
	 * @param bool   $exclude   Bisheriger Wert.
	 * @param string $post_type Post Type.
	 * @return bool
	 */
	public static function exclude_post_type( $exclude, $post_type ) {
		if ( in_array( $post_type, array( MXO_Bem_CPT::PROMO, MXO_Bem_CPT::TRACK ), true ) ) {
			return true;
		}

		return $exclude;
	}

	/**
	 * Versteckte Upload-Seite aus den Sitemaps nehmen.
	 *
	 * @param int[] $ids Ausgeschlossene IDs.
	 * @return int[]
	 */
	public static function exclude_upload_page_ids( $ids ) {
		$ids     = is_array( $ids ) ? $ids : array();
		$page_id = self::hidden_upload_page_id();

		if ( $page_id ) {
			$ids[] = $page_id;
		}

		return $ids;
	}

	/**
	 * AIOSEO llms.txt: Bemusterungen und versteckte Upload-Seite weglassen.
	 *
	 * @param string       $title Titel im llms.txt.
	 * @param WP_Post|null $post  Beitrag.
	 * @return string
	 */
	public static function aioseo_llms_title( $title, $post = null ) {
		if ( ! $post instanceof WP_Post ) {
			return $title;
		}

		if ( in_array( $post->post_type, array( MXO_Bem_CPT::PROMO, MXO_Bem_CPT::TRACK ), true ) ) {
			return '';
		}

		$page_id = self::hidden_upload_page_id();
		if ( $page_id && (int) $post->ID === $page_id ) {
			return '';
		}

		return $title;
	}

	/**
	 * Sperrt die Standard-Routen von Bemusterungen, Tracks, Rückmeldungen
	 * und ihren Medien für Besucher ohne Bearbeitungsrechte.
	 *
	 * Die eigenen Routen des Plugins (Portal, Upload, Feedback) bleiben offen,
	 * sie prüfen ihre Berechtigung selbst.
	 *
	 * @param mixed           $result  Bisheriges Ergebnis.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Anfrage.
	 * @return mixed
	 */
	public static function guard_rest( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || self::is_editor() ) {
			return $result;
		}

		$route = untrailingslashit( (string) $request->get_route() );

		$types = preg_quote( MXO_Bem_CPT::PROMO, '#' ) . '|' . preg_quote( MXO_Bem_CPT::TRACK, '#' ) . '|' . preg_quote( MXO_Bem_CPT::FEEDBACK, '#' );
		if ( preg_match( '#^/wp/v2/(' . $types . ')(/|$)#', $route ) ) {
			return self::rest_not_found();
		}

		if ( preg_match( '#^/wp/v2/media/(\d+)#', $route, $match ) ) {
			$parent = (int) wp_get_post_parent_id( (int) $match[1] );
			if ( $parent && in_array( $parent, self::protected_ids(), true ) ) {
				return self::rest_not_found();
			}
		}

		return $result;
	}

	/**
	 * Fehlerantwort, die nicht verrät, dass es etwas gäbe.
	 *
	 * @return WP_Error
	 */
	protected static function rest_not_found() {
		return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.' ), array( 'status' => 404 ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Bewusst der Kerntext.
	}

	/**
	 * Mediathek-Liste: Dateien von Bemusterungen ausblenden.
	 *
	 * @param array<string, mixed> $args Query-Argumente.
	 * @return array<string, mixed>
	 */
	public static function rest_attachment_query( $args ) {
		if ( self::is_editor() ) {
			return $args;
		}

		$ids = self::protected_ids();
		if ( $ids ) {
			$existing                    = isset( $args['post_parent__not_in'] ) ? (array) $args['post_parent__not_in'] : array();
			$args['post_parent__not_in'] = array_values( array_unique( array_merge( array_map( 'absint', $existing ), $ids ) ) );
		}

		return $args;
	}

	/**
	 * REST-Suche: Bemusterungen herausnehmen.
	 *
	 * @param array<string, mixed> $args Query-Argumente.
	 * @return array<string, mixed>
	 */
	public static function rest_search_query( $args ) {
		if ( self::is_editor() || empty( $args['post_type'] ) ) {
			return $args;
		}

		$post_types        = array_diff( (array) $args['post_type'], array( MXO_Bem_CPT::PROMO, MXO_Bem_CPT::TRACK ) );
		$args['post_type'] = $post_types ? array_values( $post_types ) : array( 'mxo_bem_none' );

		return $args;
	}

	/**
	 * IDs aller Bemusterungen und Tracks, deren Anhänge geschützt sind.
	 *
	 * @return int[]
	 */
	protected static function protected_ids() {
		if ( null === self::$protected_ids ) {
			self::$protected_ids = array_map(
				'intval',
				get_posts(
					array(
						'post_type'        => array( MXO_Bem_CPT::PROMO, MXO_Bem_CPT::TRACK ),
						'post_status'      => 'any',
						'posts_per_page'   => -1,
						'fields'           => 'ids',
						'no_found_rows'    => true,
						'suppress_filters' => true,
					)
				)
			);
		}

		return self::$protected_ids;
	}
}
