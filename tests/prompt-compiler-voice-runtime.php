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
 * 1.4.0: every entry closes on an engagement question (Dalton's guide: "Always
 * present. Goes last."), one story per run, and his own published entries are
 * compiled in full as the voice target.
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
	"DALTON'S VOICE ON THE PAGE:",
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
pv_assert( false !== strpos( $directive, 'then one engagement question' ) && false !== strpos( $directive, 'Always; never a poll.' ), 'User directive must close every entry on an engagement question.' );
pv_assert( false === strpos( $directive, 'most entries should not' ) && false === strpos( $prompt, 'roughly one entry in three' ), 'The old one-in-three question rule must be gone from both prompts.' );
pv_assert( false !== strpos( $prompt, 'Every entry ends with one engagement question' ) && false !== strpos( $prompt, 'It is always there' ), 'Compiled close must make the engagement question mandatory.' );
pv_assert( false !== strpos( $prompt, 'The engagement question, always, as the final beat.' ), 'Structure must end on the engagement question.' );
pv_assert( false !== strpos( $prompt, '300 to 700 words' ) && false !== strpos( $directive, '300 to 700 words' ), 'Both prompts must carry the single-story length target.' );
pv_assert( false !== strpos( $prompt, 'Write one entry per run' ) && false === strpos( $prompt, 'Prefer 1 strong entries' ), 'One-entry selection must read as one entry, not a plural preference.' );
pv_assert( false !== strpos( $directive, 'Write one entry:' ) && false === strpos( $directive, 'Prefer 1 or fewer' ), 'One-entry directive must read as one entry.' );
$multi = $default;
$multi['config_version'] = 'test-multi';
$multi['editorial']['selection']['prefer_entries'] = 2;
$multi['editorial']['selection']['max_entries'] = 3;
pv_assert( false !== strpos( Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $multi ), 'Never write more than 3 entries.' ) && false !== strpos( Lunara_Journal_Prompt_Compiler::dispatch_user_directive_prompt( $multi ), 'Prefer 2 or fewer strong entries; never write more than 3.' ), 'A multi-entry configuration keeps the plural rules.' );
pv_assert( false !== strpos( $directive, 'Fan first, critic brain second' ), 'User directive must put the fan before the critic.' );
pv_assert( false !== strpos( $directive, 'First person is allowed' ), 'User directive must permit first person.' );
pv_assert( substr( rtrim( $directive ), -16 ) === 'Input News Data:', 'User directive must still end at the news-data boundary.' );

/* 4b. Dalton's own entries are compiled in full, framed as the target, never as copy. */
pv_assert( 2 === substr_count( $prompt, '<example index=' ) && 2 === substr_count( $prompt, '</example>' ), 'Both exemplars must compile as complete example blocks.' );
pv_assert( false !== strpos( $prompt, 'HEADLINE: Robert Eggers Made a Werewolf Movie in Middle English and I Have Never Been More In' ) && false !== strpos( $prompt, 'HEADLINE: Paramount Let Street Fighter Be Unhinged. Thank God.' ), 'The Eggers and Street Fighter entries must be the exemplars.' );
pv_assert( false !== strpos( $prompt, 'does it even matter as long as the dread lands?' ) && false !== strpos( $prompt, 'October 16. I\'ll be there.' ), 'Exemplars must compile in full, through their last line.' );
pv_assert( false === strpos( $prompt, 'wp-video' ) && false === strpos( $prompt, 'Watch below' ) && false === strpos( $prompt, 'POST DETAILS' ) && false === strpos( $prompt, '&nbsp;' ), 'Page furniture must not reach the exemplars.' );
pv_assert( false !== strpos( $prompt, 'Never copy their sentences' ) && false !== strpos( $prompt, 'Never invent an experience Dalton did not have' ), 'Exemplars must be framed as a voice target, never as copy or invented experience.' );
pv_assert( strpos( $prompt, "DALTON'S VOICE ON THE PAGE:" ) < strpos( $prompt, 'SELECTION RULES:' ), 'The voice target must come before the mechanics.' );
pv_assert( false !== strpos( $legacy_prompt, '<example index="1">' ), 'A stored configuration from before 1.4.0 must receive the exemplars.' );
$custom = $default;
$custom['config_version'] = 'test-custom-exemplar';
$custom['editorial']['voice']['exemplars'] = array(
	array( 'title' => 'Only One', 'text' => "<p>A <em>Film</em> with <strong>tags</strong> \xE2\x80\x94 and \xE2\x80\x9Csmart\xE2\x80\x9D quotes.</p><!-- internal -->" ),
	array( 'title' => '', 'text' => 'Dropped: no title.' ),
);
$custom = Lunara_Journal_Config_Schema::sanitize_config( $custom );
pv_assert( 1 === count( $custom['editorial']['voice']['exemplars'] ), 'An edited exemplar list replaces the default and drops malformed rows.' );
pv_assert( 'A <em>Film</em> with tags -- and "smart" quotes.' === $custom['editorial']['voice']['exemplars'][0]['text'], 'Exemplar text keeps only <em>, drops comments, and folds to ASCII.' );

/* 5. The ChatGPT editor instructions inherit the same voice. */
$editor = Lunara_Journal_Prompt_Compiler::chatgpt_editor_instructions( $default );
pv_assert( false !== strpos( $editor, 'REGISTER:' ) && false !== strpos( $editor, 'Never publish' ), 'Editor instructions must carry the voice without losing the draft-only guardrails.' );

echo "Prompt compiler voice runtime passed.\n";
