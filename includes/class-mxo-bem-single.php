<?php
/**
 * Darstellung der öffentlichen Promoseite.
 *
 * Das Plugin bringt ein fertiges, lesbares Layout mit, damit eine Bemusterung
 * ohne Theme-Arbeit funktioniert. Wer es selbst bauen will, schaltet es unter
 * Einstellungen ab und verwendet Dynamic Data plus den Download-Shortcode.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert die Promoseite.
 */
class MXO_Bem_Single {

	/**
	 * Schutz gegen Rekursion beim the_content-Filter.
	 *
	 * @var bool
	 */
	protected static $rendering = false;

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_shortcode( 'mxo_bemusterung_download', array( __CLASS__, 'download_shortcode' ) );
	}

	/**
	 * Ersetzt den Inhalt der Promoseite durch die fertige Darstellung.
	 *
	 * @param string $content Bisheriger Inhalt.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( self::$rendering || ! is_singular( MXO_Bem_CPT::PROMO ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( MXO_Bem_Settings::get( 'own_layout' ) ) {
			return $content;
		}

		self::$rendering = true;
		$output          = self::render( get_the_ID(), $content );
		self::$rendering = false;

		return $output;
	}

	/**
	 * Baut die Promoseite.
	 *
	 * @param int    $promo_id ID der Bemusterung.
	 * @param string $text     Pressetext als bereits gefilterter Inhalt.
	 * @return string
	 */
	public static function render( $promo_id, $text ) {
		wp_enqueue_style( 'mxo-bem-promo', MXO_BEM_URL . 'assets/css/promo.css', array(), MXO_BEM_VERSION );

		$artist       = (string) get_post_meta( $promo_id, 'mxo_bem_artist', true );
		$label        = (string) get_post_meta( $promo_id, 'mxo_bem_label', true );
		$release      = (string) get_post_meta( $promo_id, 'mxo_bem_release_date', true );
		$contact      = (string) get_post_meta( $promo_id, 'mxo_bem_contact', true );
		$cover_id     = (int) get_post_meta( $promo_id, 'mxo_bem_cover_id', true );
		$presskit_id  = (int) get_post_meta( $promo_id, 'mxo_bem_presskit_id', true );
		$tracks       = MXO_Bem_CPT::get_tracks( $promo_id );

		ob_start();
		?>
		<div class="mxo-promo">

			<div class="mxo-promo__header">
				<?php if ( $cover_id ) : ?>
					<div class="mxo-promo__cover">
						<?php echo wp_get_attachment_image( $cover_id, 'medium_large', false, array( 'alt' => '' ) ); ?>
					</div>
				<?php endif; ?>

				<div class="mxo-promo__intro">
					<p class="mxo-promo__kicker"><?php esc_html_e( 'Bemusterung', 'mxo-bemusterung' ); ?></p>
					<h1 class="mxo-promo__title"><?php echo esc_html( get_the_title( $promo_id ) ); ?></h1>

					<?php if ( $artist ) : ?>
						<p class="mxo-promo__artist"><?php echo esc_html( $artist ); ?></p>
					<?php endif; ?>

					<dl class="mxo-promo__facts">
						<?php if ( $label ) : ?>
							<div><dt><?php esc_html_e( 'Label', 'mxo-bemusterung' ); ?></dt><dd><?php echo esc_html( $label ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $release ) : ?>
							<div>
								<dt><?php esc_html_e( 'Release', 'mxo-bemusterung' ); ?></dt>
								<dd><?php echo esc_html( self::format_date( $release ) ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( $tracks ) : ?>
							<div>
								<dt><?php esc_html_e( 'Titel', 'mxo-bemusterung' ); ?></dt>
								<dd><?php echo esc_html( (string) count( $tracks ) ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				</div>
			</div>

			<?php if ( $tracks ) : ?>
				<div class="mxo-promo__tracks">
					<?php foreach ( $tracks as $track ) : ?>
						<?php
						$preview_id = (int) get_post_meta( $track->ID, 'mxo_bem_preview_id', true );
						$master_id  = (int) get_post_meta( $track->ID, 'mxo_bem_master_id', true );
						?>
						<div class="mxo-promo__track">
							<h2 class="mxo-promo__trackname"><?php echo esc_html( $track->post_title ); ?></h2>

							<?php if ( $preview_id ) : ?>
								<audio class="mxo-promo__audio" controls preload="none"
									src="<?php echo esc_url( (string) wp_get_attachment_url( $preview_id ) ); ?>"></audio>
							<?php endif; ?>

							<?php if ( $master_id ) : ?>
								<p class="mxo-promo__dl">
									<a class="mxo-promo__btn" href="<?php echo esc_url( MXO_Bem_Download::url( $track->ID ) ); ?>">
										<?php esc_html_e( 'WAV herunterladen', 'mxo-bemusterung' ); ?>
									</a>
									<span class="mxo-promo__filemeta">
										<?php echo esc_html( self::file_note( $master_id ) ); ?>
									</span>
								</p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( trim( wp_strip_all_tags( $text ) ) ) : ?>
				<div class="mxo-promo__text">
					<?php echo wp_kses_post( $text ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $presskit_id ) : ?>
				<p class="mxo-promo__presskit">
					<a class="mxo-promo__btn mxo-promo__btn--plain"
						href="<?php echo esc_url( (string) wp_get_attachment_url( $presskit_id ) ); ?>">
						<?php esc_html_e( 'Presskit herunterladen', 'mxo-bemusterung' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( $contact ) : ?>
				<p class="mxo-promo__contact">
					<?php
					printf(
						/* translators: %s: Name oder Kontaktangabe */
						esc_html__( 'Rückfragen, Interviews und Station-Voices: %s', 'mxo-bemusterung' ),
						esc_html( $contact )
					);
					?>
				</p>
			<?php endif; ?>

			<?php echo do_shortcode( '[mxo_bemusterung_feedback]' ); ?>

		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Formatiert ein Releasedatum nach den Einstellungen der Website.
	 *
	 * @param string $raw Datum im Format JJJJ-MM-TT.
	 * @return string
	 */
	protected static function format_date( $raw ) {
		$time = strtotime( $raw );
		return $time ? wp_date( (string) get_option( 'date_format' ), $time ) : $raw;
	}

	/**
	 * Liefert Format und Größe einer Datei als kurze Notiz.
	 *
	 * @param int $attachment_id ID des Anhangs.
	 * @return string
	 */
	protected static function file_note( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		$ext = strtoupper( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		return trim( $ext . ' · ' . size_format( (int) filesize( $path ) ) );
	}

	/**
	 * Shortcode für einen einzelnen Downloadbutton.
	 *
	 * Gedacht für eigene Layouts, etwa in einer Query-Loop-Vorlage:
	 * [mxo_bemusterung_download id="123"]. Ohne id wird der aktuelle
	 * Beitrag verwendet.
	 *
	 * @param array<string, mixed> $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function download_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => __( 'WAV herunterladen', 'mxo-bemusterung' ),
			),
			$atts,
			'mxo_bemusterung_download'
		);

		$track_id = absint( $atts['id'] );
		if ( ! $track_id ) {
			$track_id = (int) get_the_ID();
		}

		if ( get_post_type( $track_id ) !== MXO_Bem_CPT::TRACK ) {
			return '';
		}

		if ( ! get_post_meta( $track_id, 'mxo_bem_master_id', true ) ) {
			return '';
		}

		return sprintf(
			'<a class="mxo-promo__btn" href="%s">%s</a>',
			esc_url( MXO_Bem_Download::url( $track_id ) ),
			esc_html( (string) $atts['label'] )
		);
	}
}
