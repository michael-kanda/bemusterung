<?php
/**
 * Freiwillige Rückmeldung der Sender. Der Download bleibt davon unberührt.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nimmt freiwillige Rückmeldungen entgegen.
 *
 * Bewusst keine Bedingung für den Download: das Formular fragt, es sperrt nicht.
 */
class MXO_Bem_Feedback {

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'mxo_bemusterung_feedback', array( __CLASS__, 'render_form' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registriert den Endpunkt für Rückmeldungen.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			MXO_Bem_Portal::REST_NS,
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				// Bewusst öffentlich: das Formular richtet sich an abgemeldete
				// Besucher. Gegen Automaten schützen Nonce und Honeypot.
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'handle' ),
			)
		);
	}

	/**
	 * Speichert eine Rückmeldung.
	 *
	 * @param WP_REST_Request $request Die Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( ! wp_verify_nonce( $nonce, 'mxo_bem_feedback' ) ) {
			return new WP_Error( 'mxo_bem_bad_nonce', __( 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden.', 'mxo-bemusterung' ), array( 'status' => 403 ) );
		}

		// Honeypot: ein für Menschen unsichtbares Feld, das nur Automaten ausfüllen.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 201 );
		}

		$promo_id = absint( $request->get_param( 'bem' ) );
		if ( get_post_type( $promo_id ) !== MXO_Bem_CPT::PROMO ) {
			return new WP_Error( 'mxo_bem_no_promo', __( 'Unbekannte Bemusterung.', 'mxo-bemusterung' ), array( 'status' => 400 ) );
		}

		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$station = sanitize_text_field( (string) $request->get_param( 'station' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( '' === $name && '' === $station && '' === $message ) {
			return new WP_Error( 'mxo_bem_empty', __( 'Bitte zumindest ein Feld ausfüllen.', 'mxo-bemusterung' ), array( 'status' => 400 ) );
		}

		$feedback_id = wp_insert_post(
			array(
				'post_type'   => MXO_Bem_CPT::FEEDBACK,
				'post_parent' => $promo_id,
				'post_status' => 'publish',
				'post_title'  => $station ? $station : $name,
				'meta_input'  => array(
					'mxo_bem_fb_name'    => $name,
					'mxo_bem_fb_station' => $station,
					'mxo_bem_fb_email'   => $email,
					'mxo_bem_fb_message' => $message,
				),
			),
			true
		);

		if ( is_wp_error( $feedback_id ) ) {
			return $feedback_id;
		}

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * Gibt das Formular aus.
	 *
	 * @param array<string, mixed> $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function render_form( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mxo_bemusterung_feedback' );

		$promo_id = absint( $atts['id'] );
		if ( ! $promo_id ) {
			$promo_id = (int) get_the_ID();
		}

		if ( get_post_type( $promo_id ) !== MXO_Bem_CPT::PROMO ) {
			return '';
		}

		ob_start();
		?>
		<div class="mxo-bem-feedback" data-bem="<?php echo esc_attr( (string) $promo_id ); ?>"
			data-endpoint="<?php echo esc_url( rest_url( MXO_Bem_Portal::REST_NS . '/feedback' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'mxo_bem_feedback' ) ); ?>">
			<p class="mxo-bem-feedback__intro">
				<?php esc_html_e( 'Freiwillig: Sag uns kurz, wer du bist. Der Download funktioniert auch ohne.', 'mxo-bemusterung' ); ?>
			</p>
			<label><?php esc_html_e( 'Name', 'mxo-bemusterung' ); ?>
				<input type="text" name="name" autocomplete="name"></label>
			<label><?php esc_html_e( 'Sender', 'mxo-bemusterung' ); ?>
				<input type="text" name="station" autocomplete="organization"></label>
			<label><?php esc_html_e( 'E-Mail', 'mxo-bemusterung' ); ?>
				<input type="email" name="email" autocomplete="email"></label>
			<label><?php esc_html_e( 'Rückmeldung', 'mxo-bemusterung' ); ?>
				<textarea name="message" rows="3"></textarea></label>
			<p class="mxo-bem-feedback__hp" aria-hidden="true">
				<label><?php esc_html_e( 'Website', 'mxo-bemusterung' ); ?>
					<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			</p>
			<button type="button" class="mxo-bem-feedback__submit"><?php esc_html_e( 'Absenden', 'mxo-bemusterung' ); ?></button>
			<p class="mxo-bem-feedback__status" role="status"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
