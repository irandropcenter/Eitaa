<?php
/**
 * Generates languages/mw24-eitaa.pot from the plugin sources (no WP-CLI needed).
 *
 * Usage: php dev-tools/make-pot.php
 *
 * Extracts __(), _e(), esc_html__(), esc_html_e(), esc_attr__(), esc_attr_e(), _x(),
 * esc_html_x(), esc_attr_x(), _n(), _nx() calls with literal arguments, translator
 * comments, the plugin header and block.json strings.
 *
 * @package MW24_Eitaa
 */

$root   = dirname( __DIR__ ) . '/mw24-eitaa';
$domain = 'mw24-eitaa';

$functions = array(
	'__'          => array( 'msgid' ),
	'_e'          => array( 'msgid' ),
	'esc_html__'  => array( 'msgid' ),
	'esc_html_e'  => array( 'msgid' ),
	'esc_attr__'  => array( 'msgid' ),
	'esc_attr_e'  => array( 'msgid' ),
	'_x'          => array( 'msgid', 'context' ),
	'_ex'         => array( 'msgid', 'context' ),
	'esc_html_x'  => array( 'msgid', 'context' ),
	'esc_attr_x'  => array( 'msgid', 'context' ),
	'_n'          => array( 'msgid', 'plural' ),
	'_nx'         => array( 'msgid', 'plural', 'count', 'context' ),
	'_n_noop'     => array( 'msgid', 'plural' ),
);

$entries = array();

/**
 * Adds an entry.
 *
 * @param array  $entries   Entries.
 * @param string $msgid     Msgid.
 * @param string $context   Context.
 * @param string $plural    Plural.
 * @param string $reference Reference.
 * @param string $comment   Translator comment.
 */
function mw24_pot_add( array &$entries, $msgid, $context, $plural, $reference, $comment ) {
	$key = $context . "\4" . $msgid;
	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array(
			'msgid'    => $msgid,
			'context'  => $context,
			'plural'   => $plural,
			'refs'     => array(),
			'comments' => array(),
		);
	}
	$entries[ $key ]['refs'][] = $reference;
	if ( '' !== $comment && ! in_array( $comment, $entries[ $key ]['comments'], true ) ) {
		$entries[ $key ]['comments'][] = $comment;
	}
}

/**
 * Decodes a PHP string literal token.
 *
 * @param string $literal Literal.
 * @return string
 */
function mw24_pot_literal( $literal ) {
	$quote = $literal[0];
	$body  = substr( $literal, 1, -1 );
	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}
	return stripcslashes( $body );
}

// Plugin header.
$main = file_get_contents( $root . '/mw24-eitaa.php' );
foreach ( array( 'Plugin Name', 'Description', 'Author' ) as $header ) {
	if ( preg_match( '/^\s*\*\s*' . preg_quote( $header, '/' ) . ':\s*(.+)$/m', $main, $m ) ) {
		mw24_pot_add( $entries, trim( $m[1] ), '', '', 'mw24-eitaa.php', $header . ' of the plugin' );
	}
}

// block.json.
$block = json_decode( file_get_contents( $root . '/blocks/login-button/block.json' ), true );
mw24_pot_add( $entries, $block['title'], 'block title', '', 'blocks/login-button/block.json', '' );
mw24_pot_add( $entries, $block['description'], 'block description', '', 'blocks/login-button/block.json', '' );
foreach ( $block['keywords'] as $keyword ) {
	mw24_pot_add( $entries, $keyword, 'block keyword', '', 'blocks/login-button/block.json', '' );
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$files    = array();
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( '.php' === substr( $path, -4 ) && false === strpos( $path, '/tests/' ) && false === strpos( $path, '/vendor/' ) ) {
		$files[] = $path;
	}
}
sort( $files );

