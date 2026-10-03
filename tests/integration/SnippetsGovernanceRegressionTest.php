<?php
declare(strict_types=1);

use CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry;
use CoreBlueprint\Snippets\Admin\Actions;
use CoreBlueprint\Snippets\State;

final class CB_Snippets_Test_Termination extends RuntimeException {
	/** @var mixed */
	public $wp_message;

	/** @var mixed */
	public $wp_title;

	/** @var mixed */
	public $wp_args;

	public function __construct( $message, $title, $args ) {
		parent::__construct( '__CB_SNIPPETS_TEST_TERMINATION__' );
		$this->wp_message = $message;
		$this->wp_title   = $title;
		$this->wp_args    = $args;
	}
}

final class CB_Snippets_Governance_Regression_Test extends WP_UnitTestCase {

	private array $user_ids = [];

	public function tear_down(): void {
		$_GET = [];
		$_POST = [];
		$_REQUEST = [];
		wp_set_current_user( 0 );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = [];

		parent::tear_down();
	}

	public function test_disabled_mutations_are_blocked_but_export_remains_registered(): void {
		$this->approved_admin();

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
				has_action( 'admin_post_cb_core_snippets_export', [ Actions::class, 'export' ] ),
				'Read-only export must remain registered while Snippets is disabled.'
			);
		} finally {
			$_POST = [];
			$_REQUEST = [];
			if ( State::is_enabled() !== $initial ) {
				State::set_enabled( $initial, 'test:snippets-restore' );
			}
		}
	}

	public function test_restore_acknowledgement_precedes_preserved_id_import_mutation(): void {
		$page = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Admin/Page.php' );
		$actions = (string) file_get_contents( CB_SNIPPETS_DIR . 'src/Admin/Actions.php' );
		$script = (string) file_get_contents( CB_SNIPPETS_DIR . 'assets/js/features/snippets.js' );

		self::assertStringContainsString( 'cb-snippets-restore-acknowledgement-template', $page );
		self::assertStringContainsString( 'data-cb-snippets-preserve-ids', $page );
		self::assertStringContainsString( "Exporter::FILE_TYPE === (string) ( \$document['file_type'] ?? '' )", $actions );
		self::assertStringContainsString( "'1' === sanitize_text_field( wp_unslash( (string) \$_POST['overwrite'] ) )", $actions );
		self::assertStringContainsString( 'MutationAcknowledgement::require_confirmed(', $actions );
		self::assertStringContainsString( "AuditLog::log( 'snippets.restore.acknowledged'", $actions );
		self::assertStringContainsString( 'restoreAcknowledgementTemplate.content.cloneNode( true )', $script );

		$audit = strpos( $actions, "AuditLog::log( 'snippets.restore.acknowledged'" );
		$mutation = strpos( $actions, 'Importer::import_json( $json, $preserve_ids )' );
		self::assertNotFalse( $audit );
		self::assertNotFalse( $mutation );
		self::assertLessThan( $mutation, $audit );
	}

	private function approved_admin(): WP_User {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->user_ids[] = $user_id;

		$user = get_userdata( $user_id );
		self::assertInstanceOf( WP_User::class, $user );
		$user->add_cap( 'cb_manage_snippets' );

		$user = get_userdata( $user_id );
		self::assertInstanceOf( WP_User::class, $user );
		self::assertTrue( PrivilegedAccessRegistry::approve( $user, $user_id, 'snippets_governance_fixture' ) );

		wp_set_current_user( $user_id );
		self::assertTrue( current_user_can( 'cb_manage_snippets' ) );

		return wp_get_current_user();
	}

	/** @return array{termination:CB_Snippets_Test_Termination,output:string} */
	private function capture_termination( callable $callback ): array {
		$die_handler = static function ( $message = '', $title = '', $args = [] ): void {
			throw new CB_Snippets_Test_Termination( $message, $title, $args );
		};
		$handler_filter = static fn() => $die_handler;
		$ajax_filter = static fn() => true;

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
