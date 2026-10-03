<?php
declare(strict_types=1);

namespace CoreBlueprint\Snippets;

use CoreBlueprint\Core\Modules\ModuleStateInterface;

defined( 'ABSPATH' ) || exit;

/** Base module-contract adapter; executable runtime state remains extension-owned. */
final class ModuleState implements ModuleStateInterface {
	public static function is_enabled(): bool {
		return State::is_enabled();
	}

	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		State::set_enabled( $enabled, $actor );
	}
}
