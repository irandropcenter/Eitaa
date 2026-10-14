<?php
/**
 * Builds languages/mw24-eitaa-fa_IR.po and .mo from dev-tools/i18n/fa_IR.part*.json.
 *
 * Usage: php dev-tools/make-pot.php && php dev-tools/build-translations.php
 *
 * Checks that every string of the POT has a translation and that printf
 * placeholders, {placeholders} and HTML tags are preserved.
 *
 * @package MW24_Eitaa
 */

$base    = dirname( __DIR__ );
$strings = json_decode( file_get_contents( __DIR__ . '/i18n/strings.json' ), true );
$map     = array();
foreach ( glob( __DIR__ . '/i18n/fa_IR.part*.json' ) as $file ) {
	$part = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $part ) ) {
		fwrite( STDERR, "Invalid JSON: $file\n" );
		exit( 1 );
	}
	$map = array_merge( $map, $part );
}

/**
 * Tokens that must survive translation.
 *
 * @param string $text Text.
 * @return array
 */
function mw24_tokens( $text ) {
	preg_match_all( '/%(?:\d+\$)?[sd]|%%|\{[a-z_]+\}|<\/?[a-z]+(?:\s[^>]*)?>/', $text, $m );
	$tokens = $m[0];
	sort( $tokens );
	return $tokens;
}

$missing  = array();
$problems = array();
$entries  = array();
foreach ( $strings as $string ) {
	$key = ( '' !== $string['context'] ? $string['context'] . "\4" : '' ) . $string['msgid'];
	if ( ! isset( $map[ $key ] ) || '' === trim( $map[ $key ] ) ) {
		$missing[] = $key;
		continue;
	}
	if ( mw24_tokens( $string['msgid'] ) !== mw24_tokens( $map[ $key ] ) ) {
		$problems[] = $key . "\n    src: " . implode( ' ', mw24_tokens( $string['msgid'] ) ) . "\n    fa:  " . implode( ' ', mw24_tokens( $map[ $key ] ) );
	}
	$entries[ $key ] = array(
		'context' => $string['context'],
		'msgid'   => $string['msgid'],
		'msgstr'  => $map[ $key ],
	);
}
$known  = array_keys( $entries );
$unused = array_diff( array_keys( $map ), $known, $missing );

echo count( $entries ) . ' translated, ' . count( $missing ) . ' missing, ' . count( $problems ) . ' placeholder problems, ' . count( $unused ) . " unused\n";
foreach ( $missing as $key ) {
	echo '  MISSING: ' . str_replace( "\4", ' | ', $key ) . "\n";
}
foreach ( $problems as $problem ) {
	echo '  TOKENS: ' . $problem . "\n";
}
foreach ( $unused as $key ) {
	echo '  UNUSED: ' . str_replace( "\4", ' | ', $key ) . "\n";
}
if ( $missing || $problems ) {
	exit( 1 );
}

$header = "Project-Id-Version: Eitaa Connect — Mihanweb24 1.0.0\n"
	. 'PO-Revision-Date: ' . gmdate( 'Y-m-d H:i' ) . "+0000\n"
	. "Last-Translator: Mihanweb24\n"
	. "Language-Team: Persian\n"
	. "Language: fa_IR\n"
	. "MIME-Version: 1.0\n"
	. "Content-Type: text/plain; charset=UTF-8\n"
	. "Content-Transfer-Encoding: 8bit\n"
	. "Plural-Forms: nplurals=2; plural=(n > 1);\n"
	. "X-Domain: mw24-eitaa\n";

/**
 * PO escaping.
 *
 * @param string $text Text.
 * @return string
 */
function mw24_po( $text ) {
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

$po  = "# Persian translation of Eitaa Connect — Mihanweb24.\n# This file is distributed under the GPL-2.0-or-later.\n";
$po .= "msgid \"\"\nmsgstr " . mw24_po( $header ) . "\n\n";
foreach ( $entries as $entry ) {
	if ( '' !== $entry['context'] ) {
		$po .= 'msgctxt ' . mw24_po( $entry['context'] ) . "\n";
	}
	$po .= 'msgid ' . mw24_po( $entry['msgid'] ) . "\n";
	$po .= 'msgstr ' . mw24_po( $entry['msgstr'] ) . "\n\n";
}
file_put_contents( $base . '/mw24-eitaa/languages/mw24-eitaa-fa_IR.po', $po );

// Compile the .mo file.
$mo = array( '' => $header );
foreach ( $entries as $key => $entry ) {
	$mo[ $key ] = $entry['msgstr'];
}
ksort( $mo, SORT_STRING );
$count      = count( $mo );
$originals  = '';
$translated = '';
$o_table    = array();
$t_table    = array();
foreach ( $mo as $original => $translation ) {
	$o_table[]  = array( strlen( $original ), strlen( $originals ) );
	$originals .= $original . "\0";
	$t_table[]  = array( strlen( $translation ), strlen( $translated ) );
	$translated .= $translation . "\0";
}
$o_offset = 28;
$t_offset = $o_offset + $count * 8;
$s_offset = $t_offset + $count * 8;
$binary   = pack( 'V7', 0x950412de, 0, $count, $o_offset, $t_offset, 0, $s_offset );
foreach ( $o_table as $row ) {
	$binary .= pack( 'V2', $row[0], $s_offset + $row[1] );
}
$t_start = $s_offset + strlen( $originals );
foreach ( $t_table as $row ) {
	$binary .= pack( 'V2', $row[0], $t_start + $row[1] );
}
$binary .= $originals . $translated;
file_put_contents( $base . '/mw24-eitaa/languages/mw24-eitaa-fa_IR.mo', $binary );
echo "Wrote mw24-eitaa-fa_IR.po and mw24-eitaa-fa_IR.mo ($count entries)\n";
