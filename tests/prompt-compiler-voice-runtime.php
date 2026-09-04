<?php
/**
 * Runtime contract: the Journal voice reaches the compiled Dispatch prompt.
 *
 * The Control Plane compiler is the only prompt the model sees when Foundation
 * is active. Before 1.2.14 it carried a one-sentence voice summary; the rest of
 * the register lived in a Dispatch fallback that never executed. This contract
 * holds the voice in the compiler, and holds it for stored configurations that
 * predate the new keys.
 *
 * Run: php tests/prompt-compiler-voice-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function pv_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) { return trim( (string) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function current_time() { return '2026-09-04 12:00:00'; }
function get_option( $key, $default = false ) { return $default; }
function wp_json_encode( $value ) { return json_encode( $value ); }

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-prompt-compiler.php';

$required_sections = array(
	'REGISTER:',
	'PRINCIPLES:',
	'STRUCTURE, FLEXIBLE BY STORY:',
	'HEADLINES:',
	'Not this: ',
	'This: ',
	'DRIFT TO CATCH BEFORE OUTPUT:',
	'CUT ON SIGHT.',
	'LANDING AND CLOSE:',
);
$required_phrases = array(
	'First person is allowed',
	'talk, not essay',
	'Fan first, critic second',
	'Worn-lightly expertise',
	'X Turns Y Into Z',
	'engagement',
	'straight quotes and apostrophes',
	'as we know',
	'notably,',
	'the takeaway is simple',
);

/* 1. A fresh default configuration compiles the full voice. */
$default = Lunara_Journal_Config_Schema::sanitize_config( Lunara_Journal_Config_Schema::default_config() );
$default['config_version'] = 'test-default';
$prompt = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $default );
foreach ( $required_sections as $section ) {
	pv_assert( false !== strpos( $prompt, $section ), "Default compiled prompt is missing the section: {$section}" );
}
foreach ( $required_phrases as $phrase ) {
	pv_assert( false !== strpos( $prompt, $phrase ), "Default compiled prompt is missing the phrase: {$phrase}" );
}
pv_assert( false === stripos( $prompt, 'do not force a question' ), 'Compiled prompt must not carry the old anti-question rule that contradicts the engagement close.' );
pv_assert( preg_match( '/^[\x09\x0A\x0D\x20-\x7E]*$/', $prompt ) === 1, 'Compiled prompt must be ASCII-only, since it asks the model for ASCII-only output.' );
pv_assert( strpos( $prompt, 'REGISTER:' ) < strpos( $prompt, 'SELECTION RULES:' ), 'Register must be stated before selection mechanics.' );
pv_assert( strpos( $prompt, 'LANDING AND CLOSE:' ) < strpos( $prompt, 'FORMATTING - CRITICAL:' ), 'Landing and close must precede the formatting block.' );
pv_assert( substr_count( $prompt, 'Not this: ' ) >= 6 && substr_count( $prompt, 'Not this: ' ) === substr_count( $prompt, "\nThis: " ), 'Every contrast example must compile as a complete Not this / This pair.' );

/* 2. A stored configuration from before 1.2.14 keeps its edited fields and still receives the code-owned voice. */
$legacy = Lunara_Journal_Config_Schema::default_config();
$legacy['config_version'] = '1.0.25';
$legacy['editorial']['voice'] = array(
	'summary'            => 'STORED SUMMARY FROM WP ADMIN',
	'current_refinement' => 'STORED REFINEMENT NOTE',
	'reader_value_test'  => $legacy['editorial']['voice']['reader_value_test'],
	'banned_phrases'     => array( 'stored banned phrase' ),
);
$legacy = Lunara_Journal_Config_Schema::sanitize_config( $legacy );
$legacy_prompt = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $legacy );
pv_assert( false !== strpos( $legacy_prompt, 'STORED SUMMARY FROM WP ADMIN' ), 'Admin-edited voice summary must survive the merge.' );
pv_assert( false !== strpos( $legacy_prompt, 'STORED REFINEMENT NOTE' ), 'Admin-edited refinement note must survive the merge.' );
pv_assert( false !== strpos( $legacy_prompt, 'stored banned phrase' ), 'Admin-edited banned phrases must survive the merge.' );
pv_assert( strpos( $legacy_prompt, 'CURRENT DALTON VOICE / PROMPT REFINEMENT:' ) > strpos( $legacy_prompt, 'PRINCIPLES:' ), 'The refinement note must land after the principles so it reads as the freshest steering.' );
foreach ( $required_sections as $section ) {
	pv_assert( false !== strpos( $legacy_prompt, $section ), "Pre-1.2.14 stored configuration lost the code-owned voice section: {$section}" );
}

/* 3. A malformed contrast example is dropped rather than compiled half-formed. */
$broken = $default;
$broken['config_version'] = 'test-broken';
$broken['editorial']['voice']['contrast_examples'] = array(
	array( 'not_this' => 'Only half a pair.' ),
	'not an array',
	array( 'not_this' => 'A complete pair.', 'this' => 'Lands.' ),
);
$broken_prompt = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $broken );
pv_assert( 1 === substr_count( $broken_prompt, 'Not this: ' ) && false === strpos( $broken_prompt, 'Only half a pair.' ), 'Half-formed contrast examples must be dropped.' );

/* 4. The user directive carries the per-entry close and the fan-first order. */
$directive = Lunara_Journal_Prompt_Compiler::dispatch_user_directive_prompt( $default );
pv_assert( false !== strpos( $directive, 'engagement question' ), 'User directive must require the per-entry engagement question.' );
pv_assert( false !== strpos( $directive, 'Fan first, critic brain second' ), 'User directive must put the fan before the critic.' );
pv_assert( false !== strpos( $directive, 'First person is allowed' ), 'User directive must permit first person.' );
pv_assert( substr( rtrim( $directive ), -16 ) === 'Input News Data:', 'User directive must still end at the news-data boundary.' );

/* 5. The ChatGPT editor instructions inherit the same voice. */
$editor = Lunara_Journal_Prompt_Compiler::chatgpt_editor_instructions( $default );
pv_assert( false !== strpos( $editor, 'REGISTER:' ) && false !== strpos( $editor, 'Never publish' ), 'Editor instructions must carry the voice without losing the draft-only guardrails.' );

echo "Prompt compiler voice runtime passed.\n";
