<?php
/**
 * Runtime contract: the compiled Dispatch prompts ask for the deck tease
 * (Journal Foundation 1.2.14).
 * Run: php tests/prompt-compiler-deck-runtime.php
 */

$root = dirname( __DIR__ );
define( 'ABSPATH', $root . DIRECTORY_SEPARATOR );
$failures = array();

function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function deck_assert( $condition, $message ) {
    global $failures;
    if ( ! $condition ) {
        $failures[] = $message;
    }
}

require_once $root . '/includes/class-lunara-journal-prompt-compiler.php';

$config = array(
    'config_version' => '1.0.99-deck-test',
    'editorial'      => array(
        'purpose'   => 'Test purpose.',
        'voice'     => array( 'summary' => 'Test voice.', 'banned_phrases' => array( 'poised to' ), 'reader_value_test' => array( 'Would a film reader text this to a friend?' ) ),
        'selection' => array( 'prefer_entries' => 2, 'max_entries' => 3, 'minimum_words' => 75, 'minimum_paragraphs' => 2, 'skip_marker' => '<!-- LUNARA_SKIP: no reader-worthy items -->', 'skip_rules' => array() ),
        'formatting'   => array( 'entry_separator' => '<hr>' ),
        'requirements' => array( 'excerpt_or_deck' => true ),
    ),
);

$system = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $config );
$user   = Lunara_Journal_Prompt_Compiler::dispatch_user_directive_prompt( $config );
$editor = Lunara_Journal_Prompt_Compiler::chatgpt_editor_instructions( $config );

$headline_at = strpos( $system, 'Start every entry with an original <h3> headline' );
$deck_at     = strpos( $system, '<!-- LUNARA_DECK: ... -->' );
$body_at     = strpos( $system, 'After the deck comment, write the body in <p> tags.' );

deck_assert( false !== $headline_at, 'System prompt must keep the headline instruction.' );
deck_assert( false !== $deck_at, 'System prompt must ask for the LUNARA_DECK comment.' );
deck_assert( false !== $body_at, 'System prompt must place the body after the deck.' );
deck_assert( false !== $headline_at && false !== $deck_at && false !== $body_at && $headline_at < $deck_at && $deck_at < $body_at, 'Headline, deck, body must be instructed in that order.' );
deck_assert( false !== strpos( $system, '18 to 40 words' ), 'System prompt must bound the deck length.' );
deck_assert( false !== strpos( $system, 'It is a tease, not a summary' ), 'System prompt must define the deck as a tease.' );
deck_assert( false !== strpos( $system, 'must not repeat the <h3>' ), 'System prompt must forbid repeating the headline.' );
deck_assert( false !== strpos( $system, 'must not repeat or paraphrase the first sentence of the body' ), 'System prompt must forbid repeating the opener.' );
deck_assert( false !== strpos( $user, 'Directly after the <h3>, write the deck as <!-- LUNARA_DECK: ... -->' ), 'User directive must ask for the deck comment.' );
deck_assert( strpos( $user, 'LUNARA_DECK' ) > strpos( $user, 'original <h3> headline' ), 'User directive must ask for the deck after the headline.' );
deck_assert( false !== strpos( $editor, '<!-- LUNARA_DECK: ... -->' ), 'Editor instructions inherit the deck rule through the compiled system prompt.' );
deck_assert( false !== strpos( $system, 'ACTIVE JOURNAL CONTROL PLANE CONFIGURATION: 1.0.99-deck-test.' ), 'Compiler must still stamp the configuration version.' );

if ( ! empty( $failures ) ) {
    fwrite( STDERR, "Prompt compiler deck runtime FAILED:\n - " . implode( "\n - ", $failures ) . "\n" );
    exit( 1 );
}
echo "Prompt compiler deck runtime passed: headline, then LUNARA_DECK tease, then body, in both compiled prompts.\n";
