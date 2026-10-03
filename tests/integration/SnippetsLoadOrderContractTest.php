<?php
declare(strict_types=1);

final class CB_Snippets_Load_Order_Contract_Test extends WP_UnitTestCase {

	public function test_wordpress_runs_later_priority_callback_added_during_current_hook(): void {
		$hook   = 'cb_snippets_plugins_loaded_semantics_fixture';
		$events = [];

		add_action(
			$hook,
			static function () use ( &$events, $hook ): void {
				$events[] = 'bootstrap';
				add_action(
					$hook,
					static function () use ( &$events ): void {
						$events[] = 'snippet';
					},
					10
				);
			},
			0
		);

		do_action( $hook );

		self::assertSame( [ 'bootstrap', 'snippet' ], $events );
	}

	public function test_snippets_boots_before_minimum_runtime_priority(): void {
		$plugin  = (string) file_get_contents( CB_SNIPPETS_DIR . 'core-blueprint-snippets.php' );
		$runtime = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Runtime.php' );

		self::assertStringContainsString( "Bootstrap::class, 'boot' ], 0", $plugin );
		self::assertStringContainsString( 'max( 1, min( 999,', $runtime );
	}
}