foreach ( $files as $path ) {
	$relative = substr( $path, strlen( $root ) + 1 );
	$tokens   = token_get_all( file_get_contents( $path ) );
	$count    = count( $tokens );
	$comment  = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( is_array( $token ) && T_COMMENT === $token[0] && false !== stripos( $token[1], 'translators:' ) ) {
			$comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', trim( $token[1] ) ) );
			$comment = trim( preg_replace( '/\s*\n\s*\*?\s*/', ' ', $comment ) );
			continue;
		}
		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $functions[ $token[1] ] ) ) {
			continue;
		}
		// Skip method calls / definitions.
		$prev = $i - 1;
		while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
			--$prev;
		}
		if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}
		// Collect top-level arguments.
		$args  = array();
		$depth = 0;
		$cur   = array();
		for ( $k = $j + 1; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t || '{' === $t ) {
				++$depth;
			} elseif ( ')' === $t || ']' === $t || '}' === $t ) {
				if ( 0 === $depth ) {
					$args[] = $cur;
					break;
				}
				--$depth;
			} elseif ( ',' === $t && 0 === $depth ) {
				$args[] = $cur;
				$cur    = array();
				continue;
			}
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT ), true ) ) {
				continue;
			}
			$cur[] = $t;
		}
		$spec   = $functions[ $token[1] ];
		$values = array();
		$ok     = true;
		foreach ( $spec as $index => $name ) {
			if ( 'count' === $name ) {
				continue;
			}
			$arg = isset( $args[ $index ] ) ? $args[ $index ] : array();
			if ( 1 !== count( $arg ) || ! is_array( $arg[0] ) || T_CONSTANT_ENCAPSED_STRING !== $arg[0][0] ) {
				$ok = false;
				break;
			}
			$values[ $name ] = mw24_pot_literal( $arg[0][1] );
		}
		$domain_index = count( $spec );
		$domain_arg   = isset( $args[ $domain_index ] ) ? $args[ $domain_index ] : array();
		if ( ! $ok || 1 !== count( $domain_arg ) || ! is_array( $domain_arg[0] ) || mw24_pot_literal( $domain_arg[0][1] ) !== $domain ) {
			continue;
		}
		mw24_pot_add(
			$entries,
			$values['msgid'],
			isset( $values['context'] ) ? $values['context'] : '',
			isset( $values['plural'] ) ? $values['plural'] : '',
			$relative . ':' . $token[2],
			$comment
		);
		$comment = '';
	}
}

/**
 * Escapes a PO string.
 *
 * @param string $text Text.
 * @return string
 */
function mw24_po_escape( $text ) {
	$text = str_replace( array( '\\', '"', "\t" ), array( '\\\\', '\\"', '\\t' ), $text );
	if ( false === strpos( $text, "\n" ) ) {
		return '"' . $text . '"';
	}
	$lines = explode( "\n", $text );
	$last  = array_pop( $lines );
	$out   = "\"\"\n";
	foreach ( $lines as $line ) {
		$out .= '"' . $line . "\\n\"\n";
	}
	if ( '' !== $last ) {
		$out .= '"' . $last . "\"\n";
	}
	return rtrim( $out, "\n" );
}

$pot  = "# Copyright (C) " . gmdate( 'Y' ) . " Mihanweb24\n";
$pot .= "# This file is distributed under the GPL-2.0-or-later.\n";
$pot .= "msgid \"\"\nmsgstr \"\"\n";
$pot .= "\"Project-Id-Version: Eitaa Connect — Mihanweb24 1.0.0\\n\"\n";
$pot .= "\"Report-Msgid-Bugs-To: \\n\"\n";
$pot .= "\"POT-Creation-Date: " . gmdate( 'Y-m-d H:i' ) . "+0000\\n\"\n";
$pot .= "\"MIME-Version: 1.0\\n\"\n";
$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$pot .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$pot .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
$pot .= "\"Last-Translator: \\n\"\n";
$pot .= "\"Language-Team: \\n\"\n";
$pot .= "\"X-Domain: mw24-eitaa\\n\"\n\n";

foreach ( $entries as $entry ) {
	foreach ( $entry['comments'] as $comment ) {
		$pot .= '#. ' . $comment . "\n";
	}
	$pot .= '#: ' . implode( ' ', array_slice( $entry['refs'], 0, 6 ) ) . "\n";
	if ( false !== strpos( $entry['msgid'], '%' ) ) {
		$pot .= "#, php-format\n";
	}
	if ( '' !== $entry['context'] ) {
		$pot .= 'msgctxt ' . mw24_po_escape( $entry['context'] ) . "\n";
	}
	$pot .= 'msgid ' . mw24_po_escape( $entry['msgid'] ) . "\n";
	if ( '' !== $entry['plural'] ) {
		$pot .= 'msgid_plural ' . mw24_po_escape( $entry['plural'] ) . "\n";
		$pot .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$pot .= "msgstr \"\"\n\n";
	}
}

file_put_contents( $root . '/languages/mw24-eitaa.pot', $pot );
file_put_contents( __DIR__ . '/i18n/strings.json', json_encode( array_values( array_map( function ( $e ) {
	return array( 'context' => $e['context'], 'msgid' => $e['msgid'] );
}, $entries ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo count( $entries ) . " strings written to languages/mw24-eitaa.pot\n";
