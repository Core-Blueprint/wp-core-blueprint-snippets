<?php
declare(strict_types=1);

use CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry;
use CoreBlueprint\Snippets\Admin\Actions;
use CoreBlueprint\Snippets\State;

final class CB_Snippets_Test_Termination extends RuntimeException {
	public mixed $wp_message;
	public mixed $wp_title;
	public mixed $wp_args;

	public function __construct( mixed $message, mixed $title, mixed $args ) {
		parent::__construct( '__CB_SNIPPETS_TEST_TERMINATION__' );
		$this->wp_message = $message;
		$this->wp_title   = $title;
		$this->wp_args    = $args;
	}
}

final class CB_Snippets_Disabled_Surface_Contract_Test extends WP_UnitTestCase {

	public function test_mutations_are_blocked_while_disabled_but_export_remains_registered(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user = get_userdata( $user_id );
		self::assertInstanceOf( WP_User::class, $user );
		$user->add_cap( 'cb_manage_snippets' );
		$user = get_userdata( $user_id );
		self::assertInstanceOf( WP_User::class, $user );
		self::assertTrue( PrivilegedAccessRegistry::approve( $user, 0, 'snippets-disabled-surface' ) );
		wp_set_current_user( $user_id );
		self::assertTrue( current_user_can( 'cb_manage_snippets' ) );

		$initial = State::is_enabled();
		$mutations = [
			'save'      => [ 'cb_core_snippets_save', [ Actions::class, 'save' ] ],
			'toggle'    => [ 'cb_core_snippets_toggle', [ Actions::class, 'toggle' ] ],
			'duplicate' => [ 'cb_core_snippets_duplicate', [ Actions::class, 'duplicate' ] ],
			'delete'    => [ 'cb_core_snippets_delete', [ Actions::class, 'delete' ] ],
			'import'    => [ 'cb_core_snippets_import', [ Actions::class, 'import' ] ],
		];

		try {
			if ( ! $initial ) {
				State::set_enabled( true, 'test:snippets-precondition' );
			}
			State::set_enabled( false, 'test:snippets-disabled' );

			foreach ( $mutations as $name => [ $nonce_action, $callback ] ) {
				$result = $this->capture_termination(
					static function () use ( $nonce_action, $callback ): void {
						$_POST = [ '_wpnonce' => wp_create_nonce( $nonce_action ) ];
						$_REQUEST = $_POST;
						call_user_func( $callback );
					}
				);

				self::assertSame( 409, $this->response_code( $result['termination'] ), $name );
				self::assertStringContainsString(
					'Snippets is disabled',
					$this->termination_message( $result['termination'] ),
					$name
				);
			}

			Actions::boot();
			self::assertNotFalse(
				has_action( 'admin_post_cb_core_snippets_export', [ Actions::class, 'export' ] )
			);
		} finally {
			$_POST = [];
			$_REQUEST = [];
			wp_set_current_user( 0 );
			if ( State::is_enabled() !== $initial ) {
				State::set_enabled( $initial, 'test:snippets-restore' );
			}
		}
	}

	/** @return array{termination:CB_Snippets_Test_Termination,output:string} */
	private function capture_termination( callable $callback ): array {
		$die_handler = static function ( $message = '', $title = '', $args = [] ): void {
			throw new CB_Snippets_Test_Termination( $message, $title, $args );
		};
		$handler_filter = static fn() => $die_handler;
		$ajax_filter    = static fn() => true;

		add_filter( 'wp_die_handler', $handler_filter, PHP_INT_MAX );
		add_filter( 'wp_die_ajax_handler', $handler_filter, PHP_INT_MAX );
		add_filter( 'wp_doing_ajax', $ajax_filter, PHP_INT_MAX );

		$level = ob_get_level();
		ob_start();
		try {
			$callback();
			self::fail( 'Expected WordPress handler to terminate the request.' );
		} catch ( CB_Snippets_Test_Termination $termination ) {
			$output = (string) ob_get_clean();
			return [ 'termination' => $termination, 'output' => $output ];
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_die_handler', $handler_filter, PHP_INT_MAX );
			remove_filter( 'wp_die_ajax_handler', $handler_filter, PHP_INT_MAX );
			remove_filter( 'wp_doing_ajax', $ajax_filter, PHP_INT_MAX );
		}
	}

	private function response_code( CB_Snippets_Test_Termination $termination ): int {
		$args = is_array( $termination->wp_args ) ? $termination->wp_args : [];
		return (int) ( $args['response'] ?? 0 );
	}

	private function termination_message( CB_Snippets_Test_Termination $termination ): string {
		return is_scalar( $termination->wp_message ) ? (string) $termination->wp_message : '';
	}
}
