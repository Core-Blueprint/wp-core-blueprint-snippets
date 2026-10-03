#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
$domain = 'core-blueprint-snippets';
$locales = [ 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ];
$functions = [
	'__' => [ 'msg' => 0, 'domain' => 1 ],
	'_e' => [ 'msg' => 0, 'domain' => 1 ],
	'esc_html__' => [ 'msg' => 0, 'domain' => 1 ],
	'esc_html_e' => [ 'msg' => 0, 'domain' => 1 ],
	'esc_attr__' => [ 'msg' => 0, 'domain' => 1 ],
	'esc_attr_e' => [ 'msg' => 0, 'domain' => 1 ],
	'_x' => [ 'msg' => 0, 'context' => 1, 'domain' => 2 ],
	'_ex' => [ 'msg' => 0, 'context' => 1, 'domain' => 2 ],
	'esc_html_x' => [ 'msg' => 0, 'context' => 1, 'domain' => 2 ],
	'esc_attr_x' => [ 'msg' => 0, 'context' => 1, 'domain' => 2 ],
	'_n' => [ 'msg' => 0, 'plural' => 1, 'domain' => 3 ],
	'_nx' => [ 'msg' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ],
];

function fail_i18n( string $message ): never {
	fwrite( STDERR, "[translations] ERROR: {$message}\n" );
	exit( 1 );
}

function literal_i18n( array $tokens ): ?string {
	$expression = '';
	foreach ( $tokens as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}
			if ( T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
				return null;
			}
			$expression .= $token[1];
			continue;
		}
		if ( '.' === $token ) {
			$expression .= '.';
			continue;
		}
		if ( '' !== trim( (string) $token ) ) {
			return null;
		}
	}
	if ( '' === $expression ) {
		return null;
	}
	try {
		$value = eval( 'return ' . $expression . ';' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- literal-only extraction helper.
	} catch ( Throwable ) {
		return null;
	}
	return is_string( $value ) ? $value : null;
}

$files = [ $root . '/core-blueprint-snippets.php', $root . '/uninstall.php' ];
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$files[] = $file->getPathname();
	}
}
sort( $files, SORT_STRING );

$source = [];
foreach ( $files as $file ) {
	$tokens = token_get_all( (string) file_get_contents( $file ) );
	$count = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $functions[ $token[1] ] ) ) {
			continue;
		}
		$spec = $functions[ $token[1] ];
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
			$j++;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		$args = [];
		$current = [];
		$depth = 1;
		for ( $j++; $j < $count; $j++ ) {
			$part = $tokens[ $j ];
			if ( in_array( $part, [ '(', '[', '{' ], true ) ) {
				$depth++;
				$current[] = $part;
				continue;
			}
			if ( in_array( $part, [ ')', ']', '}' ], true ) ) {
				$depth--;
				if ( 0 === $depth ) {
					$args[] = $current;
					break;
				}
				$current[] = $part;
				continue;
			}
			if ( ',' === $part && 1 === $depth ) {
				$args[] = $current;
				$current = [];
				continue;
			}
			$current[] = $part;
		}
		if ( count( $args ) <= max( array_values( $spec ) ) ) {
			continue;
		}
		if ( $domain !== literal_i18n( $args[ $spec['domain'] ] ) ) {
			continue;
		}
		$msgid = literal_i18n( $args[ $spec['msg'] ] );
		$context = isset( $spec['context'] ) ? literal_i18n( $args[ $spec['context'] ] ) : null;
		if ( null === $msgid || '' === $msgid ) {
			continue;
		}
		$key = null !== $context ? $context . "\4" . $msgid : $msgid;
		$source[ $key ] = true;
		$i = $j;
	}
}
ksort( $source, SORT_STRING );

if ( [] === $source ) {
	fail_i18n( 'No canonical source messages found.' );
}

foreach ( $locales as $locale ) {
	$path = $root . '/languages/core-blueprint-snippets-' . $locale . '.l10n.php';
	if ( ! is_file( $path ) ) {
		fail_i18n( "Missing {$locale} PHP catalog." );
	}
	$catalog = require $path;
	if ( ! is_array( $catalog ) || ! is_array( $catalog['messages'] ?? null ) ) {
		fail_i18n( "Invalid {$locale} catalog payload." );
	}
	if ( 'Core Blueprint Snippets 1.0.0-rc1' !== ( $catalog['project-id-version'] ?? '' ) ) {
		fail_i18n( "Unexpected {$locale} project version." );
	}
	$messages = array_fill_keys( array_map( 'strval', array_keys( $catalog['messages'] ) ), true );
	$missing = array_diff_key( $source, $messages );
	$extra = array_diff_key( $messages, $source );
	if ( [] !== $missing || [] !== $extra ) {
		fail_i18n( sprintf( '%s catalog/source mismatch: %d missing, %d extra.', $locale, count( $missing ), count( $extra ) ) );
	}
}

printf( "PASS: Core Blueprint Snippets translation catalogs aligned (%d messages; %d locales).\n", count( $source ), count( $locales ) );
