<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$builder = $root . '/tools/build-release';

function fail_release_tooling( string $message ): never {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

if ( ! is_file( $builder ) ) {
	fail_release_tooling( 'release builder missing' );
}

$source = (string) file_get_contents( $builder );
$required = [
	'CB_BASE_SOURCE_DIR',
	'CB_BASE_PLUGIN_FILE',
	'CB_SNIPPETS_PLUGIN_FILE',
	'WP_TESTS_DIR',
	'WP_DB_NAME',
	'vendor/bin/phpunit',
	'--testsuite',
	'integration',
	'runtime_hashes',
	'tree_hashes',
	'verify_archive',
	'archive.testzip()',
	'TemporaryDirectory',
	'.zip.sha256',
	'version mismatch',
	'release tree may not contain symlinks',
];

foreach ( $required as $needle ) {
	if ( ! str_contains( $source, $needle ) ) {
		fail_release_tooling( "release builder missing Golden gate: {$needle}" );
	}
}

if ( str_contains( $source, 'if command -v node' ) ) {
	fail_release_tooling( 'release build may not silently skip JavaScript validation' );
}

echo "Snippets release tooling regression: PASS\n";
