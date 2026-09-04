<?php
/**
 * Runtime contract: performed-expertise phrases surface as warnings, never
 * as validation errors.
 *
 * Run: php tests/validator-house-tells-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function vh_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) { return trim( (string) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function current_time() { return '2026-09-04 12:00:00'; }
function get_option( $key, $default = false ) { return $default; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_post( $id ) { return $GLOBALS['vh_post']; }
function get_post_meta( $id, $key, $single = false ) {
	$meta = array(
		'journal_deck'            => 'A deck.',
		'journal_seo_description' => 'An SEO description.',
		'journal_source_items'    => array( array( 'source_url' => 'https://example.com/story' ) ),
	);
	return isset( $meta[ $key ] ) ? $meta[ $key ] : '';
}
final class Lunara_Journal_Image_Guard { public static function inspect( $id ) { return array( 'errors' => array(), 'warnings' => array() ); } }
final class Lunara_Journal_Control_Plane { public static function get_active_config() { return $GLOBALS['vh_config']; } }

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-validator.php';

$GLOBALS['vh_config'] = Lunara_Journal_Config_Schema::sanitize_config( Lunara_Journal_Config_Schema::default_config() );
$body = str_repeat( '<p>' . str_repeat( 'word ', 40 ) . '</p>', 3 );

$GLOBALS['vh_post'] = (object) array( 'ID' => 1, 'post_type' => 'journal', 'post_status' => 'draft', 'post_title' => 'A Headline With Enough Words', 'post_excerpt' => '', 'post_content' => '<p>Notably, this is the first project since the last one.</p>' . $body );
$report = Lunara_Journal_Validator::validate_post( 1 );
vh_assert( true === $report['valid'], 'A house tell must not fail validation on its own: ' . implode( ' | ', $report['errors'] ) );
vh_assert( in_array( 'House tell found, cut on sight: notably,', $report['warnings'], true ), 'A house tell must surface as a warning.' );

$GLOBALS['vh_post']->post_content = '<p>This guy has not made a watchable movie since 2009.</p>' . $body;
$report = Lunara_Journal_Validator::validate_post( 1 );
foreach ( $report['warnings'] as $warning ) {
	vh_assert( 0 !== strpos( $warning, 'House tell found' ), 'Clean copy must not raise a house-tell warning.' );
}

$GLOBALS['vh_post']->post_content = '<p>This is poised to be a testament to something.</p>' . $body;
$report = Lunara_Journal_Validator::validate_post( 1 );
vh_assert( false === $report['valid'] && in_array( 'Banned phrase found: a testament to', $report['errors'], true ), 'Banned phrases must still fail validation as errors.' );

echo "Validator house-tells runtime passed.\n";
