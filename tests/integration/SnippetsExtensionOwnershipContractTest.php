<?php
declare(strict_types=1);

use CoreBlueprint\Snippets\Admin\Page;
use CoreBlueprint\Snippets\Authorization;
use CoreBlueprint\Snippets\Bootstrap;
use CoreBlueprint\Snippets\State;
use CoreBlueprint\Snippets\Status;

final class CB_Snippets_Extension_Ownership_Contract_Test extends WP_UnitTestCase {

	public function test_plugin_activation_is_the_single_public_runtime_lifecycle(): void {
		$initial = State::is_enabled();

		try {
			if ( $initial ) {
				State::set_enabled( false, 'test:single-lifecycle-precondition' );
			}
			self::assertFalse( State::is_enabled() );

			Bootstrap::activate();

			self::assertTrue( State::is_enabled() );
			self::assertFileExists( \CoreBlueprint\Snippets\Paths::runtime_index() );

			$bootstrap = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Bootstrap.php' );
			self::assertStringNotContainsString( 'core_blueprint_module_activation_definitions', $bootstrap );
			self::assertStringNotContainsString( 'register_activation_definition', $bootstrap );
		} finally {
			if ( State::is_enabled() !== $initial ) {
				State::set_enabled( $initial, 'test:single-lifecycle-restore' );
			}
		}
	}

	public function test_module_status_definition_is_extension_owned(): void {
		$definitions = Bootstrap::register_status_definition( [] );

		self::assertArrayHasKey( 'snippets', $definitions );
		self::assertSame( [ Status::class, 'contribute' ], $definitions['snippets']['provider'] );
		self::assertSame( 'Snippets', $definitions['snippets']['label'] );
	}

	public function test_admin_page_uses_public_extension_contract(): void {
		$page = new Page();

		self::assertSame( 'core-blueprint-snippets', $page->slug() );
		self::assertSame( Authorization::MANAGE_CAPABILITY, $page->capability() );
		self::assertGreaterThanOrEqual( 100, (int) $page->position() );

		$bootstrap = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Bootstrap.php' );
		self::assertStringContainsString( 'PageRegistry::register(', $bootstrap );
		self::assertStringNotContainsString( 'PageRegistry::register_base(', $bootstrap );
		self::assertMatchesRegularExpression(
			'/public static function register_page\(\): void \{\s*PageRegistry::register\(/',
			$bootstrap
		);
	}

	public function test_extension_owns_assets_and_staged_upgrade_guard(): void {
		$assets    = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Admin/Assets.php' );
		$plugin    = (string) file_get_contents( CB_SNIPPETS_DIR . 'core-blueprint-snippets.php' );
		$bootstrap = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Bootstrap.php' );

		self::assertStringContainsString( 'CB_SNIPPETS_URL', $assets );
		self::assertStringContainsString( 'CB_SNIPPETS_VERSION', $assets );
		self::assertStringNotContainsString( 'CB_CORE_URL', $assets );
		self::assertStringContainsString( 'Requires Plugins:  core-blueprint', $plugin );
		self::assertStringContainsString( "\\\\CoreBlueprint\\\\Core\\\\Snippets\\\\Bootstrap", $bootstrap );
		self::assertStringContainsString( "Bootstrap::class, 'boot' ], 0", $plugin );
		self::assertStringContainsString( "Bootstrap::class, 'activate' ]", $plugin );
	}
}
