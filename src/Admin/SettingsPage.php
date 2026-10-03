<?php
declare(strict_types=1);

namespace CoreBlueprint\Snippets\Admin;

use CoreBlueprint\Core\Admin\SettingsRegistry;
use CoreBlueprint\Snippets\Authorization;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'core_blueprint_register_settings', [ self::class, 'register' ] );
	}

	public static function register(): void {
		SettingsRegistry::register(
			'core-blueprint-snippets',
			[
				'label'        => __( 'Snippets', 'core-blueprint-snippets' ),
				'description'  => __( 'Run small PHP, CSS, JavaScript and HTML customizations without a separate snippets plugin. Runtime execution is file-based and does not query the database for snippet code.', 'core-blueprint-snippets' ),
				'group'        => SettingsRegistry::GROUP_INFRASTRUCTURE,
				'capability'   => Authorization::MANAGE_CAPABILITY,
				'renderer'     => [ self::class, 'render' ],
				'requirements' => [
					'components' => [ 'buttons', 'panels' ],
				],
			]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Authorization::MANAGE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'core-blueprint-snippets' ) );
		}
		?>
		<section class="cb-core-panel">
			<h2><?php esc_html_e( 'Snippets', 'core-blueprint-snippets' ); ?></h2>
			<p><?php esc_html_e( 'Run small PHP, CSS, JavaScript and HTML customizations without a separate snippets plugin. Runtime execution is file-based and does not query the database for snippet code.', 'core-blueprint-snippets' ); ?></p>
			<p>
				<a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Page::SLUG ) ); ?>"><?php esc_html_e( 'Snippets', 'core-blueprint-snippets' ); ?></a>
			</p>
		</section>
		<?php
	}

	private function __construct() {}
}
