<?php
declare(strict_types=1);

namespace CoreBlueprint\Core\Snippets\Admin;

use CoreBlueprint\Core\Admin\MutationAcknowledgement;
use CoreBlueprint\Core\Admin\Page as PageContract;
use CoreBlueprint\Core\Admin\TabNav;
use CoreBlueprint\Core\Snippets\Authorization;
use CoreBlueprint\Core\Snippets\ConflictDetector;
use CoreBlueprint\Core\Snippets\Repository;
use CoreBlueprint\Core\Snippets\SafeMode;
use CoreBlueprint\Core\Snippets\Schema;
use CoreBlueprint\Core\Snippets\State;
use CoreBlueprint\Core\UI\Status as StatusUi;

defined( 'ABSPATH' ) || exit;

final class Page implements PageContract {
	public const SLUG = 'core-blueprint-snippets';

	public function slug(): string { return self::SLUG; }
	public function title(): string { return __( 'Snippets', 'core-blueprint-snippets' ); }
	public function position(): ?int { return 120; }
	public function menu_title(): string { return $this->title(); }
	public function capability(): string { return 'cb_manage_snippets'; }

	public function render(): void {
		$this->guard();
		$tabs = [
			'snippets'      => __( 'Snippets', 'core-blueprint-snippets' ),
			'settings'      => __( 'Settings', 'core-blueprint-snippets' ),
			'import-export' => __( 'Import / Export', 'core-blueprint-snippets' ),
		];
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'snippets'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $tabs[ $requested ] ) ? $requested : 'snippets';

