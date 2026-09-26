<?php
/**
 * Runtime check for the 1.4.0 Journal voice move (Lunara_Journal_Voice_Upgrade).
 *
 * Drafts came out three to a 2,200-token response, with a closing question
 * about a third of the time, and read like trade recaps. The stored
 * configuration carries its own structure and selection, so a new code
 * default never reached it. This holds the one-time move of the active
 * version: the voice step lands straight away on any provider, the Claude
 * step waits for an Anthropic key, each runs once, and neither undoes a later
 * edit. The real schema, compiler, and repository run against an in-memory
 * options table.
 *
 * Run: php tests/voice-upgrade-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['vu_options'] = array();
$GLOBALS['vu_context'] = true;
$GLOBALS['vu_activated'] = 0;

class WP_Error {
    private $code; private $message;
    public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['vu_options'] ) ? $GLOBALS['vu_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['vu_options'][ $key ] = $value; return true; }
function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) {
    if ( array_key_exists( $key, $GLOBALS['vu_options'] ) ) { return false; }
    $GLOBALS['vu_options'][ $key ] = $value;
    return true;
}
function delete_option( $key ) { unset( $GLOBALS['vu_options'][ $key ] ); return true; }
function add_action() { return true; }
function do_action( $hook ) { if ( 'lunara_journal_control_plane_activated' === $hook ) { $GLOBALS['vu_activated']++; } }
function is_admin() { return $GLOBALS['vu_context']; }
function wp_doing_cron() { return false; }
function current_time() { return '2026-09-26 12:00:00'; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) ); }
function esc_url_raw( $v, $p = null ) { return trim( (string) $v ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $v ) { return json_encode( $v ); }

$failures = array();
function vu_check( $ok, $message ) { global $failures; if ( ! $ok ) { $failures[] = $message; } }

$root = dirname( __DIR__ );
require $root . '/includes/class-lunara-journal-protocol.php';
require $root . '/includes/class-lunara-journal-config-schema.php';
require $root . '/includes/class-lunara-journal-config-repository.php';
require $root . '/includes/class-lunara-journal-prompt-compiler.php';
require $root . '/includes/class-lunara-journal-voice-upgrade.php';

// A live site's active version from before 1.4.0: OpenAI mini at 2,200
// tokens, two-to-three entries, the one-in-three question rule, no exemplars,
// and an edited refinement note and banned list that must survive.
$old = Lunara_Journal_Config_Schema::default_config();
$old['editorial']['voice']['structure'] = array( 'Hook.', 'Context.', 'Specifics.', 'The take.', 'The landing.' );
$old['editorial']['voice']['engagement_close'] = 'Add one engagement question only when the entry has a genuine fork. Expect that to be true for roughly one entry in three.';
$old['editorial']['voice']['current_refinement'] = 'DALTON REFINEMENT NOTE';
$old['editorial']['voice']['banned_phrases'] = array( 'edited banned phrase' );
unset( $old['editorial']['voice']['exemplars'] );
$old['editorial']['selection']['prefer_entries'] = 2;
$old['editorial']['selection']['max_entries'] = 3;
$old['dispatch']['provider'] = 'openai';
$old['dispatch']['models']['claude'] = 'claude-opus-4-5';
$GLOBALS['vu_options'][ Lunara_Journal_Config_Repository::OPTION_VERSIONS ] = array( array(
    'id' => 25, 'config_version' => '1.0.24', 'status' => 'active', 'created_by' => 'wp_admin', 'changelog' => 'Before 1.4.0.', 'config' => $old,
) );
$GLOBALS['vu_options'][ Lunara_Journal_Config_Repository::OPTION_ACTIVE ] = 25;

// 0. Nothing happens until Dispatch 3.4.0 is active (3.3.0 batches three
//     approved pitches and has no Claude stop-reason handling).
vu_check( false === Lunara_Journal_Voice_Upgrade::dispatch_ready() && false === Lunara_Journal_Voice_Upgrade::maybe_apply(), 'The upgrade ran without Dispatch 3.4.0.' );
vu_check( 25 === Lunara_Journal_Config_Repository::get_active_version_id(), 'The active version moved without Dispatch 3.4.0.' );
define( 'LUNARA_DISPATCH_VERSION', '3.4.0' );
vu_check( true === Lunara_Journal_Voice_Upgrade::dispatch_ready(), 'Dispatch 3.4.0 was not recognised.' );

// 1. Nothing happens on a visitor's page view, even with Dispatch ready.
$GLOBALS['vu_context'] = false;
vu_check( false === Lunara_Journal_Voice_Upgrade::maybe_apply(), 'A front-end request ran the upgrade.' );
vu_check( 25 === Lunara_Journal_Config_Repository::get_active_version_id(), 'A front-end request changed the active version.' );
$GLOBALS['vu_context'] = true;

// 2. No Anthropic key: the voice lands now, the provider does not move.
vu_check( false === Lunara_Journal_Voice_Upgrade::claude_key_available(), 'The fixture unexpectedly has a Claude key.' );
$steps = Lunara_Journal_Voice_Upgrade::maybe_apply();
vu_check( array( 'voice' ) === $steps, 'Without a key only the voice step should run.' );
$active = Lunara_Journal_Config_Repository::get_active_version();
$config = Lunara_Journal_Config_Repository::get_active_config();
vu_check( 26 === (int) $active['id'] && 'system' === $active['created_by'] && false !== strpos( $active['changelog'], 'Journal voice 1.4.0' ), 'The voice step is not a normal, attributed Control Plane version.' );
vu_check( 1 === $GLOBALS['vu_activated'], 'Activation hooks did not fire exactly once.' );
vu_check( 1 === $config['editorial']['selection']['max_entries'] && 1 === $config['editorial']['selection']['prefer_entries'], 'The voice step did not move to one story per run.' );
vu_check( 75 === $config['editorial']['selection']['minimum_words'], 'The validator word floor moved; Dalton\'s short hand-written pieces must still publish.' );
vu_check( false !== strpos( $config['editorial']['voice']['engagement_close'], 'It is always there' ), 'The close was not made mandatory.' );
vu_check( 7 === count( $config['editorial']['voice']['structure'] ) && false !== strpos( $config['editorial']['voice']['structure'][0], '300 to 700 words' ), 'The structure was not replaced whole (or spliced onto the old list).' );
vu_check( 2 === count( $config['editorial']['voice']['exemplars'] ), 'The exemplars were not stored.' );
vu_check( 'DALTON REFINEMENT NOTE' === $config['editorial']['voice']['current_refinement'] && array( 'edited banned phrase' ) === $config['editorial']['voice']['banned_phrases'], 'The voice step overwrote Dalton\'s own edits.' );
vu_check( 'openai' === $config['dispatch']['provider'] && 2200 === $config['dispatch']['max_tokens'], 'The provider moved without a key.' );
$state = get_option( Lunara_Journal_Voice_Upgrade::OPTION );
vu_check( ! empty( $state['voice'] ) && empty( $state['claude'] ), 'The step record is wrong after the voice step.' );
vu_check( null === get_option( Lunara_Journal_Voice_Upgrade::LOCK_OPTION, null ), 'The lock was not released.' );
$prompt = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $config );
vu_check( false !== strpos( $prompt, "DALTON'S VOICE ON THE PAGE:" ) && false !== strpos( $prompt, 'Write one entry per run' ), 'The upgraded version does not compile the new voice.' );

// 3. Nothing is due again until a key appears; a held lock blocks a second worker.
vu_check( false === Lunara_Journal_Voice_Upgrade::maybe_apply() && 26 === Lunara_Journal_Config_Repository::get_active_version_id(), 'The voice step ran twice.' );
$GLOBALS['vu_options']['lunara_dispatch_claude_key'] = 'sk-ant-test-key';
$GLOBALS['vu_options'][ Lunara_Journal_Voice_Upgrade::LOCK_OPTION ] = (string) time();
vu_check( false === Lunara_Journal_Voice_Upgrade::maybe_apply() && 26 === Lunara_Journal_Config_Repository::get_active_version_id(), 'A second worker ran past a held lock.' );
$GLOBALS['vu_options'][ Lunara_Journal_Voice_Upgrade::LOCK_OPTION ] = (string) ( time() - 3600 );

// 4. The key arrives: Dispatch moves to Claude Opus 5 with room to think.
$steps = Lunara_Journal_Voice_Upgrade::maybe_apply();
vu_check( array( 'claude' ) === $steps, 'The Claude step did not run once the key appeared (a stale lock must not block it).' );
$config = Lunara_Journal_Config_Repository::get_active_config();
vu_check( 'claude' === $config['dispatch']['provider'] && 'claude-opus-5' === $config['dispatch']['models']['claude'] && 16000 === $config['dispatch']['max_tokens'], 'The Claude step did not set provider, model, and token room.' );
vu_check( 1 === $config['editorial']['selection']['max_entries'], 'The Claude step lost the voice step.' );
vu_check( 27 === Lunara_Journal_Config_Repository::get_active_version_id() && 2 === $GLOBALS['vu_activated'], 'The Claude step was not its own version.' );
vu_check( false === strpos( wp_json_encode( get_option( Lunara_Journal_Config_Repository::OPTION_VERSIONS ) ), 'sk-ant-test-key' ), 'The key leaked into stored configuration.' );

// 5. Both steps are done: a later edit by Dalton stands.
$edited = Lunara_Journal_Config_Repository::get_active_config();
$edited['dispatch']['provider'] = 'openai';
Lunara_Journal_Config_Repository::create_and_activate( $edited, 'Dalton switched back.', 'wp_admin' );
vu_check( false === Lunara_Journal_Voice_Upgrade::maybe_apply(), 'The upgrade ran again after both steps.' );
$config = Lunara_Journal_Config_Repository::get_active_config();
vu_check( 'openai' === $config['dispatch']['provider'] && 2200 === $config['dispatch']['max_tokens'], 'A later edit was undone, or OpenAI kept the Claude token room.' );

// 6. A fresh site with the key already present gets both steps in one version.
$GLOBALS['vu_options'] = array( 'lunara_dispatch_claude_key' => 'sk-ant-test-key' );
$fresh = Lunara_Journal_Config_Schema::default_config();
$fresh['editorial']['selection']['max_entries'] = 3;
$GLOBALS['vu_options'][ Lunara_Journal_Config_Repository::OPTION_VERSIONS ] = array( array( 'id' => 1, 'config_version' => '1.0.0', 'status' => 'active', 'config' => $fresh ) );
$GLOBALS['vu_options'][ Lunara_Journal_Config_Repository::OPTION_ACTIVE ] = 1;
vu_check( array( 'voice', 'claude' ) === Lunara_Journal_Voice_Upgrade::maybe_apply() && 2 === Lunara_Journal_Config_Repository::get_active_version_id(), 'Both steps should land together in one version.' );

// 7. The per-provider cap: Claude keeps its room, everything else stays at 2,200.
$probe = Lunara_Journal_Config_Schema::default_config();
$probe['dispatch']['max_tokens'] = 999999;
foreach ( array( 'claude' => 16000, 'openai' => 2200, 'gemini' => 2200, 'grok' => 2200 ) as $provider => $cap ) {
    $probe['dispatch']['provider'] = $provider;
    vu_check( $cap === Lunara_Journal_Config_Schema::sanitize_config( $probe )['dispatch']['max_tokens'], "The {$provider} token cap is not {$cap}." );
}

// 8. Dispatch reads the active version's house tells, and an activation in
//    this request is what the rest of the request reads.
require $root . '/includes/class-lunara-journal-control-plane.php';
$runtime = Lunara_Journal_Control_Plane::get_dispatch_runtime_config();
vu_check( in_array( 'a testament to', $runtime['house_tells'], true ) && in_array( 'notably,', $runtime['house_tells'], true ) && $runtime['house_tells'] === array_values( array_unique( $runtime['house_tells'] ) ), 'The runtime must carry the banned and cut-on-sight phrases, once each.' );
vu_check( 'claude' === $runtime['provider'] && 16000 === $runtime['max_tokens'], 'The runtime does not reflect the upgraded version.' );
$switched = Lunara_Journal_Config_Repository::get_active_config();
$switched['dispatch']['provider'] = 'openai';
Lunara_Journal_Config_Repository::create_and_activate( $switched, 'Mid-request change.', 'wp_admin' );
Lunara_Journal_Control_Plane::flush_active_config_cache();
vu_check( 'openai' === Lunara_Journal_Control_Plane::get_dispatch_runtime_config()['provider'], 'A flushed cache must read the newly active version.' );
vu_check( false !== strpos( file_get_contents( $root . '/includes/class-lunara-journal-control-plane.php' ), "add_action( 'lunara_journal_control_plane_activated', array( __CLASS__, 'flush_active_config_cache' ), 1 );" ), 'Every activation must flush the request cache.' );
vu_check( false !== strpos( file_get_contents( $root . '/lunara-journal-foundation.php' ), 'Lunara_Journal_Voice_Upgrade::bootstrap();' ), 'The voice move must be bootstrapped.' );

if ( $failures ) {
    fwrite( STDERR, "Voice upgrade runtime failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "Voice upgrade runtime passed.\n";
