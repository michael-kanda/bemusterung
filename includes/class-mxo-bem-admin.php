<?php
/**
 * Backend: Zugänge verwalten, Downloadzahlen sehen.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verwaltungsseite für die Zugangslinks.
 */
class MXO_Bem_Admin {

	const CREATE = 'mxo_bem_access_create';
	const REVOKE = 'mxo_bem_access_revoke';

	/**
	 * Hooks setzen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_' . self::CREATE, array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_' . self::REVOKE, array( __CLASS__, 'handle_revoke' ) );
		add_filter( 'manage_' . MXO_Bem_CPT::TRACK . '_posts_columns', array( __CLASS__, 'track_columns' ) );
		add_action( 'manage_' . MXO_Bem_CPT::TRACK . '_posts_custom_column', array( __CLASS__, 'track_column' ), 10, 2 );
	}

	/**
	 * Hängt die Zugangsseite unter die Bemusterungen.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . MXO_Bem_CPT::PROMO,
			__( 'Zugänge', 'mxo-bemusterung' ),
			__( 'Zugänge', 'mxo-bemusterung' ),
			'manage_options',
			'mxo-bemusterung-access',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Gibt die Zugangsseite aus.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mxo-bemusterung' ) );
		}

		$fresh = get_transient( 'mxo_bem_fresh_access' );
		if ( $fresh ) {
			delete_transient( 'mxo_bem_fresh_access' );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zugänge', 'mxo-bemusterung' ); ?></h1>
			<p>
				<?php esc_html_e( 'Jeder Zugang ist ein dauerhafter Link. Wer ihn hat, verwaltet seine eigenen Bemusterungen im Frontend: anlegen, Dateien hinzufügen, veröffentlichen, Link weitergeben. Kein WordPress-Konto nötig.', 'mxo-bemusterung' ); ?>
			</p>

			<?php if ( $fresh ) : ?>
				<div class="notice notice-success">
					<p><strong><?php esc_html_e( 'Diesen Link jetzt kopieren und weitergeben. Er wird nicht noch einmal angezeigt.', 'mxo-bemusterung' ); ?></strong></p>
					<p><input type="text" readonly class="large-text code" onfocus="this.select()"
						value="<?php echo esc_attr( $fresh ); ?>"></p>
				</div>
			<?php endif; ?>

			<?php if ( '' === MXO_Bem_Settings::upload_page_url() ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Es steht noch keine Portalseite fest. Lege eine Seite mit dem Shortcode [mxo_bemusterung_portal] an.', 'mxo-bemusterung' ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Neuen Zugang anlegen', 'mxo-bemusterung' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE ); ?>">
				<?php wp_nonce_field( self::CREATE ); ?>
				<p>
					<label for="mxo-bem-label"><?php esc_html_e( 'Name der Person oder Firma', 'mxo-bemusterung' ); ?></label><br>
					<input type="text" id="mxo-bem-label" name="label" class="regular-text" required>
				</p>
				<?php submit_button( __( 'Zugang anlegen', 'mxo-bemusterung' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Bestehende Zugänge', 'mxo-bemusterung' ); ?></h2>
			<?php self::render_table(); ?>
		</div>
		<?php
	}

	/**
	 * Gibt die Tabelle der Zugänge aus.
	 *
	 * @return void
	 */
	protected static function render_table() {
		$access = MXO_Bem_Access::all();

		if ( empty( $access ) ) {
			echo '<p>' . esc_html__( 'Noch keine Zugänge vergeben.', 'mxo-bemusterung' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'mxo-bemusterung' ); ?></th>
					<th><?php esc_html_e( 'Angelegt', 'mxo-bemusterung' ); ?></th>
					<th><?php esc_html_e( 'Bemusterungen', 'mxo-bemusterung' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $access as $id => $entry ) : ?>
				<?php
				$revoke_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=' . self::REVOKE . '&id=' . rawurlencode( $id ) ),
					self::REVOKE . '_' . $id
				);
				?>
				<tr>
					<td><strong><?php echo esc_html( $entry['label'] ); ?></strong></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $entry['created'] ) ); ?></td>
					<td><?php echo esc_html( (string) count( MXO_Bem_Access::promos( $id ) ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $revoke_url ); ?>" class="button button-small"
							onclick="return confirm('<?php echo esc_js( __( 'Zugang wirklich zurückziehen? Der Link funktioniert danach nicht mehr. Die angelegten Bemusterungen bleiben bestehen.', 'mxo-bemusterung' ) ); ?>');">
							<?php esc_html_e( 'Zurückziehen', 'mxo-bemusterung' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Legt einen Zugang an.
	 *
	 * @return void
	 */
	public static function handle_create() {
		check_admin_referer( self::CREATE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mxo-bemusterung' ) );
		}

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( '' !== $label ) {
			$created = MXO_Bem_Access::create( $label );
			set_transient( 'mxo_bem_fresh_access', MXO_Bem_Access::portal_url( $created['token'] ), 5 * MINUTE_IN_SECONDS );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . MXO_Bem_CPT::PROMO . '&page=mxo-bemusterung-access' ) );
		exit;
	}

	/**
	 * Zieht einen Zugang zurück.
	 *
	 * @return void
	 */
	public static function handle_revoke() {
		$id = isset( $_GET['id'] ) ? MXO_Bem_Access::sanitize_id( wp_unslash( $_GET['id'] ) ) : '';

		check_admin_referer( self::REVOKE . '_' . $id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mxo-bemusterung' ) );
		}

		MXO_Bem_Access::revoke( $id );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . MXO_Bem_CPT::PROMO . '&page=mxo-bemusterung-access' ) );
		exit;
	}

	/**
	 * Ergänzt die Trackliste um eine Downloadspalte.
	 *
	 * @param array<string, string> $columns Bestehende Spalten.
	 * @return array<string, string>
	 */
	public static function track_columns( $columns ) {
		$columns['mxo_bem_downloads'] = __( 'Downloads', 'mxo-bemusterung' );
		return $columns;
	}

	/**
	 * Gibt den Inhalt der Downloadspalte aus.
	 *
	 * @param string $column  Spaltenname.
	 * @param int    $post_id Beitrags-ID.
	 * @return void
	 */
	public static function track_column( $column, $post_id ) {
		if ( 'mxo_bem_downloads' !== $column ) {
			return;
		}
		echo esc_html( (string) absint( get_post_meta( $post_id, 'mxo_bem_downloads', true ) ) );
	}
}
