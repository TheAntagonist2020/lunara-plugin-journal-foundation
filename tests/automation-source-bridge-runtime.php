<?php
/**
 * Runtime contract for the private Source Radar to Dispatch bridge.
 *
 * Run: php tests/automation-source-bridge-runtime.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );

$source_posts = array(
    10 => (object) array( 'ID' => 10, 'post_type' => 'lunara_signal', 'post_status' => 'draft', 'post_title' => 'A useful trade story', 'post_content' => 'Follow the negotiating leverage.', 'post_date' => '2026-08-01 10:00:00' ),
    11 => (object) array( 'ID' => 11, 'post_type' => 'lunara_signal', 'post_status' => 'draft', 'post_title' => 'An idea', 'post_content' => 'Not a URL signal.', 'post_date' => '2026-08-01 09:00:00' ),
    12 => (object) array( 'ID' => 12, 'post_type' => 'lunara_signal', 'post_status' => 'draft', 'post_title' => 'Already handled', 'post_content' => 'Do not dispatch twice.', 'post_date' => '2026-08-01 08:00:00' ),
);
$source_meta = array(
    10 => array(
        '_lunara_automation_type'        => 'source',
        '_lunara_automation_status'      => 'new',
        '_lunara_automation_source_url'  => 'https://example.com/useful-story',
        '_lunara_automation_received_at' => '2026-08-01 15:00:00',
    ),
    11 => array(
        '_lunara_automation_type'   => 'idea',
        '_lunara_automation_status' => 'new',
    ),
    12 => array(
        '_lunara_automation_type'        => 'source',
        '_lunara_automation_status'      => 'triaged',
        '_lunara_automation_source_url'  => 'https://example.com/handled',
    ),
);
$source_options = array();

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function wp_http_validate_url( $value ) { return 0 === strpos( (string) $value, 'https://example.com/' ); }
function wp_parse_url( $value, $component = -1 ) { return parse_url( (string) $value, $component ); }
function get_edit_post_link( $post_id ) { return 'https://example.com/wp-admin/post.php?post=' . (int) $post_id; }
function get_the_title( $post ) { return is_object( $post ) ? $post->post_title : ''; }
function get_post_type( $post_id ) { global $source_posts; return isset( $source_posts[ $post_id ] ) ? $source_posts[ $post_id ]->post_type : false; }
function get_post_meta( $post_id, $key, $single = false ) { global $source_meta; $value = $source_meta[ $post_id ][ $key ] ?? ''; return $single ? $value : array( $value ); }
function update_post_meta( $post_id, $key, $value ) { global $source_meta; $source_meta[ $post_id ][ $key ] = $value; return true; }
function get_option( $key, $default = false ) { global $source_options; return array_key_exists( $key, $source_options ) ? $source_options[ $key ] : $default; }
function update_option( $key, $value ) { global $source_options; $source_options[ $key ] = $value; return true; }
function current_time( $type, $gmt = false ) { return $gmt ? '2026-08-01 15:30:00' : '2026-08-01 10:30:00'; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function maybe_serialize( $value ) { return is_scalar( $value ) ? (string) $value : serialize( $value ); }

function get_posts( $args ) {
    global $source_posts, $source_meta;
    $matches = array();
    foreach ( $source_posts as $post ) {
        if ( ! empty( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
            continue;
        }
        if ( ! empty( $args['post_status'] ) && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
            continue;
        }
        foreach ( $args['meta_query'] ?? array() as $clause ) {
            if ( ( $source_meta[ $post->ID ][ $clause['key'] ] ?? '' ) !== $clause['value'] ) {
                continue 2;
            }
        }
        $matches[] = $post;
    }
    usort( $matches, static function ( $a, $b ) { return strcmp( $b->post_date, $a->post_date ); } );
    return array_slice( $matches, 0, (int) ( $args['posts_per_page'] ?? count( $matches ) ) );
}

require dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-lunara-journal-automation.php';

$items = Lunara_Journal_Automation::dispatch_source_items( 6 );
if ( 1 !== count( $items ) || 10 !== $items[0]['signal_id'] || 'https://example.com/useful-story' !== $items[0]['source_url'] ) {
    fwrite( STDERR, "Source bridge exposed the wrong inbox records.\n" );
    exit( 1 );
}

if ( 0 !== Lunara_Journal_Automation::record_dispatch_source_outcome( array( 10 ), 'network_error', array(), 'run-retry' ) ) {
    fwrite( STDERR, "Source bridge accepted a retryable or unknown terminal outcome.\n" );
    exit( 1 );
}
if ( 'new' !== $source_meta[10]['_lunara_automation_status'] ) {
    fwrite( STDERR, "Rejected outcome removed a Source Radar item from retry.\n" );
    exit( 1 );
}

$updated = Lunara_Journal_Automation::record_dispatch_source_outcome( array( 10, 11, 999 ), 'drafted', array( 501 ), 'run-42' );
if ( 1 !== $updated || 'triaged' !== $source_meta[10]['_lunara_automation_status'] || 'drafted' !== $source_meta[10]['_lunara_automation_dispatch_outcome'] ) {
    fwrite( STDERR, "Source bridge did not record the valid terminal Dispatch outcome.\n" );
    exit( 1 );
}
if ( array( 501 ) !== $source_meta[10]['_lunara_automation_dispatch_post_ids'] || 'new' !== $source_meta[11]['_lunara_automation_status'] ) {
    fwrite( STDERR, "Source bridge crossed signal types or lost draft provenance.\n" );
    exit( 1 );
}
if ( array() !== Lunara_Journal_Automation::dispatch_source_items( 6 ) ) {
    fwrite( STDERR, "Triaged Source Radar item was offered to Dispatch again.\n" );
    exit( 1 );
}

echo "Automation Source Radar bridge runtime passed.\n";
