<?php
/**
 * Zugänge: dauerhafte, widerrufbare Links für externe Verwalter.
 *
 * Ein Zugang gehört zu einer Person, nicht zu einer Bemusterung. Wer ihn hat,
 * verwaltet seine eigenen Bemusterungen im Frontend, ohne WordPress-Konto.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verwaltet die Zugangslinks.
 */
class MXO_Bem_Access {

	const OPTION    = 'mxo_bem_access';
	const META_OWNER = 'mxo_bem_access_id';

	/**
	 * Bereinigt eine Zugangs-ID, ohne die Schreibweise zu verändern.
	 *
	 * sanitize_key() darf hier nicht verwendet werden: es wandelt in
	 * Kleinbuchstaben um, die IDs aus wp_generate_password() enthalten aber
	 * Großbuchstaben, und der Vergleich schlägt dann fehl.
	 *
	 * @param string $id Rohe ID.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
	}

	/**
	 * Liefert alle Zugänge.
	 *
	 * @return array<string, array<string, mixed>> Zugangs-ID => Datensatz.
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Legt einen Zugang an und liefert den Klartext des Tokens.
	 *
	 * Gespeichert wird nur der HMAC. Der Klartext ist danach nicht mehr
	 * rekonstruierbar, deshalb wird er genau einmal angezeigt.
	 *
	 * @param string $label Name der Person oder Firma.
	 * @return array{id: string, token: string}
	 */
	public static function create( $label ) {
		$access   = self::all();
		$id       = wp_generate_password( 8, false, false );
		$token    = wp_generate_password( 40, false, false );

		$access[ $id ] = array(
			'label'   => sanitize_text_field( $label ),
			'hash'    => wp_hash( $token ),
			'created' => time(),
			'active'  => 1,
		);

		update_option( self::OPTION, $access, false );

		return array(
			'id'    => $id,
			'token' => $id . '.' . $token,
		);
	}

	/**
	 * Zieht einen Zugang zurück. Die angelegten Bemusterungen bleiben bestehen.
	 *
	 * @param string $id Zugangs-ID.
	 * @return void
	 */
	public static function revoke( $id ) {
		$access = self::all();
		$id     = self::sanitize_id( $id );

		if ( isset( $access[ $id ] ) ) {
			unset( $access[ $id ] );
			update_option( self::OPTION, $access, false );
		}
	}

	/**
	 * Prüft ein Token und liefert die Zugangs-ID.
	 *
	 * @param string $raw Klartext in der Form id.token.
	 * @return string|WP_Error
	 */
	public static function verify( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $raw || false === strpos( $raw, '.' ) ) {
			return new WP_Error( 'mxo_bem_no_access', __( 'Dieser Link ist unvollständig.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		list( $id, $secret ) = explode( '.', $raw, 2 );

		$id     = self::sanitize_id( $id );
		$access = self::all();

		if ( empty( $access[ $id ] ) || empty( $access[ $id ]['active'] ) ) {
			return new WP_Error( 'mxo_bem_bad_access', __( 'Dieser Link ist nicht mehr gültig.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( (string) $access[ $id ]['hash'], wp_hash( $secret ) ) ) {
			return new WP_Error( 'mxo_bem_bad_access', __( 'Dieser Link ist nicht mehr gültig.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		return $id;
	}

	/**
	 * Prüft, ob ein Zugang eine bestimmte Bemusterung verwalten darf.
	 *
	 * @param string $access_id Zugangs-ID.
	 * @param int    $promo_id  ID der Bemusterung.
	 * @return bool
	 */
	public static function owns( $access_id, $promo_id ) {
		if ( get_post_type( $promo_id ) !== MXO_Bem_CPT::PROMO ) {
			return false;
		}

		return get_post_meta( $promo_id, self::META_OWNER, true ) === $access_id;
	}

	/**
	 * Liefert die Bemusterungen eines Zugangs, neueste zuerst.
	 *
	 * @param string $access_id Zugangs-ID.
	 * @return WP_Post[]
	 */
	public static function promos( $access_id ) {
		return get_posts(
			array(
				'post_type'      => MXO_Bem_CPT::PROMO,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => self::META_OWNER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $access_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Baut die Portal-URL zu einem Token.
	 *
	 * @param string $token Klartext des Tokens.
	 * @return string Leer, wenn keine Portalseite feststeht.
	 */
	public static function portal_url( $token ) {
		$base = MXO_Bem_Settings::upload_page_url();

		if ( '' === $base ) {
			return '';
		}

		return add_query_arg( 'k', $token, $base );
	}
}
