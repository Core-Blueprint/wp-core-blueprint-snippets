<?php
declare(strict_types=1);

namespace CoreBlueprint\Snippets;

defined( 'ABSPATH' ) || exit;

/** Optional bridge into Base audit logging without coupling the runtime to load order. */
final class Audit {
	public static function log( string $event, string $level, array $context = [] ): void {
		$logger = '\\CoreBlueprint\\Core\\Log\\AuditLog';
		if ( class_exists( $logger ) ) {
			$logger::log( $event, $level, $context );
		}
	}
}
