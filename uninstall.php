<?php
/**
 * Uninstall handler for Core Blueprint Snippets.
 *
 * Operator-authored snippet source and metadata survive uninstall for exit
 * freedom. Only plugin settings, transient UI state and generated runtime files
 * are removed.
 *
 * @package Core_Blueprint_Snippets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cb_core_snippets_settings' );

global $wpdb;
$prefixes = [
	'cb_core_snippets_result_',
	'cb_core_snippets_draft_',
];

foreach ( $prefixes as $prefix ) {
	$like = $wpdb->esc_like( $prefix ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			'_transient_' . $like,
			'_transient_timeout_' . $like
		)
	);
}

$storage = untrailingslashit( trailingslashit( WP_CONTENT_DIR ) . 'cb-snippets' );
foreach ( [ $storage . '/runtime-index.php', $storage . '/.lock' ] as $runtime_file ) {
	if ( is_file( $runtime_file ) ) {
		wp_delete_file( $runtime_file );
	}
}

// Preserved deliberately: registry.php, code/* and direct-access guard files.
