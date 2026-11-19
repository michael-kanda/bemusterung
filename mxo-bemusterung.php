<?php
/**
 * Plugin Name:       MXO Bemusterung
 * Plugin URI:        https://designare.at/plugins/mxo-bemusterung
 * Description:       Selbstverwaltete Promoseiten für Musikbemusterungen. Externe legen über einen dauerhaften Link eigene Bemusterungen an, ziehen Dateien per Drag and Drop hinein und veröffentlichen selbst. Kein WordPress-Konto nötig.
 * Version:           0.5.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Michael Kanda
 * Author URI:        https://designare.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mxo-bemusterung
 * Domain Path:       /languages
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MXO_BEM_VERSION', '0.5.1' );
define( 'MXO_BEM_FILE', __FILE__ );
define( 'MXO_BEM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MXO_BEM_URL', plugin_dir_url( __FILE__ ) );

require_once MXO_BEM_PATH . 'includes/class-mxo-bem-cpt.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-settings.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-storage.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-access.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-privacy.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-portal.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-upload.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-download.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-single.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-feedback.php';
require_once MXO_BEM_PATH . 'includes/class-mxo-bem-admin.php';

/**
 * Startet das Plugin.
 *
 * @return void
 */
function mxo_bem_bootstrap() {
	MXO_Bem_CPT::init();
	MXO_Bem_Privacy::init();
	MXO_Bem_Portal::init();
	MXO_Bem_Upload::init();
	MXO_Bem_Download::init();
	MXO_Bem_Single::init();
	MXO_Bem_Feedback::init();

	if ( is_admin() ) {
		MXO_Bem_Settings::init();
		MXO_Bem_Admin::init();
	}
}
add_action( 'plugins_loaded', 'mxo_bem_bootstrap' );

/**
 * Verwirft die Seitenerkennung, wenn eine Seite gespeichert wurde.
 *
 * Ohne das würde eine frisch eingefügte Shortcode-Seite bis zu einer Stunde
 * lang nicht erkannt.
 *
 * @param int $post_id Beitrags-ID.
 * @return void
 */
function mxo_bem_flush_page_detection( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( in_array( get_post_type( $post_id ), array( 'page', 'post' ), true ) ) {
		MXO_Bem_Settings::flush_detection();
	}
}
add_action( 'save_post', 'mxo_bem_flush_page_detection' );

/**
 * Aktivierung: Post Types registrieren, Schreibregeln erneuern, Ablageordner anlegen.
 *
 * @return void
 */
function mxo_bem_activate() {
	MXO_Bem_CPT::register_post_types();
	MXO_Bem_Storage::ensure_base_dir();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mxo_bem_activate' );

/**
 * Deaktivierung: Schreibregeln und Zwischenspeicher aufräumen.
 *
 * @return void
 */
function mxo_bem_deactivate() {
	MXO_Bem_Settings::flush_detection();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mxo_bem_deactivate' );
