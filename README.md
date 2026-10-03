# Core Blueprint Snippets

Managed PHP, CSS, JavaScript and HTML snippets for the Core Blueprint suite.

This repository contains the standalone Snippets extension extracted from Core Blueprint Base. It requires Core Blueprint Base and reuses the existing governed capability, audit, admin UI and module contracts.

## Compatibility

- WordPress 7.0+
- PHP 8.4+
- Core Blueprint Base 1.0.0-rc1+
- Core Blueprint Distribution API 1.2+

The extraction preserves the existing `wp-content/cb-snippets` storage layout, `cb_core_snippets_settings` option, admin-post action names, export file type and emergency `CB_CORE_DISABLE_SNIPPETS` kill switch. Existing managed snippets therefore remain portable across the Base-to-extension transition.

## Golden release gate

A release build is accepted only after all of the following pass against an isolated WordPress test copy:

- PHP syntax and extraction-contract checks.
- Translation catalog alignment.
- JavaScript syntax.
- Release-tooling regression checks.
- PHP 8.4 and required integration extensions.
- Exact Base and Snippets test-copy validation.
- The full Snippets WordPress PHPUnit integration suite.
- Deterministic archive manifest and ZIP integrity verification.
- SHA-256 checksum generation after all validation gates.

The release builder intentionally fails when the isolated WordPress environment or either plugin test copy is stale.

## Development status

Current work branch: `feature/snippets-extraction-v1`.

Do not merge without explicit release approval.
