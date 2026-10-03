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

register_activation_hook( __FILE__, [ \CoreBlueprint\Snippets\Bootstrap::class, 'activate' ] );

add_action( 'plugins_loaded', [ \CoreBlueprint\Snippets\Bootstrap::class, 'boot' ], 0 );
