<?php
/**
 * Einstellungen des Plugins.
 *
 * Alles, was früher nur über Filter erreichbar war, steht hier als Feld im
 * Backend. Die Filter bleiben als Notausgang bestehen, sind aber für den
 * Normalbetrieb nicht mehr nötig.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert Option, Einstellungsseite und Felder.
 */
class MXO_Bem_Settings {

	const OPTION = 'mxo_bem_settings';
	const GROUP  = 'mxo_bem_settings_group';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
	}

	/**
	 * Liefert die Standardwerte.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'upload_page'   => 0,
			'max_mb'        => 300,
			'delivery'      => 'php',
			'notify_email'  => get_option( 'admin_email' ),
			'noindex_upload' => 1,
			'own_layout'     => 0,
		);
	}

	/**
	 * Liefert einen einzelnen Einstellungswert.
	 *
	 * @param string $key Name des Feldes.
	 * @return mixed
	 */
	public static function get( $key ) {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = self::defaults();

		if ( isset( $saved[ $key ] ) && '' !== $saved[ $key ] ) {
			return $saved[ $key ];
		}

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
	}

	/**
	 * Liefert die ID der Upload-Seite.
	 *
	 * Ist nichts eingestellt, sucht das Plugin selbst nach der Seite, auf der
	 * der Shortcode steht. Nur wenn es mehrere oder gar keine gibt, muss
	 * jemand eingreifen.
	 *
	 * @return int
	 */
	public static function upload_page_id() {
		$configured = absint( self::get( 'upload_page' ) );

		if ( $configured && 'publish' === get_post_status( $configured ) ) {
			return $configured;
		}

		$found = self::detect_upload_pages();

		return count( $found ) === 1 ? (int) $found[0] : 0;
	}

	/**
	 * Sucht alle veröffentlichten Seiten mit dem Upload-Shortcode.
	 *
	 * @return int[] Gefundene Seiten-IDs.
	 */
	public static function detect_upload_pages() {
		$cached = get_transient( 'mxo_bem_upload_pages' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = %s
				AND post_type IN ( 'page', 'post' )
				AND ( post_content LIKE %s OR post_content LIKE %s )
				LIMIT 20",
				'publish',
				'%' . $wpdb->esc_like( '[mxo_bemusterung_portal' ) . '%',
				'%' . $wpdb->esc_like( '[mxo_bemusterung_upload' ) . '%'
			)
		);

		$ids = array_map( 'absint', (array) $ids );
		set_transient( 'mxo_bem_upload_pages', $ids, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Verwirft die zwischengespeicherte Seitenerkennung.
	 *
	 * @return void
	 */
	public static function flush_detection() {
		delete_transient( 'mxo_bem_upload_pages' );
	}

	/**
	 * Liefert die URL der Upload-Seite.
	 *
	 * @return string Leer, wenn keine Seite feststeht.
	 */
	public static function upload_page_url() {
		$page_id = self::upload_page_id();
		$url     = $page_id ? (string) get_permalink( $page_id ) : '';

		/**
		 * Überschreibt die ermittelte Upload-Seite.
		 *
		 * Nur als Notausgang gedacht, der Normalfall ist die Einstellungsseite.
		 *
		 * @param string $url     Ermittelte URL.
		 * @param int    $page_id Ermittelte Seiten-ID.
		 */
		return (string) apply_filters( 'mxo_bem_upload_page_url', $url, $page_id );
	}

	/**
	 * Meldet die Option bei der Settings API an.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'mxo_bem_section_main',
			__( 'Grundeinstellungen', 'mxo-bemusterung' ),
			array( __CLASS__, 'render_section_intro' ),
			'mxo-bemusterung'
		);

		$fields = array(
			'upload_page'    => __( 'Portalseite', 'mxo-bemusterung' ),
			'noindex_upload' => __( 'Portalseite verstecken', 'mxo-bemusterung' ),
			'max_mb'         => __( 'Maximale Dateigröße', 'mxo-bemusterung' ),
			'delivery'       => __( 'Auslieferung der Downloads', 'mxo-bemusterung' ),
			'own_layout'     => __( 'Layout der Promoseite', 'mxo-bemusterung' ),
			'notify_email'   => __( 'Benachrichtigung an', 'mxo-bemusterung' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'mxo_bem_field_' . $key,
				$label,
				array( __CLASS__, 'render_field' ),
				'mxo-bemusterung',
				'mxo_bem_section_main',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Prüft und begrenzt die eingegebenen Werte.
	 *
	 * @param mixed $input Rohwerte aus dem Formular.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$out    = self::defaults();

		$out['upload_page']    = isset( $input['upload_page'] ) ? absint( $input['upload_page'] ) : 0;
		$out['noindex_upload'] = empty( $input['noindex_upload'] ) ? 0 : 1;
		$out['own_layout']     = empty( $input['own_layout'] ) ? 0 : 1;
		$out['max_mb']         = isset( $input['max_mb'] ) ? min( 4096, max( 1, absint( $input['max_mb'] ) ) ) : 300;

		$allowed_delivery   = array( 'php', 'x-accel', 'x-sendfile' );
		$delivery           = isset( $input['delivery'] ) ? sanitize_key( $input['delivery'] ) : 'php';
		$out['delivery']    = in_array( $delivery, $allowed_delivery, true ) ? $delivery : 'php';

		$email               = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';
		$out['notify_email'] = is_email( $email ) ? $email : get_option( 'admin_email' );

		self::flush_detection();

		return $out;
	}

	/**
	 * Hängt die Einstellungsseite unter die Bemusterungen.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . MXO_Bem_CPT::PROMO,
			__( 'Bemusterung: Einstellungen', 'mxo-bemusterung' ),
			__( 'Einstellungen', 'mxo-bemusterung' ),
			'manage_options',
			'mxo-bemusterung',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Gibt den Einleitungstext der Sektion aus.
	 *
	 * @return void
	 */
	public static function render_section_intro() {
		$found = self::detect_upload_pages();

		if ( empty( $found ) ) {
			echo '<p>' . esc_html__( 'Es wurde noch keine Seite mit dem Shortcode [mxo_bemusterung_portal] gefunden. Lege eine Seite an und setze den Shortcode hinein.', 'mxo-bemusterung' ) . '</p>';
			return;
		}

		if ( 1 === count( $found ) ) {
			printf(
				'<p>%s <strong>%s</strong></p>',
				esc_html__( 'Der Shortcode wurde automatisch gefunden auf:', 'mxo-bemusterung' ),
				esc_html( (string) get_the_title( $found[0] ) )
			);
			return;
		}

		echo '<p>' . esc_html__( 'Der Shortcode steht auf mehreren Seiten. Bitte unten auswählen, welche für die Upload-Links verwendet wird.', 'mxo-bemusterung' ) . '</p>';
	}

	/**
	 * Gibt ein einzelnes Feld aus.
	 *
	 * @param array<string, mixed> $args Feldargumente, erwartet 'key'.
	 * @return void
	 */
	public static function render_field( $args ) {
		$key   = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$value = self::get( $key );
		$name  = self::OPTION . '[' . $key . ']';

		switch ( $key ) {
			case 'upload_page':
				wp_dropdown_pages(
					array(
						'name'              => $name,
						'selected'          => absint( $value ),
						'show_option_none'  => __( '— automatisch erkennen —', 'mxo-bemusterung' ),
						'option_none_value' => '0',
					)
				);
				echo '<p class="description">'
					. esc_html__( 'Nur nötig, wenn der Shortcode auf mehreren Seiten steht.', 'mxo-bemusterung' )
					. '</p>';
				break;

			case 'own_layout':
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html__( 'Mitgeliefertes Layout abschalten und selbst im Theme bauen', 'mxo-bemusterung' )
				);
				echo '<p class="description">'
					. esc_html__( 'Standardmäßig bringt das Plugin eine fertige Promoseite mit Abspieler und Downloadbutton mit. Für ein eigenes Layout stehen alle Felder als Post Meta bereit, dazu der Shortcode [mxo_bemusterung_download].', 'mxo-bemusterung' )
					. '</p>';
				break;

			case 'noindex_upload':
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html__( 'Die Portalseite auf noindex setzen und aus der Sitemap nehmen', 'mxo-bemusterung' )
				);
				break;

			case 'max_mb':
				printf(
					'<input type="number" min="1" max="4096" class="small-text" name="%s" value="%s"> MB',
					esc_attr( $name ),
					esc_attr( (string) absint( $value ) )
				);
				self::render_php_limit_hint();
				break;

			case 'delivery':
				$options = array(
					'php'        => __( 'Durch PHP (funktioniert überall, langsamer)', 'mxo-bemusterung' ),
					'x-accel'    => __( 'X-Accel-Redirect (Nginx)', 'mxo-bemusterung' ),
					'x-sendfile' => __( 'X-Sendfile (Apache, LiteSpeed)', 'mxo-bemusterung' ),
				);
				printf( '<select name="%s">', esc_attr( $name ) );
				foreach ( $options as $option_value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option_value ),
						selected( $option_value, (string) $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'notify_email':
				printf(
					'<input type="email" class="regular-text" name="%s" value="%s">',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
		}
	}

	/**
	 * Warnt, wenn die PHP-Limits unter der eingestellten Maximalgröße liegen.
	 *
	 * @return void
	 */
	protected static function render_php_limit_hint() {
		$php_limit = wp_max_upload_size();
		$wanted    = absint( self::get( 'max_mb' ) ) * MB_IN_BYTES;

		if ( $php_limit && $wanted > $php_limit ) {
			echo '<p class="description" style="color:#b3261e">' . esc_html(
				sprintf(
					/* translators: %s: vom Server erlaubte Maximalgröße */
					__( 'Achtung: der Server lässt aktuell nur %s zu. Größere Uploads brechen ab, bis upload_max_filesize und post_max_size angehoben sind.', 'mxo-bemusterung' ),
					size_format( $php_limit )
				)
			) . '</p>';
		}
	}

	/**
	 * Gibt die Einstellungsseite aus.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mxo-bemusterung' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( 'mxo-bemusterung' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
