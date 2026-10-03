<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$tests_dir = rtrim( (string) getenv( 'WP_TESTS_DIR' ), "/\\" );
$base_plugin_file = (string) getenv( 'CB_BASE_PLUGIN_FILE' );
$snippets_plugin_file = (string) getenv( 'CB_SNIPPETS_PLUGIN_FILE' );

if ( '' === $tests_dir || ! is_file( $tests_dir . '/includes/functions.php' ) ) {
    throw new RuntimeException( 'WP_TESTS_DIR must point to the pinned WordPress PHPUnit library.' );
}
if ( '' === $base_plugin_file || ! is_file( $base_plugin_file ) ) {
    throw new RuntimeException( 'CB_BASE_PLUGIN_FILE must point to the installed Core Blueprint Base entrypoint.' );
}
if ( '' === $snippets_plugin_file ) {
    $snippets_plugin_file = $root . '/core-blueprint-snippets.php';
}
if ( ! is_file( $snippets_plugin_file ) ) {
    throw new RuntimeException( 'CB_SNIPPETS_PLUGIN_FILE must point to the installed Core Blueprint Snippets entrypoint.' );
}

require_once $root . '/vendor/autoload.php';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $root . '/vendor/yoast/phpunit-polyfills' );
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $root . '/tests/wp-tests-config.php' );
if ( false === getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) ) {
    putenv( 'WP_PHPUNIT__TABLE_PREFIX=cbsnippets_' );
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function () use ( $base_plugin_file, $snippets_plugin_file ): void {
        require_once $base_plugin_file;
        require_once $snippets_plugin_file;

        add_action(
            'plugins_loaded',
            static function (): void {
                \CoreBlueprint\Core\Core::activate();
            },
            2
        );
    }
);

require_once $tests_dir . '/includes/bootstrap.php';
