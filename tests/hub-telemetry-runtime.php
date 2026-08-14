<?php
/**
 * Runtime contract for the private Hub/Desk Dispatch 3.2.5 telemetry surface.
 *
 * Run: php tests/hub-telemetry-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'LUNARA_DISPATCH_VERSION', '3.2.5' );

$GLOBALS['hub_options'] = array(
    'lunara_dispatch_max_tokens'  => 4096,
    'lunara_dispatch_openai_model'=> 'gpt-4.1',
    'lunara_dispatch_last_run_report' => array(
        'timestamp_gmt'         => '2026-08-14 19:00:00',
        'success'               => true,
        'message'               => '<b>Three drafts created.</b>',
        'created'               => 3,
        'imported'              => 3,
        'source_radar_items'    => 2,
        'deferred_source_items' => 4,
        'ai_fallback_used'      => true,
        'ai_error_code'         => 'openai-billing<script>',
        'api_key'               => 'must-not-escape',
        'prompt'                => 'must-not-escape',
        'source_body'           => 'must-not-escape',
        'ai_usage'              => array(
            'provider'            => 'openai',
            'requested_model'     => 'gpt-4.1',
            'effective_model'     => 'gpt-5.4-mini',
            'max_output_tokens'   => 2200,
            'input_tokens'        => 1800,
            'cached_input_tokens' => 300,
            'output_tokens'       => 640,
            'estimated_cost_usd'  => '0.00432149',
            'response_id'         => 'resp_private',
            'api_key'             => 'must-not-escape',
        ),
    ),
);
$GLOBALS['hub_meta'] = array();
$GLOBALS['hub_scheduled'] = array(
    'lunara_dispatch_cron'             => 1770000000,
    'lunara_dispatch_manual_requested' => 1770000300,
);
$GLOBALS['hub_transients'] = array( 'lunara_dispatch_lock' => 'worker' );

function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['hub_options'] ) ? $GLOBALS['hub_options'][ $key ] : $default;
}
function wp_next_scheduled( $hook ) {
    return isset( $GLOBALS['hub_scheduled'][ $hook ] ) ? $GLOBALS['hub_scheduled'][ $hook ] : false;
}
function get_transient( $key ) {
    return isset( $GLOBALS['hub_transients'][ $key ] ) ? $GLOBALS['hub_transients'][ $key ] : false;
}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) ); }
function get_post_meta( $post_id, $key, $single = false ) {
    unset( $single );
    return isset( $GLOBALS['hub_meta'][ $post_id ][ $key ] ) ? $GLOBALS['hub_meta'][ $post_id ][ $key ] : '';
}
function get_field( $field, $post_id ) { return get_post_meta( $post_id, $field, true ); }
function wp_get_post_terms() { return array( 'Signal' ); }
function get_the_title() { return 'Fallback Draft'; }
function get_post_modified_time() { return '2026-08-14T19:01:00+00:00'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }

class WP_Post {
    public $ID = 101500;
    public $post_excerpt = 'An editable source packet.';
}

final class Lunara_Dispatch_Plugin {
    const REPORT_OPTION     = 'lunara_dispatch_last_run_report';
    const CRON_HOOK         = 'lunara_dispatch_cron';
    const MANUAL_CRON_HOOK  = 'lunara_dispatch_manual_requested';
    const LOCK_KEY          = 'lunara_dispatch_lock';
    const MAX_ITEMS_PER_RUN = 3;
}

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';

final class Lunara_Journal_Control_Plane {
    public static function get_dispatch_runtime_config() {
        return array(
            'enabled'    => true,
            'provider'   => 'openai',
            'models'     => array( 'openai' => 'gpt-5.4-mini' ),
            'max_tokens' => 2200,
        );
    }
}

final class Lunara_Journal_Image_Guard {
    public static function inspect() {
        return array(
            'status' => 'ready', 'attached' => true, 'usable' => true,
            'preferred_quality' => true, 'dimensions' => '1920x1080',
            'aspect_ratio' => 1.7778, 'warnings' => array(), 'errors' => array(),
        );
    }
}

require dirname( __DIR__ ) . '/includes/class-lunara-journal-fast-desk.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-automation.php';

$failures = array();
function hub_assert( $condition, $message ) {
    global $failures;
    if ( ! $condition ) {
        $failures[] = $message;
    }
}

$defaults = Lunara_Journal_Config_Schema::default_config();
hub_assert( 2200 === $defaults['dispatch']['max_tokens'], 'Legacy token configuration was not capped at 2,200.' );
hub_assert( 'gpt-5.4-mini' === $defaults['dispatch']['models']['openai'], 'Unsupported legacy OpenAI model was not normalized.' );

$unsafe = $defaults;
$unsafe['dispatch']['max_tokens'] = 999999;
$unsafe['dispatch']['models']['openai'] = 'gpt-4.1';
$safe = Lunara_Journal_Config_Schema::sanitize_config( $unsafe );
hub_assert( 2200 === $safe['dispatch']['max_tokens'], 'Sanitized token cap exceeded 2,200.' );
hub_assert( 'gpt-5.4-mini' === $safe['dispatch']['models']['openai'], 'Sanitized OpenAI model escaped the allowlist.' );
$unsafe['dispatch']['models']['openai'] = 'gpt-5.4-nano';
$safe_nano = Lunara_Journal_Config_Schema::sanitize_config( $unsafe );
hub_assert( 'gpt-5.4-nano' === $safe_nano['dispatch']['models']['openai'], 'Allowed nano model was not preserved.' );

$fast_method = new ReflectionMethod( 'Lunara_Journal_Fast_Desk', 'dispatch_state' );
$fast_method->setAccessible( true );
$automation_method = new ReflectionMethod( 'Lunara_Journal_Automation', 'dispatch_state' );
$automation_method->setAccessible( true );

foreach ( array( 'fast_desk' => $fast_method->invoke( null ), 'automation' => $automation_method->invoke( null ) ) as $surface => $state ) {
    hub_assert( 'openai' === $state['runtime']['provider'], $surface . ' lost the active runtime provider.' );
    hub_assert( 'gpt-5.4-mini' === $state['runtime']['model'], $surface . ' lost the active runtime model.' );
    hub_assert( 2200 === $state['runtime']['max_output_tokens'], $surface . ' returned the wrong runtime token cap.' );
    hub_assert( 3 === $state['runtime']['source_budget'], $surface . ' returned the wrong source budget.' );
    hub_assert( 3 === $state['last_run']['processed_source_items'], $surface . ' returned the wrong processed-source count.' );
    hub_assert( 4 === $state['last_run']['deferred_source_items'], $surface . ' returned the wrong deferred-source count.' );
    hub_assert( 3 === $state['last_run']['source_packet_drafts'], $surface . ' returned the wrong source-packet count.' );
    hub_assert( true === $state['last_run']['fallback_used'], $surface . ' lost the fallback marker.' );
    hub_assert( true === $state['last_run']['usage_reported'], $surface . ' failed to mark supplied usage as reported.' );
    hub_assert( 'gpt-5.4-mini' === $state['last_run']['effective_model'], $surface . ' lost the effective model.' );
    hub_assert( 0.004321 === $state['last_run']['estimated_cost_usd'], $surface . ' did not bound and normalize the cost estimate.' );
    hub_assert( 'openai-billingscript' === $state['last_run']['error_code'], $surface . ' did not sanitize the error code.' );
    hub_assert( 'Three drafts created.' === $state['last_run']['message'], $surface . ' did not sanitize the report message.' );
    $encoded = json_encode( $state );
    foreach ( array( 'must-not-escape', 'resp_private', 'source_body', 'api_key', 'prompt' ) as $secret ) {
        hub_assert( false === strpos( $encoded, $secret ), $surface . ' exposed disallowed report data: ' . $secret );
    }
}

$GLOBALS['hub_meta'][101500] = array(
    '_lunara_journal_initial_provider' => 'source_packet',
    '_lunara_journal_prompt_version'   => 'source-packet-v1',
    'journal_validation_status'        => 'unchecked',
    'journal_status'                   => 'needs_chatgpt_review',
    'journal_ready_for_review'         => 0,
    'journal_source_items'             => array( array( 'source_url' => 'https://example.test/story' ) ),
    'journal_seo_description'          => 'A safe source packet awaiting Dalton.',
);
$draft_method = new ReflectionMethod( 'Lunara_Journal_Fast_Desk', 'draft_summary' );
$draft_method->setAccessible( true );
$draft = $draft_method->invoke( null, new WP_Post() );
hub_assert( 'source_packet' === $draft['generation']['mode'], 'Draft summary did not expose source-packet generation mode.' );
hub_assert( true === $draft['generation']['source_packet'], 'Draft summary did not expose the source-packet marker.' );

$GLOBALS['hub_options']['lunara_dispatch_last_run_report'] = array( 'created' => 1, 'imported' => 2 );
$legacy = $automation_method->invoke( null );
hub_assert( '' === $legacy['last_run']['effective_model'], 'Older reports must receive empty telemetry defaults.' );
hub_assert( false === $legacy['last_run']['fallback_used'], 'Older reports must default fallback state to false.' );
hub_assert( false === $legacy['last_run']['usage_reported'], 'Older reports must identify usage as not reported.' );
hub_assert( null === $legacy['last_run']['input_tokens'], 'Older reports must not fabricate zero input tokens.' );
hub_assert( null === $legacy['last_run']['output_tokens'], 'Older reports must not fabricate zero output tokens.' );
hub_assert( null === $legacy['last_run']['estimated_cost_usd'], 'Older reports must not fabricate a free run.' );
hub_assert( null === $legacy['last_run']['deferred_source_items'], 'Older reports must not fabricate a zero deferred count.' );

$GLOBALS['hub_options']['lunara_dispatch_last_run_report'] = array(
    'created'  => 1,
    'imported' => 1,
    'ai_usage' => array( 'estimated_cost_usd' => 0.0 ),
);
$reported_zero = $automation_method->invoke( null );
hub_assert( true === $reported_zero['last_run']['usage_reported'], 'A supplied zero cost must still count as reported usage.' );
hub_assert( 0.0 === $reported_zero['last_run']['estimated_cost_usd'], 'A genuine reported zero cost must be preserved.' );
hub_assert( null === $reported_zero['last_run']['input_tokens'], 'Missing tokens must remain unreported even when cost is present.' );

if ( ! empty( $failures ) ) {
    fwrite( STDERR, "Hub telemetry runtime failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Hub telemetry runtime passed.\n";
