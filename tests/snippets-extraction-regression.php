<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

function fail_snippets_contract( string $message ): never {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function require_contains( string $path, string $needle ): void {
	$source = (string) file_get_contents( $path );
	if ( ! str_contains( $source, $needle ) ) {
		fail_snippets_contract( basename( $path ) . " missing contract: {$needle}" );
	}
}

function require_not_contains( string $path, string $needle ): void {
	$source = (string) file_get_contents( $path );
	if ( str_contains( $source, $needle ) ) {
		fail_snippets_contract( basename( $path ) . " contains forbidden contract: {$needle}" );
	}
}

$plugin = $root . '/core-blueprint-snippets.php';
$bootstrap = $root . '/src/Bootstrap.php';
$paths = $root . '/src/Paths.php';
$settings = $root . '/src/Settings.php';
$actions = $root . '/src/Admin/Actions.php';
$uninstall = $root . '/uninstall.php';
$module_state = $root . '/src/ModuleState.php';

foreach ( [ $plugin, $bootstrap, $paths, $settings, $actions, $uninstall, $module_state ] as $required ) {
	if ( ! is_file( $required ) ) {
		fail_snippets_contract( "required file missing: {$required}" );
	}
}

require_contains( $plugin, 'Requires Plugins:  core-blueprint' );
require_contains( $plugin, "add_action( 'plugins_loaded'" );
require_contains( $plugin, "Bootstrap::class, 'boot' ], 0" );
require_contains( $bootstrap, "class_exists( '\\\\CoreBlueprint\\\\Core\\\\Snippets\\\\Bootstrap' )" );
require_contains( $bootstrap, "'id'            => 'core-blueprint-snippets'" );
require_contains( $bootstrap, "'requires_api'  => '1.2'" );
require_contains( $bootstrap, "'state'      => ModuleState::class" );
require_contains( $bootstrap, "PageRegistry::register(" );
require_not_contains( $bootstrap, 'register_base(' );

require_contains( $paths, "WP_CONTENT_DIR ) . 'cb-snippets'" );
require_contains( $settings, "public const OPTION = 'cb_core_snippets_settings'" );
require_contains( $actions, "admin_post_cb_core_snippets_save" );
require_contains( $actions, "admin_post_cb_core_snippets_import" );
require_contains( $module_state, 'implements ModuleStateInterface' );

require_contains( $uninstall, "delete_option( 'cb_core_snippets_settings' )" );
require_contains( $uninstall, "WP_CONTENT_DIR ) . 'cb-snippets'" );
require_contains( $uninstall, "'/runtime-index.php'" );
require_contains( $uninstall, 'Preserved deliberately: registry.php, code/* and direct-access guard files.' );
require_not_contains( $uninstall, "apply_filters( 'core_blueprint_snippets_storage_dir'" );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$source = (string) file_get_contents( $file->getPathname() );
	if ( str_contains( $source, 'namespace CoreBlueprint\\Core\\Snippets' ) ) {
		fail_snippets_contract( 'legacy embedded namespace remains in ' . $file->getPathname() );
	}
}

echo "Snippets extraction contract: PASS\n";
