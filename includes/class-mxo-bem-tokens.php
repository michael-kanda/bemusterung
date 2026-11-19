<?php
/**
 * Upload-Token: erlaubt Externen das Hochladen ohne WordPress-Konto.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Erzeugt und prüft die ablaufenden Upload-Links.
 *
 * Gespeichert wird nur der HMAC des Tokens, nie der Klartext. Der Klartext
 * existiert genau einmal, nämlich in dem Moment, in dem der Link erzeugt und
 * angezeigt wird.
 */
class MXO_Bem_Tokens {

	const META_HASH    = 'mxo_bem_token_hash';
	const META_EXPIRES = 'mxo_bem_token_expires';
	const META_USES    = 'mxo_bem_token_uses';

	/**
	 * Erzeugt ein neues Token für eine Bemusterung und verwirft das alte.
	 *
	 * @param int      $promo_id ID der Bemusterung.
	 * @param int|null $days     Gültigkeit in Tagen, null nimmt die Einstellung.
	 * @return string Der Klartext des Tokens.
	 */
	public static function create( $promo_id, $days = null ) {
		$promo_id = absint( $promo_id );
		$days     = null === $days ? (int) MXO_Bem_Settings::get( 'token_days' ) : (int) $days;
		$days     = max( 1, $days );
		$raw      = wp_generate_password( 32, false, false );

		update_post_meta( $promo_id, self::META_HASH, wp_hash( $raw ) );
		update_post_meta( $promo_id, self::META_EXPIRES, time() + ( $days * DAY_IN_SECONDS ) );
		update_post_meta( $promo_id, self::META_USES, 0 );

		return $raw;
	}

	/**
	 * Zieht das Token zurück.
	 *
	 * @param int $promo_id ID der Bemusterung.
	 * @return void
	 */
	public static function revoke( $promo_id ) {
		$promo_id = absint( $promo_id );
		delete_post_meta( $promo_id, self::META_HASH );
		delete_post_meta( $promo_id, self::META_EXPIRES );
	}

	/**
	 * Prüft ein Token und liefert die zugehörige Bemusterung.
	 *
	 * @param string $raw      Klartext des Tokens aus der Anfrage.
	 * @param int    $promo_id ID der Bemusterung.
	 * @return int|WP_Error Die geprüfte Promo-ID oder ein Fehler.
	 */
	public static function verify( $raw, $promo_id ) {
		$promo_id = absint( $promo_id );
		$raw      = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $raw || ! $promo_id ) {
			return new WP_Error( 'mxo_bem_no_token', __( 'Dieser Upload-Link ist unvollständig.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		if ( get_post_type( $promo_id ) !== MXO_Bem_CPT::PROMO ) {
			return new WP_Error( 'mxo_bem_no_promo', __( 'Dieser Upload-Link gehört zu keiner Bemusterung.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		$hash = get_post_meta( $promo_id, self::META_HASH, true );
		if ( ! $hash || ! hash_equals( (string) $hash, wp_hash( $raw ) ) ) {
			return new WP_Error( 'mxo_bem_bad_token', __( 'Dieser Upload-Link ist ungültig.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		$expires = (int) get_post_meta( $promo_id, self::META_EXPIRES, true );
		if ( $expires && $expires < time() ) {
			return new WP_Error( 'mxo_bem_expired', __( 'Dieser Upload-Link ist abgelaufen. Bitte fordere einen neuen an.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		return $promo_id;
	}

	/**
	 * Einfache Drosselung pro Token, damit ein geleakter Link nicht als
	 * Dateiablage missbraucht werden kann.
	 *
	 * @param string $raw Klartext des Tokens.
	 * @return bool True, wenn der Upload erlaubt ist.
	 */
	public static function check_rate_limit( $raw ) {
		$key   = 'mxo_bem_rl_' . md5( (string) $raw );
		$count = (int) get_transient( $key );

		/**
		 * Erlaubte Uploads pro Stunde und Token.
		 *
		 * @param int $limit Standardwert.
		 */
		$limit = (int) apply_filters( 'mxo_bem_upload_rate_limit', 40 );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Baut die Upload-URL zu einem Token.
	 *
	 * @param int    $promo_id ID der Bemusterung.
	 * @param string $raw      Klartext des Tokens.
	 * @return string
	 */
	public static function build_url( $promo_id, $raw ) {
		$base = MXO_Bem_Settings::upload_page_url();

		if ( '' === $base ) {
			return '';
		}

		return add_query_arg(
			array(
				'bem' => absint( $promo_id ),
				't'   => rawurlencode( $raw ),
			),
			$base
		);
	}
}
