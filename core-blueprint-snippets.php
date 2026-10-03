<?php
/**
 * Plugin Name: Core Blueprint Snippets
 * Plugin URI:  https://coreblueprint.io
 * Description: Governed PHP, CSS, JavaScript and HTML snippets for Core Blueprint.
 * Version:     1.0.0-rc1
 * Author:      Core Blueprint
 * Author URI:  https://coreblueprint.io
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: core-blueprint-snippets
 * Domain Path: /languages
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Requires Plugins:  core-blueprint
 *
 * @package Core_Blueprint_Snippets
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'CB_SNIPPETS_FILE' ) ) {
	return;
}

define( 'CB_SNIPPETS_VERSION', '1.0.0-rc1' );
define( 'CB_SNIPPETS_FILE', __FILE__ );
define( 'CB_SNIPPETS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_SNIPPETS_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_SNIPPETS_BASENAME', plugin_basename( __FILE__ ) );

if (
	! defined( 'CB_CORE_VERSION' )
	|| ! defined( 'CB_CORE_API_VERSION' )
	|| version_compare( (string) CB_CORE_API_VERSION, '1.2', '<' )
	|| ! class_exists( '\\CoreBlueprint\\Core\\ExtensionRegistry' )
) {
	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Core Blueprint Snippets requires Core Blueprint Base with Distribution API 1.2 or newer.', 'core-blueprint-snippets' );
		echo '</p></div>';
	} );
	return;
}

// During a staged upgrade, an older Base may still contain the embedded
// Snippets runtime. Stay completely dormant in that state so managed code can
// never execute twice. The standalone plugin takes ownership automatically
// after Base is updated to the extraction release.
if ( class_exists( '\\CoreBlueprint\\Core\\Snippets\\Bootstrap' ) ) {
	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Core Blueprint Snippets is installed and waiting for the Core Blueprint Base update that removes the embedded Snippets runtime.', 'core-blueprint-snippets' );
		echo '</p></div>';
	} );
	return;
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CoreBlueprint\\Snippets\\';
	$length = strlen( $prefix );
	if ( strncmp( $class, $prefix, $length ) !== 0 ) {
		return;
	}

	$relative = substr( $class, $length );
	$file = CB_SNIPPETS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, static function (): void {
	if ( class_exists( '\\CoreBlueprint\\Snippets\\Repository' ) ) {
		\CoreBlueprint\Snippets\Repository::rebuild_index();
	}
} );

\CoreBlueprint\Snippets\Bootstrap::boot();
