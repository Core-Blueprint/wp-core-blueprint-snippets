<?php
declare(strict_types=1);

namespace CoreBlueprint\Snippets;

use CoreBlueprint\Core\Admin\PageRegistry;
use CoreBlueprint\Core\ExtensionRegistry;
use CoreBlueprint\Core\RequestContext;
use CoreBlueprint\Snippets\Admin\Actions;
use CoreBlueprint\Snippets\Admin\Assets;
use CoreBlueprint\Snippets\Admin\Page;
use CoreBlueprint\Snippets\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	private static bool $booted = false;


	/**
	 * WordPress plugin activation is the single public Snippets lifecycle.
	 *
	 * Individual snippets retain their own enabled state. The internal subsystem
	 * flag exists only as a fail-closed runtime guard and is synchronized here.
	 */
	public static function activate(): void {
		if ( State::is_enabled() ) {
			if ( ! Repository::rebuild_index() ) {
				throw new \RuntimeException( 'Core Blueprint Snippets runtime index could not be rebuilt during activation.' );
			}
			return;
		}

		State::set_enabled( true, 'plugin:activation' );
	}

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if (
			! defined( 'CB_CORE_VERSION' )
			|| ! defined( 'CB_CORE_API_VERSION' )
			|| version_compare( (string) CB_CORE_API_VERSION, '1.2', '<' )
			|| ! class_exists( '\\CoreBlueprint\\Core\\ExtensionRegistry' )
		) {
			self::dependency_notice();
			return;
		}

		// A pre-extraction Base may still own the embedded runtime. Never run both.
		if ( class_exists( '\\CoreBlueprint\\Core\\Snippets\\Bootstrap' ) ) {
			self::legacy_base_notice();
			return;
		}

		// Runtime registration stays synchronous so enabled PHP snippets may
		// intentionally target plugins_loaded, matching the embedded Base runtime.
		Runtime::boot();
		SettingsPage::init();

		add_action( 'core_blueprint_register_extensions', [ self::class, 'register_extension' ] );
		add_filter( 'core_blueprint_module_status_definitions', [ self::class, 'register_status_definition' ] );
		add_action( 'core_blueprint_register_pages', [ self::class, 'register_page' ] );
		add_action( 'core_blueprint_hud_register_items', [ self::class, 'register_hud_item' ] );
		add_action( 'core_blueprint_hud_register_items', [ self::class, 'register_hud_quick_action' ] );
		add_action( 'init', [ self::class, 'load_textdomain' ], 0 );
		add_action( 'init', [ self::class, 'register_capability_filter' ], 1 );

		if ( RequestContext::is_admin_post() ) {
			Actions::boot();
		}
		if ( RequestContext::is_admin_screen() ) {
			add_action( 'admin_notices', [ self::class, 'conflict_notice' ] );
			add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
		}
	}

	public static function register_extension(): void {
		ExtensionRegistry::register( [
			'id'            => 'core-blueprint-snippets',
			'plugin_file'   => CB_SNIPPETS_BASENAME,
			'requires_api'  => '1.2',
			'requires_base' => '1.0.0-rc1',
			'menu_url'      => admin_url( 'admin.php?page=' . Page::SLUG ),
			'status_id'     => 'snippets',
		] );
	}

	public static function register_status_definition( array $definitions ): array {
		$definitions['snippets'] = [
			'provider' => [ Status::class, 'contribute' ],
			'label'    => __( 'Snippets', 'core-blueprint-snippets' ),
			'url'      => admin_url( 'admin.php?page=' . Page::SLUG ),
		];
		return $definitions;
	}

	public static function register_page(): void {
		PageRegistry::register(
			new Page(),
			[
				'foundations' => [ 'modal' ],
				'components'  => [ 'actions', 'buttons', 'fields', 'form-controls', 'nav-tabs', 'notices', 'panels', 'state-badges', 'status' ],
			]
		);
	}

	public static function enqueue_admin_assets( string $hook ): void {
		$view = isset( $_GET['view'] ) && is_scalar( $_GET['view'] )
			? sanitize_key( wp_unslash( (string) $_GET['view'] ) )
			: 'list';
		Assets::enqueue( $hook, 'edit' === $view );
	}

	public static function conflict_notice(): void {
		if ( ! State::is_enabled() || ! current_user_can( Authorization::MANAGE_CAPABILITY ) ) {
			return;
		}
		$conflicts = ConflictDetector::active();
		if ( empty( $conflicts ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Another snippets runtime is active.', 'core-blueprint-snippets' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of active snippet plugins */
					__( '%s may execute code alongside Core Blueprint Snippets. Migrate snippets as disabled copies, review them, then disable the old runtime to avoid duplicate execution.', 'core-blueprint-snippets' ),
					implode( ', ', $conflicts )
				)
			),
			esc_url( admin_url( 'admin.php?page=' . Page::SLUG . '&tab=import-export' ) ),
			esc_html__( 'Review migration', 'core-blueprint-snippets' )
		);
	}

	public static function register_hud_item( string $registry ): void {
		if ( ! current_user_can( Authorization::MANAGE_CAPABILITY ) || ! class_exists( $registry ) ) {
			return;
		}
		$registry::add_item( [
			'id'         => 'cb-hud-cb-snippets',
			'label'      => __( 'Snippets', 'core-blueprint-snippets' ),
			'section'    => 'cb-core',
			'url'        => admin_url( 'admin.php?page=' . Page::SLUG ),
			'order'      => 24,
			'capability' => Authorization::MANAGE_CAPABILITY,
			'icon'       => 'editor-code',
			'module'     => 'snippets',
			'status'     => 'snippets',
		] );
	}

	public static function register_hud_quick_action( string $registry ): void {
		if ( ! State::is_enabled() || ! Authorization::can_mutate() || ! class_exists( $registry ) ) {
			return;
		}
		$registry::add_item( [
			'id'         => 'cb-hud-quick-add-snippet',
			'label'      => __( 'Add snippet', 'core-blueprint-snippets' ),
			'section'    => 'quick-actions',
			'url'        => admin_url( 'admin.php?page=' . Page::SLUG . '&tab=snippets&view=edit' ),
			'order'      => 12,
			'capability' => Authorization::MANAGE_CAPABILITY,
			'icon'       => 'plus-alt2',
			'module'     => 'snippets',
		] );
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'core-blueprint-snippets',
			false,
			dirname( CB_SNIPPETS_BASENAME ) . '/languages'
		);
	}

	public static function register_capability_filter(): void {
		add_filter( 'core_blueprint_capability_catalog', [ self::class, 'register_capability' ] );
	}

	private static function dependency_notice(): void {
		add_action( 'admin_notices', static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Core Blueprint Snippets requires Core Blueprint Base with Distribution API 1.2 or newer.', 'core-blueprint-snippets' );
			echo '</p></div>';
		} );
	}

	private static function legacy_base_notice(): void {
		add_action( 'admin_notices', static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'Core Blueprint Snippets is installed and waiting for the Core Blueprint Base update that removes the embedded Snippets runtime.', 'core-blueprint-snippets' );
			echo '</p></div>';
		} );
	}

	public static function register_capability( array $catalog ): array {
		$catalog[ Authorization::MANAGE_CAPABILITY ] = [
			'label'       => __( 'Manage code snippets', 'core-blueprint-snippets' ),
			'group'       => __( 'Core Blueprint', 'core-blueprint-snippets' ),
			'source'      => 'Core Blueprint Snippets',
			'description' => __( 'Create, edit, import, enable and execute managed PHP, CSS, JavaScript and HTML snippets.', 'core-blueprint-snippets' ),
		];
		return $catalog;
	}
}
