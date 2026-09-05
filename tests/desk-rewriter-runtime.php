<?php
/** Unsaved AI proposals: authentication, source boundaries, transport errors, and no writes. */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
    public $code; public $message; public $data;
    public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
class WP_REST_Request {
    public $params; public $headers;
    public function __construct( $params = array(), $headers = array() ) { $this->params = $params; $this->headers = $headers; }
    public function get_param( $key ) { return $this->params[ $key ] ?? null; }
    public function get_json_params() { return $this->params; }
    public function get_header( $key ) { return $this->headers[ strtolower( $key ) ] ?? ''; }
}
class WP_Post { public $ID = 7; public $post_type = 'journal'; public $post_status = 'draft'; public $post_title = 'Original'; public $post_content = '<p>Original copy.</p>'; public $post_excerpt = 'Original excerpt'; }
$GLOBALS['rw_post'] = new WP_Post();
$GLOBALS['rw_logged_in'] = true; $GLOBALS['rw_can_edit'] = true; $GLOBALS['rw_can_manage'] = true; $GLOBALS['rw_cookie'] = 4;
$GLOBALS['rw_options'] = array( 'lunara_dispatch_openai_key' => 'test-server-secret' );
$GLOBALS['rw_writes'] = array(); $GLOBALS['rw_http'] = array(); $GLOBALS['rw_routes'] = array();
$GLOBALS['rw_meta'] = array( 'journal_source_items' => 1, 'journal_source_items_0_source_url' => 'https://deadline.com/reported-story/', 'journal_source_items_0_source_publication' => 'Deadline', 'journal_source_items_0_source_excerpt' => 'Keaton will direct a Western.' );
$GLOBALS['rw_config'] = array( 'config_version' => '1.0.25', 'editorial' => array( 'voice' => array( 'current_refinement' => 'Keep the opening human.' ) ), 'dispatch' => array( 'provider' => 'openai', 'models' => array( 'openai' => 'gpt-5.4-mini', 'claude' => 'claude-opus-4-5', 'gemini' => 'gemini-2.5-pro', 'grok' => 'grok-4' ), 'max_tokens' => 999999 ) );
function rw_assert( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function add_action( $hook, $callback ) { if ( 'rest_api_init' === $hook ) { call_user_func( $callback ); } }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['rw_routes'][ $namespace . $route ] = $args; }
function is_user_logged_in() { return $GLOBALS['rw_logged_in']; }
function get_current_user_id() { return 4; }
function wp_get_session_token() { return $GLOBALS['rw_cookie'] ? 'test-cookie-session' : ''; }
function wp_validate_auth_cookie( $cookie = '', $scheme = '' ) { return $GLOBALS['rw_cookie']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'wp_rest' === $action; }
function current_user_can( $cap, $id = null ) { return 'manage_options' === $cap ? $GLOBALS['rw_can_manage'] : ( 'edit_post' === $cap && $GLOBALS['rw_can_edit'] && 7 === $id ); }
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) { return 7 === $id ? $GLOBALS['rw_post'] : null; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['rw_meta'][ $key ] ?? ''; }
function get_option( $key, $default = false ) { return $GLOBALS['rw_options'][ $key ] ?? $default; }
function update_option( ...$args ) { $GLOBALS['rw_writes'][] = $args; return true; }
function update_post_meta( ...$args ) { $GLOBALS['rw_writes'][] = $args; return true; }
function wp_update_post( ...$args ) { $GLOBALS['rw_writes'][] = $args; return 7; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_url_raw( $v ) { return filter_var( $v, FILTER_VALIDATE_URL ) ? $v : ''; }
function esc_url( $v ) { return htmlspecialchars( $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( $v, ENT_QUOTES ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_kses( $html, $allowed, $protocols = array() ) {
    $html = strip_tags( $html, '<p><em><a><br>' );
    return preg_replace_callback( '/<([a-z]+)\b([^>]*)>/i', function ( $m ) {
        if ( 'a' !== strtolower( $m[1] ) ) { return '<' . strtolower( $m[1] ) . '>'; }
        preg_match( '/href=["\x27]([^"\x27]*)["\x27]/i', $m[2], $href );
        return '<a' . ( isset( $href[1] ) ? ' href="' . $href[1] . '"' : '' ) . '>';
    }, $html );
}
function wp_safe_remote_post( $url, $args ) { $GLOBALS['rw_http'][] = array( $url, $args ); return $GLOBALS['rw_response']; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
class Lunara_Journal_Config_Repository {
    public static function get_active_version_id() { return 25; }
    public static function get_version( $id ) { return $GLOBALS['rw_config'] ? array( 'config' => $GLOBALS['rw_config'] ) : null; }
}
require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-prompt-compiler.php';
$path = dirname( __DIR__ ) . '/includes/class-lunara-journal-desk-rewriter.php';
rw_assert( is_file( $path ), 'The authenticated unsaved rewrite endpoint must exist.' );
require $path;
Lunara_Journal_Desk_Rewriter::bootstrap();
$route = $GLOBALS['rw_routes']['lunara/v1/journal/app/drafts/(?P<id>\d+)/revise'] ?? null;
rw_assert( $route && 'POST' === $route['methods'], 'Only POST registers the draft rewrite route.' );
$params = array( 'id' => 7, 'title' => 'Edited headline', 'content' => '<p>My current unsaved copy.</p>', 'excerpt' => 'Edited excerpt', 'instructions' => 'Put the film before financing.' );
$request = new WP_REST_Request( $params, array( 'x-wp-nonce' => 'valid' ) );
$permission = $route['permission_callback']; $handler = $route['callback'];
rw_assert( true === call_user_func( $permission, $request ), 'An administrator cookie session with valid REST nonce and draft access is authorized.' );
foreach ( array( array(), array( 'x-wp-nonce' => 'wrong' ), array( 'x-wp-nonce' => 'valid', 'authorization' => 'Bearer bridge-token' ) ) as $headers ) {
    rw_assert( is_wp_error( call_user_func( $permission, new WP_REST_Request( $params, $headers ) ) ), 'Missing nonce, bad nonce, and bearer authentication are rejected.' );
}
$GLOBALS['rw_cookie'] = 0;
rw_assert( is_wp_error( call_user_func( $permission, $request ) ), 'A non-cookie identity cannot request an AI rewrite.' );
$GLOBALS['rw_cookie'] = 4; $GLOBALS['rw_can_edit'] = false;
rw_assert( is_wp_error( call_user_func( $permission, $request ) ), 'A session without edit_post permission is rejected.' );
$GLOBALS['rw_can_edit'] = true;
$GLOBALS['rw_can_manage'] = false;
$before_editor_http = count( $GLOBALS['rw_http'] );
rw_assert( is_wp_error( call_user_func( $permission, $request ) ), 'An editor with edit_post but without administrator access cannot request a rewrite.' );
rw_assert( is_wp_error( call_user_func( $handler, $request ) ) && $before_editor_http === count( $GLOBALS['rw_http'] ) && array() === $GLOBALS['rw_writes'], 'The rewrite handler must reject a non-administrator before provider calls or writes.' );
$GLOBALS['rw_can_manage'] = true;
$candidate = array( 'title' => 'A Western worth watching', 'content' => '<p onclick="bad()">A <em>Western</em> with a reason to care.</p>', 'excerpt' => 'A concise complete summary.', 'seo_description' => 'A useful search summary.' );
function rw_response( $text, $status = 200 ) { return array( 'response' => array( 'code' => $status ), 'body' => json_encode( array( 'status' => 'completed', 'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => $text ) ) ) ) ) ) ); }
$GLOBALS['rw_response'] = rw_response( json_encode( $candidate ) );
$result = call_user_func( $handler, $request );
rw_assert( ! is_wp_error( $result ) && $result['candidate']['title'] === $candidate['title'], 'A valid provider response yields an unsaved candidate.' );
rw_assert( false === strpos( $result['candidate']['content'], 'onclick' ), 'Unsafe generated attributes cannot enter the candidate.' );
rw_assert( false !== strpos( $result['candidate']['content'], 'https://deadline.com/reported-story/' ), 'The actual source ledger remains attributed in proposed copy.' );
rw_assert( '1.0.25' === $result['voice_version'] && 'openai' === $result['provider'], 'The response carries effective generation provenance.' );
$sent = json_decode( $GLOBALS['rw_http'][0][1]['body'], true );
rw_assert( false !== strpos( $sent['input'], 'My current unsaved copy.' ) && false !== strpos( $sent['input'], 'Keaton will direct a Western.' ), 'The model receives unsaved edits and server-side source evidence together.' );
rw_assert( false !== strpos( $sent['instructions'], 'Keep the opening human.' ), 'Current canonical voice refinement reaches the rewrite request.' );
rw_assert( false !== strpos( $sent['instructions'], 'Never reuse a template across entries.' ) && false !== strpos( $sent['instructions'], 'Worn-lightly expertise.' ), 'Sparse stored configuration receives normalized canonical headline and voice guidance before compilation.' );
rw_assert( $sent['max_output_tokens'] <= 2200 && $GLOBALS['rw_http'][0][1]['timeout'] <= 60 && false === $sent['store'], 'Requests bound tokens and latency and disable provider storage.' );
rw_assert( false === strpos( json_encode( $result ), 'test-server-secret' ), 'Provider credentials never appear in client output.' );
foreach ( array( 'pending', 'private', 'auto-draft' ) as $editable_status ) {
    $GLOBALS['rw_post']->post_status = $editable_status;
    rw_assert( ! is_wp_error( call_user_func( $handler, $request ) ), 'An editable ' . $editable_status . ' Journal entry can receive an unsaved rewrite.' );
}
$GLOBALS['rw_post']->post_status = 'draft';
$GLOBALS['rw_meta']['journal_bridge_locked'] = '1';
$before_locked = count( $GLOBALS['rw_http'] );
rw_assert( is_wp_error( call_user_func( $handler, $request ) ) && count( $GLOBALS['rw_http'] ) === $before_locked, 'A locked Journal entry is rejected before a provider call, even without an expected revision.' );
unset( $GLOBALS['rw_meta']['journal_bridge_locked'] );
$candidate['content'] = '<p>See <a href="https://invented.test/news">this report</a>.</p>';
$GLOBALS['rw_response'] = rw_response( json_encode( $candidate ) );
rw_assert( is_wp_error( call_user_func( $handler, $request ) ), 'A generated source URL outside the actual ledger is rejected.' );
$candidate['content'] = '<p>A source <a href=/made-up-report>link</a>.</p>';
$GLOBALS['rw_response'] = rw_response( json_encode( $candidate ) );
rw_assert( is_wp_error( call_user_func( $handler, $request ) ), 'Unquoted relative links cannot bypass ledger validation.' );
$candidate['content'] = '<p>The film interests me.</p>';
$GLOBALS['rw_response'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'status' => 'incomplete', 'output' => array( array( 'content' => array( array( 'type' => 'output_text', 'text' => json_encode( $candidate ) ) ) ) ) ) ) );
rw_assert( 'lunara_rewrite_incomplete' === call_user_func( $handler, $request )->get_error_code(), 'Even parseable JSON from an incomplete response is rejected.' );
$saved_config = $GLOBALS['rw_config'];
$provider_fixtures = array(
    'claude' => array( 'stop_reason' => 'end_turn', 'content' => array( array( 'type' => 'text', 'text' => json_encode( $candidate ) ) ) ),
    'gemini' => array( 'candidates' => array( array( 'finishReason' => 'STOP', 'content' => array( 'parts' => array( array( 'text' => json_encode( $candidate ) ) ) ) ) ) ),
    'grok' => array( 'choices' => array( array( 'finish_reason' => 'stop', 'message' => array( 'content' => json_encode( $candidate ) ) ) ) ),
);
foreach ( $provider_fixtures as $provider => $fixture ) {
    $GLOBALS['rw_config']['dispatch']['provider'] = $provider;
    $GLOBALS['rw_options'][ 'lunara_dispatch_' . $provider . '_key' ] = 'test-alternate-secret';
    $GLOBALS['rw_response'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $fixture ) );
    $alternative = call_user_func( $handler, $request );
    rw_assert( ! is_wp_error( $alternative ) && $provider === $alternative['provider'] && $alternative['candidate']['title'] === $candidate['title'], 'The configured ' . $provider . ' provider yields a real validated candidate without switching provider.' );
}
$GLOBALS['rw_config'] = $saved_config;
foreach ( array( 'not json', '{"title":"Missing body"}', json_encode( array( 'title' => array(), 'content' => '<p>x</p>', 'excerpt' => 'x', 'seo_description' => 'x' ) ) ) as $text ) {
    $GLOBALS['rw_response'] = rw_response( $text );
    rw_assert( is_wp_error( call_user_func( $handler, $request ) ), 'Malformed or incomplete output never becomes a fake rewrite.' );
}
$GLOBALS['rw_response'] = new WP_Error( 'http_request_failed', 'timeout with test-server-secret' );
$error = call_user_func( $handler, $request );
rw_assert( is_wp_error( $error ) && false === strpos( $error->get_error_message(), 'test-server-secret' ), 'Transport failures are actionable and redacted.' );
$GLOBALS['rw_response'] = rw_response( 'provider-secret-detail', 401 );
rw_assert( 'lunara_rewrite_auth' === call_user_func( $handler, $request )->get_error_code(), 'Credential rejection returns the actionable authentication error.' );
unset( $GLOBALS['rw_options']['lunara_dispatch_openai_key'] );
$before = count( $GLOBALS['rw_http'] );
rw_assert( 'lunara_rewrite_unavailable' === call_user_func( $handler, $request )->get_error_code() && $before === count( $GLOBALS['rw_http'] ), 'Missing credentials return unavailable without an HTTP call.' );
$GLOBALS['rw_options']['lunara_dispatch_openai_key'] = 'test-server-secret';
$GLOBALS['rw_post']->post_status = 'publish';
rw_assert( is_wp_error( call_user_func( $handler, $request ) ), 'Published articles cannot enter the draft rewrite operation.' );
$GLOBALS['rw_post']->post_status = 'draft';
$GLOBALS['rw_config'] = null;
rw_assert( is_wp_error( call_user_func( $handler, $request ) ), 'No active configuration fails without creating defaults.' );
rw_assert( array() === $GLOBALS['rw_writes'], 'Success and all failures must leave WordPress posts and settings untouched.' );
echo "Desk rewriter runtime checks passed.\n";
