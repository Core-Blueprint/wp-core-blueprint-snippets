<?php
declare(strict_types=1);

use CoreBlueprint\Core\Governance\EventRegistry;

final class CB_Snippets_Mutation_Acknowledgement_Contract_Test extends WP_UnitTestCase {

	public function test_restore_acknowledgement_precedes_preserved_id_import_mutation(): void {
		$page = $this->source( 'src/Admin/Page.php' );
		$actions = $this->source( 'src/Admin/Actions.php' );
		$script = $this->source( 'assets/js/features/snippets.js' );

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

	public function test_historical_restore_event_identity_remains_readable_through_base_governance(): void {
		self::assertTrue( EventRegistry::is_valid_id( 'snippets.restore.acknowledged' ) );
		self::assertSame(
			'Snippets: restore backup and recovery responsibility acknowledged',
			EventRegistry::label( 'snippets.restore.acknowledged' )
		);
	}

	private function source( string $path ): string {
		$source = file_get_contents( CB_SNIPPETS_DIR . $path );
		self::assertIsString( $source, $path );
		return $source;
	}
}
