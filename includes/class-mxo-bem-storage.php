<?php
/**
 * Ablage der Masterdateien außerhalb der normalen Mediathekspfade.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legt Masterdateien in einen nicht erratbaren Unterordner und schirmt ihn ab.
 */
class MXO_Bem_Storage {

	const BASE       = 'mxo-bemusterung';
	const SECRET_KEY = 'mxo_bem_dir_secret';

	/**
	 * Legt den Basisordner an und schirmt ihn gegen direkten Zugriff ab.
	 *
	 * Wichtig: .htaccess wirkt nur unter Apache. Unter Nginx trägt der Schutz
	 * allein über den zufälligen Pfadanteil, siehe promo_dir().
	 *
	 * @return string Absoluter Pfad des Basisordners.
	 */
	public static function ensure_base_dir() {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] ) . self::BASE;

		if ( ! file_exists( $base ) ) {
			wp_mkdir_p( $base );
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$htaccess = trailingslashit( $base ) . '.htaccess';
		if ( $wp_filesystem && ! $wp_filesystem->exists( $htaccess ) ) {
			$rules = "Options -Indexes\n"
				. "<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n"
				. "<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n";
			$wp_filesystem->put_contents( $htaccess, $rules, FS_CHMOD_FILE );
		}

		$index = trailingslashit( $base ) . 'index.php';
		if ( $wp_filesystem && ! $wp_filesystem->exists( $index ) ) {
			$wp_filesystem->put_contents( $index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
		}

		return $base;
	}

	/**
	 * Liefert den Unterordner einer Bemusterung, relativ zum Uploads-Verzeichnis.
	 *
	 * Der Ordnername enthält einen zufälligen Anteil, damit die Datei auch dann
	 * nicht erratbar ist, wenn der Webserver keine .htaccess auswertet.
	 *
	 * @param int $promo_id ID der Bemusterung.
	 * @return string Relativer Pfad, z. B. mxo-bemusterung/17-9f3a...
	 */
	public static function promo_dir( $promo_id ) {
		$promo_id = absint( $promo_id );
		$slug     = get_post_meta( $promo_id, 'mxo_bem_dir_slug', true );

		if ( ! $slug ) {
			$slug = wp_generate_password( 24, false, false );
			update_post_meta( $promo_id, 'mxo_bem_dir_slug', $slug );
		}

		return self::BASE . '/' . $promo_id . '-' . sanitize_key( $slug );
	}

	/**
	 * Lenkt wp_handle_upload für die Dauer eines Uploads in den geschützten Ordner.
	 *
	 * @param int $promo_id ID der Bemusterung.
	 * @return callable Der gesetzte Filter, zum späteren Entfernen.
	 */
	public static function redirect_uploads_to( $promo_id ) {
		self::ensure_base_dir();
		$subdir = self::promo_dir( $promo_id );

		$filter = static function ( $dirs ) use ( $subdir ) {
			$dirs['subdir'] = '/' . $subdir;
			$dirs['path']   = $dirs['basedir'] . '/' . $subdir;
			$dirs['url']    = $dirs['baseurl'] . '/' . $subdir;
			return $dirs;
		};

		add_filter( 'upload_dir', $filter );
		return $filter;
	}

	/**
	 * Hebt die Umleitung wieder auf.
	 *
	 * @param callable $filter Der von redirect_uploads_to() gelieferte Filter.
	 * @return void
	 */
	public static function restore_uploads( $filter ) {
		remove_filter( 'upload_dir', $filter );
	}
}
