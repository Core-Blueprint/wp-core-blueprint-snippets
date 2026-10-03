#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$base = getenv( 'CB_BASE_SOURCE_DIR' );
if ( ! is_string( $base ) || '' === $base || ! is_dir( $base ) ) {
	fwrite( STDERR, "[translations] ERROR: CB_BASE_SOURCE_DIR must point to the Core Blueprint Base checkout.\n" );
	exit( 1 );
}
$base = rtrim( $base, "/\\" );

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

function literal_value( array $tokens ): ?string {
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

function source_messages( string $root, string $domain, array $functions ): array {
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

	$messages = [];
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
			$call_domain = literal_value( $args[ $spec['domain'] ] );
			if ( $domain !== $call_domain ) {
				continue;
			}
			$msgid = literal_value( $args[ $spec['msg'] ] );
			$plural = isset( $spec['plural'] ) ? literal_value( $args[ $spec['plural'] ] ) : null;
			$context = isset( $spec['context'] ) ? literal_value( $args[ $spec['context'] ] ) : null;
			if ( null === $msgid || '' === $msgid ) {
				continue;
			}
			$key = null !== $context ? $context . "\4" . $msgid : $msgid;
			$messages[ $key ] = [ 'msgid' => $msgid, 'plural' => $plural ];
			$i = $j;
		}
	}
	ksort( $messages, SORT_STRING );
	return $messages;
}

function php_export_file( array $catalog ): string {
	$export = var_export( $catalog, true );
	$export = preg_replace( '/[ \\t]+$/m', '', $export ) ?? $export;
	return "<?php\ndeclare(strict_types=1);\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn " . $export . ";\n";
}

$source = source_messages( $root, $domain, $functions );
if ( [] === $source ) {
	fwrite( STDERR, "[translations] ERROR: no Snippets source messages found.\n" );
	exit( 1 );
}

@mkdir( $root . '/languages', 0777, true );
$base_ref = getenv( 'CB_BASE_TRANSLATION_REF' );
if ( ! is_string( $base_ref ) || '' === trim( $base_ref ) ) {
	$base_ref = 'origin/main';
}

foreach ( $locales as $locale ) {
	$relative = 'languages/base/core-blueprint-' . $locale . '.php';
	$command = sprintf(
		'git -C %s show %s:%s',
		escapeshellarg( $base ),
		escapeshellarg( $base_ref ),
		escapeshellarg( $relative )
	);
	$payload = shell_exec( $command );
	if ( ! is_string( $payload ) || '' === trim( $payload ) ) {
		fwrite( STDERR, "[translations] ERROR: could not read {$relative} from Base ref {$base_ref}.\n" );
		exit( 1 );
	}

	$temp = tempnam( sys_get_temp_dir(), 'cb-snippets-i18n-' );
	if ( false === $temp || false === file_put_contents( $temp, $payload ) ) {
		fwrite( STDERR, "[translations] ERROR: could not materialize Base catalog {$locale}.\n" );
		exit( 1 );
	}
	try {
		$base_catalog = require $temp;
	} finally {
		@unlink( $temp );
	}
	if ( ! is_array( $base_catalog ) || ! is_array( $base_catalog['messages'] ?? null ) ) {
		fwrite( STDERR, "[translations] ERROR: invalid Base catalog {$locale} at {$base_ref}.\n" );
		exit( 1 );
	}

	$messages = [];
	$missing = [];
	foreach ( $source as $key => $entry ) {
		if ( ! array_key_exists( $key, $base_catalog['messages'] ) ) {
			$missing[] = $key;
			continue;
		}
		$messages[ $key ] = $base_catalog['messages'][ $key ];
	}
	if ( [] !== $missing ) {
		fwrite( STDERR, "[translations] ERROR: {$locale} missing " . count( $missing ) . " Snippets translations.\n" );
		exit( 1 );
	}

	$catalog = [
		'project-id-version' => 'Core Blueprint Snippets 1.0.0-rc1',
		'language' => $locale,
		'plural-forms' => (string) ( $base_catalog['plural-forms'] ?? '' ),
		'content-type' => 'text/plain; charset=UTF-8',
		'x-generator' => 'Core Blueprint Snippets extraction',
		'messages' => $messages,
	];
	file_put_contents(
		$root . '/languages/core-blueprint-snippets-' . $locale . '.l10n.php',
		php_export_file( $catalog )
	);
}

printf(
	"PASS: generated Core Blueprint Snippets catalogs (%d messages; %d locales).\n",
	count( $source ),
	count( $locales )
);