		ob_start();
		if ( 'settings' === $tab ) {
			$this->render_settings();
		} elseif ( 'import-export' === $tab ) {
			$this->render_import_export();
		} else {
			$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'edit' === $view ) {
				$this->render_editor();
			} else {
				$this->render_list();
			}
		}
		$html = (string) ob_get_clean();
		echo TabNav::inject( $html, self::SLUG, $tab, $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'core-blueprint-snippets' ),
				esc_html__( 'Forbidden', 'core-blueprint-snippets' ),
				[ 'response' => 403 ]
			);
		}
	}

	private function render_header( string $intro ): void {
		$result = Actions::pull_result();
		?>
		<div class="wrap cb-core-wrap cb-snippets-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'Snippets', 'core-blueprint-snippets' ); ?></h1>
			<p class="cb-core-intro"><?php echo esc_html( $intro ); ?></p>
			<?php if ( is_array( $result ) && ! empty( $result['message'] ) ) : ?>
				<div class="notice <?php echo 'error' === ( $result['type'] ?? '' ) ? 'notice-error' : ( 'warning' === ( $result['type'] ?? '' ) ? 'notice-warning' : 'notice-success' ); ?> inline"><p><?php echo esc_html( (string) $result['message'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( SafeMode::is_active() ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Emergency safe mode is active.', 'core-blueprint-snippets' ); ?></strong> <?php esc_html_e( 'All snippets are suppressed by CB_CORE_DISABLE_SNIPPETS, regardless of their saved state.', 'core-blueprint-snippets' ); ?></p></div>
			<?php endif; ?>
			<?php $conflicts = ConflictDetector::active(); ?>
			<?php if ( ! empty( $conflicts ) ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Migration overlap detected.', 'core-blueprint-snippets' ); ?></strong> <?php echo esc_html( sprintf(
					/* translators: %s: comma-separated list of active snippet plugins */
					__( '%s is still active. Core Blueprint imports migrated snippets as disabled copies; review and migrate them before disabling the old runtime.', 'core-blueprint-snippets' ),
					implode( ', ', $conflicts )
				) ); ?></p></div>
			<?php endif; ?>
		<?php
	}

	private function render_list(): void {
		$this->render_header( __( 'Run small PHP, CSS, JavaScript and HTML customizations without a separate snippets plugin. Runtime execution is file-based and does not query the database for snippet code.', 'core-blueprint-snippets' ) );
		$snippets = Repository::all();
		usort( $snippets, static fn( array $a, array $b ): int => strnatcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) ) );
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'snippets', 'view' => 'edit' ], admin_url( 'admin.php' ) );
		?>
		<div class="cb-snippets-toolbar">
			<div class="cb-snippets-toolbar__status">
				<?php echo StatusUi::render( State::is_enabled() ? 'active' : 'idle', State::is_enabled() ? __( 'Runtime enabled', 'core-blueprint-snippets' ) : __( 'Runtime disabled', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( Authorization::can_mutate() ) : ?>
				<a class="button button-primary cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add snippet', 'core-blueprint-snippets' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $snippets ) ) : ?>
			<div class="cb-snippets-empty">
				<h2><?php esc_html_e( 'No snippets yet', 'core-blueprint-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Create a managed snippet or import an existing Core Blueprint / Fluent Snippets export.', 'core-blueprint-snippets' ); ?></p>
			</div>
		<?php else : ?>
			<div class="cb-snippets-table-shell cb-core-scrollbar">
			<table class="widefat striped cb-snippets-table">
				<thead><tr>
					<th><?php esc_html_e( 'Snippet', 'core-blueprint-snippets' ); ?></th>
					<th><?php esc_html_e( 'Type', 'core-blueprint-snippets' ); ?></th>
					<th><?php esc_html_e( 'Location', 'core-blueprint-snippets' ); ?></th>
					<th><?php esc_html_e( 'State', 'core-blueprint-snippets' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'core-blueprint-snippets' ); ?></th>
					<th class="cb-snippets-table__actions"><?php esc_html_e( 'Actions', 'core-blueprint-snippets' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $snippets as $snippet ) :
					$id = (string) $snippet['id'];
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'snippets', 'view' => 'edit', 'snippet' => $id ], admin_url( 'admin.php' ) );
					$locations = Schema::locations_for_type( (string) $snippet['type'] );
					$last_error = is_array( $snippet['last_error'] ?? null ) ? $snippet['last_error'] : null;
				?>
				<tr>
					<td>
						<a class="cb-snippets-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $snippet['title'] ); ?></a>
						<?php if ( ! empty( $snippet['description'] ) ) : ?><div class="cb-snippets-description"><?php echo esc_html( (string) $snippet['description'] ); ?></div><?php endif; ?>
						<?php if ( $last_error ) : ?><div class="cb-snippets-error"><?php echo esc_html( (string) ( $last_error['message'] ?? __( 'Runtime error', 'core-blueprint-snippets' ) ) ); ?></div><?php endif; ?>
					</td>
					<td><code><?php echo esc_html( strtoupper( (string) $snippet['type'] ) ); ?></code></td>
					<td><?php echo esc_html( (string) ( $locations[ (string) $snippet['location'] ] ?? $snippet['location'] ) ); ?></td>
					<td><?php echo StatusUi::render( ! empty( $snippet['enabled'] ) ? 'active' : 'idle', ! empty( $snippet['enabled'] ) ? __( 'Enabled', 'core-blueprint-snippets' ) : __( 'Disabled', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo esc_html( self::format_date( (string) ( $snippet['updated_at'] ?? '' ) ) ); ?></td>
					<td class="cb-snippets-table__actions">
						<a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-snippets' ); ?></a>
						<?php $this->render_row_action( 'cb_core_snippets_toggle', 'cb_core_snippets_toggle', $id, ! empty( $snippet['enabled'] ) ? __( 'Disable', 'core-blueprint-snippets' ) : __( 'Enable', 'core-blueprint-snippets' ) ); ?>
						<?php $this->render_row_action( 'cb_core_snippets_duplicate', 'cb_core_snippets_duplicate', $id, __( 'Duplicate', 'core-blueprint-snippets' ) ); ?>
						<?php $this->render_row_action( 'cb_core_snippets_delete', 'cb_core_snippets_delete', $id, __( 'Delete', 'core-blueprint-snippets' ), true, (string) $snippet['title'] ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
		</div>
		<?php
	}

	private function render_editor(): void {
		$id = isset( $_GET['snippet'] ) ? sanitize_key( wp_unslash( $_GET['snippet'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$snippet = '' !== $id ? Repository::get( $id ) : null;
		if ( '' !== $id && null === $snippet ) {
			wp_die( esc_html__( 'Snippet not found.', 'core-blueprint-snippets' ) );
		}
		$snippet = array_replace( Schema::default_meta(), is_array( $snippet ) ? $snippet : [] );
		$code = '' !== $id ? Repository::code( $id ) : '';

		$type = sanitize_key( (string) ( $snippet['type'] ?? 'php' ) );
		if ( ! in_array( $type, Schema::TYPES, true ) ) {
			$type = 'php';
		}
		$snippet['type'] = $type;
		if ( ! Schema::valid_location( $type, (string) ( $snippet['location'] ?? '' ) ) ) {
			$snippet['location'] = Schema::default_location( $type );
		}
		$conditions = self::condition_values( (array) $snippet['conditions'] );
		$roles = wp_roles()->roles;
		$this->render_header( '' !== $id ? __( 'Edit managed snippet code and execution rules.', 'core-blueprint-snippets' ) : __( 'Create a managed snippet. New snippets are disabled unless you explicitly enable them.', 'core-blueprint-snippets' ) );
		?>
		<?php if ( ! Authorization::can_mutate() ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'You do not have permission to manage code snippets.', 'core-blueprint-snippets' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-snippets-editor cb-core-form-scope">
			<input type="hidden" name="action" value="cb_core_snippets_save" />
			<input type="hidden" name="snippet_id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( 'cb_core_snippets_save' ); ?>

			<?php if ( is_array( $snippet['last_error'] ?? null ) ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'This snippet was automatically disabled after a runtime error.', 'core-blueprint-snippets' ); ?></strong> <?php echo esc_html( (string) ( $snippet['last_error']['message'] ?? '' ) ); ?></p></div>
			<?php endif; ?>

			<section class="cb-snippets-section">
				<div class="cb-snippets-grid cb-snippets-grid--two">
					<div class="cb-core-field">
						<label class="cb-core-field__label" for="cb-snippet-title"><?php esc_html_e( 'Title', 'core-blueprint-snippets' ); ?></label>
						<input id="cb-snippet-title" name="title" type="text" value="<?php echo esc_attr( (string) $snippet['title'] ); ?>" required />
					</div>
					<div class="cb-core-field">
						<label class="cb-core-field__label" for="cb-snippet-tags"><?php esc_html_e( 'Tags', 'core-blueprint-snippets' ); ?></label>
						<input id="cb-snippet-tags" name="tags" type="text" value="<?php echo esc_attr( implode( ', ', (array) $snippet['tags'] ) ); ?>" placeholder="maintenance, frontend" />
					</div>
				</div>
				<div class="cb-core-field">
					<label class="cb-core-field__label" for="cb-snippet-description"><?php esc_html_e( 'Description', 'core-blueprint-snippets' ); ?></label>
					<textarea id="cb-snippet-description" name="description" rows="2"><?php echo esc_textarea( (string) $snippet['description'] ); ?></textarea>
				</div>
			</section>

			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Execution', 'core-blueprint-snippets' ); ?></h2>
				<div class="cb-snippets-grid cb-snippets-grid--three">
					<div class="cb-core-field">
						<label class="cb-core-field__label" for="cb-snippet-type"><?php esc_html_e( 'Type', 'core-blueprint-snippets' ); ?></label>
						<select id="cb-snippet-type" name="type">
							<?php foreach ( Schema::TYPES as $candidate ) : ?><option value="<?php echo esc_attr( $candidate ); ?>" <?php selected( $type, $candidate ); ?>><?php echo esc_html( strtoupper( $candidate ) ); ?></option><?php endforeach; ?>
						</select>
					</div>
					<div class="cb-core-field">
						<label class="cb-core-field__label" for="cb-snippet-location"><?php esc_html_e( 'Location', 'core-blueprint-snippets' ); ?></label>
						<select id="cb-snippet-location" name="location">
							<?php foreach ( Schema::locations_for_type( $type ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $snippet['location'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
						</select>
					</div>
					<div class="cb-core-field">
						<label class="cb-core-field__label" for="cb-snippet-priority"><?php esc_html_e( 'Priority', 'core-blueprint-snippets' ); ?></label>
						<input id="cb-snippet-priority" name="priority" type="number" min="1" max="999" value="<?php echo esc_attr( (string) $snippet['priority'] ); ?>" />
					</div>
				</div>
				<div class="cb-core-field cb-snippets-shortcode-field" <?php echo 'shortcode' === (string) $snippet['location'] ? '' : 'hidden'; ?>>
					<label class="cb-core-field__label" for="cb-snippet-shortcode"><?php esc_html_e( 'Shortcode name', 'core-blueprint-snippets' ); ?></label>
					<input id="cb-snippet-shortcode" name="shortcode" type="text" value="<?php echo esc_attr( (string) $snippet['shortcode'] ); ?>" placeholder="my_snippet" />
					<p class="description"><?php esc_html_e( 'Leave blank on first save to generate a safe Core Blueprint shortcode automatically.', 'core-blueprint-snippets' ); ?></p>
				</div>
				<label class="cb-core-check-row"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $snippet['enabled'] ) ); ?> /><span class="cb-core-check-row__body"><strong><?php esc_html_e( 'Enable snippet', 'core-blueprint-snippets' ); ?></strong><small><?php esc_html_e( 'Enabled snippets enter the generated runtime index. The module master switch can still suppress every snippet globally.', 'core-blueprint-snippets' ); ?></small></span></label>
			</section>

			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Conditions', 'core-blueprint-snippets' ); ?></h2>
				<p class="description"><?php esc_html_e( 'All configured rules must match. Leave fields at Any / blank to run without that restriction.', 'core-blueprint-snippets' ); ?></p>
				<div class="cb-snippets-grid cb-snippets-grid--two">
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippet-condition-scope"><?php esc_html_e( 'Request scope', 'core-blueprint-snippets' ); ?></label><select id="cb-snippet-condition-scope" name="condition_scope"><option value="any"><?php esc_html_e( 'Any', 'core-blueprint-snippets' ); ?></option><option value="frontend" <?php selected( $conditions['scope'], 'frontend' ); ?>><?php esc_html_e( 'Frontend only', 'core-blueprint-snippets' ); ?></option><option value="admin" <?php selected( $conditions['scope'], 'admin' ); ?>><?php esc_html_e( 'Admin only', 'core-blueprint-snippets' ); ?></option></select></div>
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippet-condition-login"><?php esc_html_e( 'Authentication', 'core-blueprint-snippets' ); ?></label><select id="cb-snippet-condition-login" name="condition_login"><option value="any"><?php esc_html_e( 'Any', 'core-blueprint-snippets' ); ?></option><option value="logged_in" <?php selected( $conditions['login'], 'logged_in' ); ?>><?php esc_html_e( 'Logged in', 'core-blueprint-snippets' ); ?></option><option value="logged_out" <?php selected( $conditions['login'], 'logged_out' ); ?>><?php esc_html_e( 'Logged out', 'core-blueprint-snippets' ); ?></option></select></div>
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippet-condition-role"><?php esc_html_e( 'User role', 'core-blueprint-snippets' ); ?></label><select id="cb-snippet-condition-role" name="condition_role"><option value=""><?php esc_html_e( 'Any', 'core-blueprint-snippets' ); ?></option><?php foreach ( $roles as $role_slug => $role_data ) : ?><option value="<?php echo esc_attr( (string) $role_slug ); ?>" <?php selected( $conditions['role'], (string) $role_slug ); ?>><?php echo esc_html( translate_user_role( (string) ( $role_data['name'] ?? $role_slug ) ) ); ?></option><?php endforeach; ?></select></div>
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippet-condition-post-type"><?php esc_html_e( 'Post type', 'core-blueprint-snippets' ); ?></label><input id="cb-snippet-condition-post-type" name="condition_post_type" type="text" value="<?php echo esc_attr( $conditions['post_type'] ); ?>" placeholder="post" /></div>
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippet-condition-path"><?php esc_html_e( 'URL path contains', 'core-blueprint-snippets' ); ?></label><input id="cb-snippet-condition-path" name="condition_path" type="text" value="<?php echo esc_attr( $conditions['path'] ); ?>" placeholder="/academy/" /></div>
				</div>
			</section>

			<section class="cb-snippets-section cb-snippets-code-section">
				<div class="cb-snippets-code-heading"><h2><?php esc_html_e( 'Code', 'core-blueprint-snippets' ); ?></h2><span class="cb-snippets-code-note"><?php esc_html_e( 'PHP snippets must not include opening or closing PHP tags.', 'core-blueprint-snippets' ); ?></span></div>
				<textarea id="cb-snippet-code" name="code" rows="24" spellcheck="false"><?php echo esc_textarea( $code ); ?></textarea>
			</section>

			<div class="cb-core-actions">
				<?php if ( Authorization::can_mutate() ) : ?>
					<button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save snippet', 'core-blueprint-snippets' ); ?></button>
				<?php endif; ?>
				<a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=snippets' ) ); ?>"><?php esc_html_e( 'Back to snippets', 'core-blueprint-snippets' ); ?></a>
			</div>
		</form>
		</div>
		<?php
	}

	private function render_settings(): void {
		$this->render_header( __( 'Inspect the managed snippets runtime and keep the emergency recovery path available. Module activation is managed from the Core Blueprint Dashboard.', 'core-blueprint-snippets' ) );
		$health = Repository::health();
		?>
		<div class="cb-snippets-settings cb-core-form-scope">
			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Runtime health', 'core-blueprint-snippets' ); ?></h2>
				<div class="cb-snippets-health">
					<div><span><?php esc_html_e( 'Storage', 'core-blueprint-snippets' ); ?></span><?php echo StatusUi::render( $health['storage'] ? 'active' : 'error', $health['storage'] ? __( 'Writable', 'core-blueprint-snippets' ) : __( 'Unavailable', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div><span><?php esc_html_e( 'Registry', 'core-blueprint-snippets' ); ?></span><?php echo StatusUi::render( $health['registry'] ? 'active' : 'error', $health['registry'] ? __( 'Healthy', 'core-blueprint-snippets' ) : __( 'Invalid', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div><span><?php esc_html_e( 'Runtime index', 'core-blueprint-snippets' ); ?></span><?php echo StatusUi::render( $health['index'] ? 'active' : 'error', $health['index'] ? __( 'Healthy', 'core-blueprint-snippets' ) : __( 'Invalid', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div><span><?php esc_html_e( 'Code files', 'core-blueprint-snippets' ); ?></span><?php echo StatusUi::render( $health['code'] ? 'active' : 'error', $health['code'] ? __( 'Verified', 'core-blueprint-snippets' ) : __( 'Changed or missing', 'core-blueprint-snippets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
				<p class="description"><code><?php echo esc_html( \CoreBlueprint\Core\Snippets\Paths::base_dir() ); ?></code></p>
			</section>

			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Emergency kill switch', 'core-blueprint-snippets' ); ?></h2>
				<p><?php esc_html_e( 'If a deployment or unexpected snippet prevents normal administration, add the following line to wp-config.php. It suppresses the runtime before any managed snippet executes:', 'core-blueprint-snippets' ); ?></p>
				<pre class="cb-snippets-code-sample"><code>define( 'CB_CORE_DISABLE_SNIPPETS', true );</code></pre>
			</section>

		</div>
		</div>
		<?php
	}

	private function render_import_export(): void {
		$this->render_header( __( 'Move snippets between Core Blueprint sites or migrate a Fluent Snippets JSON export. Imports are deliberately disabled until reviewed.', 'core-blueprint-snippets' ) );
		?>
		<div class="cb-snippets-transfer-grid">
			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Export', 'core-blueprint-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Export all managed snippets as a portable JSON file. The export contains snippet code and metadata but no site URL, user data or telemetry identifiers.', 'core-blueprint-snippets' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_core_snippets_export" />
					<?php wp_nonce_field( 'cb_core_snippets_export' ); ?>
					<button type="submit" class="button cb-core-button cb-core-button--secondary"><?php esc_html_e( 'Export all snippets', 'core-blueprint-snippets' ); ?></button>
				</form>
			</section>

			<section class="cb-snippets-section">
				<h2><?php esc_html_e( 'Import', 'core-blueprint-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Accepted formats: Core Blueprint Snippets JSON and Fluent Snippets JSON. Imported code never becomes active automatically.', 'core-blueprint-snippets' ); ?></p>
				<?php if ( Authorization::can_mutate() ) : ?>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope">
					<input type="hidden" name="action" value="cb_core_snippets_import" />
					<?php wp_nonce_field( 'cb_core_snippets_import' ); ?>
					<div class="cb-core-field"><label class="cb-core-field__label" for="cb-snippets-file"><?php esc_html_e( 'JSON file', 'core-blueprint-snippets' ); ?></label><input id="cb-snippets-file" name="snippets_file" type="file" accept="application/json,.json" required /></div>
					<label class="cb-core-check-row"><input type="checkbox" name="overwrite" value="1" data-cb-snippets-preserve-ids /><span class="cb-core-check-row__body"><strong><?php esc_html_e( 'Preserve Core Blueprint snippet IDs', 'core-blueprint-snippets' ); ?></strong><small><?php esc_html_e( 'Use only for a controlled restore. Normal migrations should leave this off so imported snippets receive new IDs.', 'core-blueprint-snippets' ); ?></small></span></label>
					<template id="cb-snippets-restore-acknowledgement-template">
						<?php MutationAcknowledgement::render(
							'snippets_restore_acknowledgement',
							'cb-snippets-restore-acknowledgement',
							__( 'I understand that preserving snippet IDs can replace existing managed snippet code and metadata and that I am responsible for having a recent backup or other recovery option available.', 'core-blueprint-snippets' ),
							__( 'Imported snippets remain disabled, but existing snippets with matching IDs can be overwritten.', 'core-blueprint-snippets' )
						); ?>
					</template>
					<div data-cb-snippets-restore-acknowledgement></div>
					<button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Import snippets', 'core-blueprint-snippets' ); ?></button>
				</form>
				<?php else : ?>
					<p><?php esc_html_e( 'You do not have permission to manage code snippets.', 'core-blueprint-snippets' ); ?></p>
				<?php endif; ?>
			</section>
		</div>
		</div>
		<?php
	}

	private function render_row_action( string $action, string $nonce, string $id, string $label, bool $danger = false, string $title = '' ): void {
		if ( ! Authorization::can_mutate() ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-snippets-inline-action" <?php echo $danger ? 'data-cb-snippet-delete="1" data-snippet-title="' . esc_attr( $title ) . '"' : ''; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="snippet_id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( $nonce ); ?>
			<button type="submit" class="button cb-core-button <?php echo $danger ? 'cb-core-button--danger' : 'cb-core-button--secondary'; ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	private static function condition_values( array $conditions ): array {
		$out = [ 'scope' => 'any', 'login' => 'any', 'role' => '', 'post_type' => '', 'path' => '' ];
		foreach ( (array) ( $conditions['rules'] ?? [] ) as $rule ) {
			if ( ! is_array( $rule ) ) { continue; }
			$field = (string) ( $rule['field'] ?? '' );
			$value = (string) ( $rule['value'] ?? '' );
			if ( 'scope' === $field ) { $out['scope'] = $value; }
			if ( 'logged_in' === $field ) { $out['login'] = in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true ) ? 'logged_in' : 'logged_out'; }
			if ( 'user_role' === $field ) { $out['role'] = $value; }
			if ( 'post_type' === $field ) { $out['post_type'] = $value; }
			if ( 'request_path' === $field ) { $out['path'] = $value; }
		}
		return $out;
	}

	private static function format_date( string $iso ): string {
		$timestamp = strtotime( $iso );
		return false === $timestamp ? '—' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
