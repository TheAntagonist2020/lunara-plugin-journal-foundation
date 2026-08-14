<?php
/**
 * Runtime contract for post-specific Needs Attention alert lifecycle.
 *
 * Run: php tests/automation-attention-runtime.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'LUNARA_IFTTT_WEBHOOK_KEY', 'runtime-test-webhook-key' );

$GLOBALS['attention_posts'] = array(
    700 => array( 'post_type' => 'journal', 'title' => 'A Journal Draft' ),
);
$GLOBALS['attention_meta'] = array();
$GLOBALS['attention_options'] = array(
    'lunara_journal_automation_enabled' => '1',
);
$GLOBALS['attention_scheduled'] = array();
$GLOBALS['attention_http'] = array();

class WP_Error {
    private $code;
    private $message;
    public function __construct( $code = '', $message = '' ) {
        $this->code = $code;
        $this->message = $message;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function maybe_serialize( $value ) { return is_scalar( $value ) ? (string) $value : serialize( $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function current_time( $type, $gmt = false ) { return $gmt ? '2026-08-14 22:00:00' : '2026-08-14 17:00:00'; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function esc_url_raw( $value, $protocols = null ) { unset( $protocols ); return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function get_post_type( $post_id ) { return $GLOBALS['attention_posts'][ $post_id ]['post_type'] ?? false; }
function get_the_title( $post_id ) { return $GLOBALS['attention_posts'][ $post_id ]['title'] ?? ''; }
function get_edit_post_link( $post_id, $context = '' ) { unset( $context ); return 'https://example.test/wp-admin/post.php?post=' . absint( $post_id ); }
function get_post_meta( $post_id, $key, $single = false ) {
    $value = $GLOBALS['attention_meta'][ $post_id ][ $key ] ?? '';
    return $single ? $value : array( $value );
}
function update_post_meta( $post_id, $key, $value ) {
    $GLOBALS['attention_meta'][ $post_id ][ $key ] = $value;
    return true;
}
function delete_post_meta( $post_id, $key ) {
    unset( $GLOBALS['attention_meta'][ $post_id ][ $key ] );
    return true;
}
function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['attention_options'] ) ? $GLOBALS['attention_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
    unset( $autoload );
    $GLOBALS['attention_options'][ $key ] = $value;
    return true;
}
function wp_next_scheduled( $hook, $args = array() ) {
    foreach ( $GLOBALS['attention_scheduled'] as $scheduled ) {
        if ( $hook === $scheduled['hook'] && $args === $scheduled['args'] ) {
            return $scheduled['timestamp'];
        }
    }
    return false;
}
function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
    unset( $wp_error );
    $GLOBALS['attention_scheduled'][] = array(
        'timestamp' => $timestamp,
        'hook'      => $hook,
        'args'      => $args,
    );
    return true;
}
function wp_safe_remote_post( $url, $args ) {
    $GLOBALS['attention_http'][] = array( 'url' => $url, 'args' => $args );
    return array( 'response' => array( 'code' => 200 ) );
}
function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['response']['code'] ?? 0 ); }

require dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-lunara-journal-automation.php';

$failures = array();
function attention_assert( $condition, $message ) {
    global $failures;
    if ( ! $condition ) {
        $failures[] = $message;
    }
}
function attention_reset() {
    $GLOBALS['attention_meta'] = array();
    $GLOBALS['attention_options'] = array( 'lunara_journal_automation_enabled' => '1' );
    $GLOBALS['attention_scheduled'] = array();
    $GLOBALS['attention_http'] = array();
}
function attention_set_validation( $status, $report = array( 'errors' => array( 'Missing image.' ) ) ) {
    update_post_meta( 700, 'journal_validation_status', $status );
    update_post_meta( 700, 'journal_validation_report', $report );
    Lunara_Journal_Automation::handle_validation_meta_change( 1, 700, 'journal_validation_status', $status );
}
function attention_run_next() {
    $scheduled = array_shift( $GLOBALS['attention_scheduled'] );
    if ( ! $scheduled ) {
        return false;
    }
    Lunara_Journal_Automation::send_scheduled_event( $scheduled['args'][0], $scheduled['args'][1] );
    return true;
}

// A draft that recovers before cron runs must not emit a stale webhook.
attention_reset();
attention_set_validation( 'failed' );
attention_assert( 1 === count( $GLOBALS['attention_scheduled'] ), 'Failed validation did not queue one attention event.' );
attention_set_validation( 'passed' );
attention_assert(
    ! isset( $GLOBALS['attention_meta'][700]['_lunara_automation_last_attention_signature'] ),
    'Recovered validation did not clear the attention signature.'
);
attention_run_next();
attention_assert( 0 === count( $GLOBALS['attention_http'] ), 'A recovered draft emitted a stale Needs Attention webhook.' );

// Repeated observation of the same persistent failure must remain deduplicated.
attention_reset();
attention_set_validation( 'failed' );
attention_set_validation( 'failed' );
attention_assert( 1 === count( $GLOBALS['attention_scheduled'] ), 'An unchanged persistent failure queued duplicate attention events.' );
attention_run_next();
attention_assert( 1 === count( $GLOBALS['attention_http'] ), 'A persistent validation failure did not emit exactly one webhook.' );

// Recovery clears the dedupe signature so the same real failure can alert again.
attention_reset();
attention_set_validation( 'failed' );
attention_run_next();
attention_set_validation( 'passed' );
attention_set_validation( 'failed' );
attention_assert( 1 === count( $GLOBALS['attention_scheduled'] ), 'The same failure did not re-arm after a genuine recovery.' );
attention_run_next();
attention_assert( 2 === count( $GLOBALS['attention_http'] ), 'A post-recovery validation failure did not emit a fresh webhook.' );

// Dispatch-run failures have no post-specific validation context and must send.
attention_reset();
Lunara_Journal_Automation::handle_dispatch_report_update(
    array(),
    array(
        'success'       => false,
        'message'       => 'Provider transport failed.',
        'created'       => 0,
        'imported'      => 0,
        'timestamp_gmt' => '2026-08-14 22:00:00',
    ),
    'lunara_dispatch_last_run_report'
);
attention_assert( 1 === count( $GLOBALS['attention_scheduled'] ), 'Dispatch failure did not queue its independent attention event.' );
attention_run_next();
attention_assert( 1 === count( $GLOBALS['attention_http'] ), 'Post-specific stale-alert protection suppressed a Dispatch failure.' );

if ( ! empty( $failures ) ) {
    fwrite( STDERR, "Automation attention runtime failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Automation attention runtime passed.\n";
