<?php
/** Run: php tests/desk-api-runtime.php */
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['desk_options'] = array();
$GLOBALS['desk_meta'] = array( 42 => array( 'journal_status' => array( 'needs_chatgpt_review' ) ) );
$GLOBALS['desk_logged_in'] = true;
$GLOBALS['desk_session'] = 'cookie-session';
$GLOBALS['desk_cookie_user'] = 7;
$GLOBALS['desk_caps'] = array( 'manage_options' => true, 'edit_others_posts' => true, 'edit_post' => true, 'publish_posts' => true );
$GLOBALS['desk_writes'] = 0;
$GLOBALS['desk_calls'] = array();
$GLOBALS['desk_actions'] = array();

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_Post {
    public $ID = 42;
    public $post_type = 'journal';
    public $post_status = 'draft';
    public $post_title = 'A title & a choice';
    public $post_content = '<p>Original text.</p>';
    public $post_excerpt = 'Original excerpt.';
    public $post_modified_gmt = '2026-09-04 12:00:00';
}
class WP_REST_Response {
    private $data;
    public function __construct( $data ) { $this->data = $data; }
    public function get_data() { return $this->data; }
    public function set_data( $data ) { $this->data = $data; }
    public function header( $name, $value ) {}
    public function get_headers() { return array('X-WP-TotalPages'=>3); }
    public function is_error() { return false; }
}
class WP_REST_Request implements ArrayAccess {
    private $params = array();
    private $headers = array();
    private $json = array();
    private $files = array();
    public function set_file_params($files) { $this->files=$files; }
    public function get_file_params() { return $this->files; }
    private $method;
    private $route;
    public function __construct( $method = 'GET', $route = '/' ) { $this->method = $method; $this->route = $route; }
    public function set_header( $name, $value ) { $this->headers[strtolower( $name )] = $value; }
    public function get_header( $name ) { return $this->headers[strtolower( $name )] ?? ''; }
    public function set_body( $body ) { $this->json = json_decode( $body, true ); }
    public function get_json_params() { return $this->json; }
    public function get_param( $name ) { return $this->params[$name] ?? ( $this->json[$name] ?? null ); }
    public function set_param( $name, $value ) { $this->params[$name] = $value; }
    public function get_method() { return $this->method; }
    public function get_route() { return $this->route; }
    public function offsetExists( $offset ): bool { return isset( $this->params[$offset] ); }
    #[\ReturnTypeWillChange]
    public function offsetGet( $offset ) { return $this->get_param( $offset ); }
    public function offsetSet( $offset, $value ): void { $this->params[$offset] = $value; }
    public function offsetUnset( $offset ): void { unset( $this->params[$offset] ); }
}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }

function desk_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response || is_wp_error( $value ) ? $value : new WP_REST_Response( $value ); }
function is_user_logged_in() { return $GLOBALS['desk_logged_in']; }
function wp_get_session_token() { return $GLOBALS['desk_session']; }
function wp_validate_auth_cookie( $cookie = '', $scheme = '' ) { return $GLOBALS['desk_cookie_user']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid-nonce' === $nonce && 'wp_rest' === $action; }
function current_user_can( $cap, $id = 0 ) { return ! empty( $GLOBALS['desk_caps'][$cap] ); }
function wp_get_current_user() { return (object) array( 'ID' => 7, 'user_login' => 'dalton', 'display_name' => 'Dalton' ); }
function get_current_user_id() { return 7; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['desk_options'] ) ? $GLOBALS['desk_options'][$key] : $default; }
function update_option( $key, $value ) { $GLOBALS['desk_options'][$key] = $value; return true; }
function add_option( $key, $value ) { if ( array_key_exists( $key, $GLOBALS['desk_options'] ) ) { return false; } $GLOBALS['desk_options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['desk_options'][$key] ); return true; }
function current_time() { return '2026-09-04 12:00:00'; }
function wp_slash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) { return $value; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { static $i = 0; return '11111111-1111-4111-8111-' . str_pad( (string) ++$i, 12, '0', STR_PAD_LEFT ); }
function get_post( $id ) { return 42 === (int) $id ? clone $GLOBALS['desk_post'] : null; }
function get_post_status( $post ) { return $post instanceof WP_Post ? $post->post_status : $GLOBALS['desk_post']->post_status; }
function get_post_meta( $id, $key = '', $single = false ) { $meta = $GLOBALS['desk_meta'][$id] ?? array(); return '' === $key ? $meta : ( $single ? ( $meta[$key][0] ?? '' ) : ( $meta[$key] ?? array() ) ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['desk_meta'][$id][$key] = array( $value ); $GLOBALS['desk_writes']++; return true; }
function update_field( $key, $value, $id ) { return update_post_meta( $id, $key, $value ); }
function get_post_thumbnail_id( $id ) { return $GLOBALS['desk_thumbnail'] ?? 9; }
function set_post_thumbnail( $id, $image ) { $GLOBALS['desk_thumbnail'] = $image; $GLOBALS['desk_writes']++; return true; }
function wp_attachment_is_image( $id ) { return in_array( $id, array(9,15), true ); }
function get_post_mime_type( $id ) { return 'image/jpeg'; }
function wp_get_attachment_url( $id ) { return 'https://example.com/image-' . $id . '.jpg'; }
class Lunara_Journal_Image_Guard { public static function clear_cache( $id ) {} }
function clean_post_cache( $id ) {}
function do_action( $hook ) { $GLOBALS['desk_actions'][] = $hook; }
function add_action( $hook, $callback ) {}
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['desk_routes'][$route] = $args; }
function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; }

class Lunara_Journal_Foundation {
    public static function rest_permissions_check( $request ) { $GLOBALS['desk_calls'][] = $request->get_route(); return true; }
    public static function record_bridge_log_entry( $id, $action, $context = array() ) { $GLOBALS['desk_calls'][] = $action; }
    public static function update_bridge_attribution( $id, $action ) {}
    public static function rest_validate_positive_id( $id ) { return (int) $id > 0; }
}
class Lunara_Journal_Fast_Desk {
    public static function invalidate_cache() {}
    public static function rest_open_workspace( $request ) {
        if ( ! empty( $GLOBALS['desk_change_during_read'] ) ) { $GLOBALS['desk_post']->post_content = '<p>A concurrent change while the workspace opens.</p>'; }
        return rest_ensure_response( array( 'workspace' => array( 'id' => 42, 'title' => 'A title &amp; a choice' ), 'validation' => array( 'valid' => true ) ) );
    }
    public static function rest_save_validate( $request ) {
        $GLOBALS['desk_calls'][] = 'save';
        $body = $request->get_json_params();
        if ( isset( $body['content'] ) ) { $GLOBALS['desk_post']->post_content = $body['content']; }
        $GLOBALS['desk_writes']++;
        return rest_ensure_response( array( 'saved' => true, 'post_status' => $GLOBALS['desk_post']->post_status ) );
    }
    public static function rest_publish_draft( $request ) {
        $GLOBALS['desk_calls'][] = 'publish';
        $config = Lunara_Journal_Config_Repository::get_active_config();
        if ( empty( $config['chatgpt']['may_publish'] ) ) { return new WP_Error( 'lunara_publish_disabled', 'Disabled.', array( 'status' => 403 ) ); }
        if ( empty( $request->get_json_params()['confirm_publish_now'] ) ) { return new WP_Error( 'lunara_publish_confirmation_required', 'Confirm.', array( 'status' => 400 ) ); }
        $GLOBALS['desk_post']->post_status = 'publish';
        $GLOBALS['desk_writes']++;
        return rest_ensure_response( array( 'published' => true, 'id' => 42, 'permalink' => 'https://example.com/journal/story/' ) );
    }
}

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-repository.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-desk-api.php';

$GLOBALS['desk_post'] = new WP_Post();
$config = Lunara_Journal_Config_Schema::default_config();
$config['sources'] = array( array( 'id' => 'deadline', 'enabled' => true, 'label' => 'Deadline', 'url' => 'https://deadline.com/feed/', 'max' => 10, 'priority' => 5 ) );
$config['notion']['private_test_token'] = 'never-return-this';
$GLOBALS['desk_options'][Lunara_Journal_Config_Repository::OPTION_VERSIONS] = array( array( 'id' => 1, 'config_version' => '1.0.0', 'status' => 'active', 'config' => $config ) );
$GLOBALS['desk_options'][Lunara_Journal_Config_Repository::OPTION_ACTIVE] = 1;

function desk_request( $body = array(), $method = 'POST' ) {
    $request = new WP_REST_Request( $method, '/lunara/v1/journal/app/drafts/42/save' );
    $request['id'] = 42;
    $request->set_header( 'X-WP-Nonce', 'valid-nonce' );
    $request->set_header( 'Content-Type', 'application/json' );
    $request->set_body( json_encode( $body ) );
    return $request;
}
function desk_error( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }

$request = desk_request();
$GLOBALS['desk_logged_in'] = false;
desk_assert( is_wp_error( Lunara_Journal_Desk_API::session_permissions_check( $request ) ), 'Anonymous requests must fail.' );
$request->set_header( 'Authorization', 'Bearer existing-editor-token' );
desk_assert( is_wp_error( Lunara_Journal_Desk_API::rest_settings( $request ) ), 'Bridge-only requests must not read private settings.' );
$GLOBALS['desk_logged_in'] = true;
$request->set_header( 'Authorization', '' );
$GLOBALS['desk_session'] = '';
desk_assert( is_wp_error( Lunara_Journal_Desk_API::session_permissions_check( $request ) ), 'An application-password login without the cookie session must fail.' );
$GLOBALS['desk_session'] = 'cookie-session';
$GLOBALS['desk_cookie_user'] = 8;
desk_assert( is_wp_error( Lunara_Journal_Desk_API::session_permissions_check( $request ) ), 'The cookie must authenticate the current WordPress user.' );
$GLOBALS['desk_cookie_user'] = 7;
$request->set_header( 'X-WP-Nonce', '' );
desk_assert( is_wp_error( Lunara_Journal_Desk_API::session_permissions_check( $request ) ), 'A session without an explicit REST nonce must fail.' );
$request = desk_request();
$GLOBALS['desk_caps']['manage_options'] = false;
desk_assert( is_wp_error( Lunara_Journal_Desk_API::rest_settings( $request ) ), 'Editors without admin capability cannot read or change workflow settings.' );
$before_editor_calls = $GLOBALS['desk_calls'];
$before_editor_writes = $GLOBALS['desk_writes'];
foreach ( array( 'rest_workspace', 'rest_save', 'rest_reject', 'rest_publish' ) as $operation ) {
    $denied = call_user_func( array( 'Lunara_Journal_Desk_API', $operation ), $request );
    desk_assert( desk_error( $denied, 'lunara_desk_forbidden' ) && 403 === $denied->get_error_data()['status'], 'An editor with edit_post and publish_posts must not access the private app operation ' . $operation . '.' );
}
desk_assert( $before_editor_calls === $GLOBALS['desk_calls'] && $before_editor_writes === $GLOBALS['desk_writes'], 'Rejected non-administrators cannot reach underlying draft operations or cause writes.' );
$GLOBALS['desk_caps']['manage_options'] = true;
$GLOBALS['desk_caps']['edit_post'] = false;
desk_assert( is_wp_error( Lunara_Journal_Desk_API::draft_permissions_check( $request ) ), 'Administrator access must still require permission for the specific draft.' );
$GLOBALS['desk_caps']['edit_post'] = true;

$settings = Lunara_Journal_Desk_API::rest_settings( $request )->get_data();
desk_assert( 1 === $settings['version_id'] && ! isset( $settings['notion'] ) && ! isset( $settings['chatgpt'] ) && ! isset( $settings['dispatch'] ), 'Settings must expose only the safe control subset.' );
desk_assert( false === $settings['publication']['enabled'] && ! empty( $settings['settings_admin_url'] ), 'Disabled publishing must be reported with the existing admin destination.' );
$before = serialize( $GLOBALS['desk_options'] );
$stale_config = Lunara_Journal_Desk_API::rest_save_settings( desk_request( array( 'expected_version_id' => 0, 'voice' => array( 'summary' => 'Do not save.' ) ) ) );
desk_assert( is_wp_error( $stale_config ) && 409 === $stale_config->get_error_data()['status'] && $before === serialize( $GLOBALS['desk_options'] ), 'Stale settings must not create or activate a version.' );
$forbidden = Lunara_Journal_Desk_API::rest_save_settings( desk_request( array( 'expected_version_id' => 1, 'chatgpt' => array( 'may_publish' => true ) ) ) );
desk_assert( is_wp_error( $forbidden ) && $before === serialize( $GLOBALS['desk_options'] ), 'A caller must not expand publishing permissions through settings.' );
$bad_sources = Lunara_Journal_Desk_API::rest_save_settings( desk_request( array( 'expected_version_id' => 1, 'sources' => array() ) ) );
desk_assert( is_wp_error( $bad_sources ) && $before === serialize( $GLOBALS['desk_options'] ), 'Omitting a source is not removal confirmation.' );
$rows = $settings['sources'];
$rows[0]['enabled'] = false;
$rows[] = array( 'id' => '', 'enabled' => true, 'label' => 'IndieWire', 'url' => 'https://www.indiewire.com/feed/', 'max' => 5, 'priority' => 7 );
$saved = Lunara_Journal_Desk_API::rest_save_settings( desk_request( array( 'expected_version_id' => 1, 'voice' => array( 'summary' => 'Talk to a reader.', 'current_refinement' => 'Keep the opening concrete.', 'banned_phrases' => array( 'game-changer' ) ), 'sources' => $rows ) ) );
desk_assert( ! is_wp_error( $saved ), 'Valid bounded settings must save.' );
$data = $saved->get_data();
$active = Lunara_Journal_Config_Repository::get_active_config();
desk_assert( 2 === $data['version_id'] && true === $data['saved'] && count( Lunara_Journal_Config_Repository::get_versions() ) === 2, 'Settings must create a new immutable active version.' );
desk_assert( 'deadline' === $data['sources'][0]['id'] && false === $data['sources'][0]['enabled'] && '' !== $data['sources'][1]['id'], 'Existing IDs must survive; new IDs must be server-owned.' );
desk_assert( false === $active['chatgpt']['may_publish'] && $config['dispatch'] === $active['dispatch'] && $config['notion'] === $active['notion'], 'Voice and source edits must preserve provider, secret-bearing settings, and publishing policy.' );
desk_assert( 'Talk to a reader.' === $active['editorial']['voice']['summary'], 'The actual authoritative voice must change.' );
desk_assert( array( 'game-changer' ) === $active['editorial']['voice']['banned_phrases'], 'Replacing an editable phrase list must not silently re-add defaults.' );
$removed = Lunara_Journal_Desk_API::rest_save_settings( desk_request( array( 'expected_version_id' => 2, 'sources' => array( $data['sources'][1] ), 'removed_source_ids' => array( 'deadline' ), 'voice' => array( 'banned_phrases' => array() ), 'selection' => array( 'skip_rules' => array() ) ) ) );
desk_assert( ! is_wp_error( $removed ), 'An explicitly confirmed source removal must save through the versioned repository.' );
$after_removal = Lunara_Journal_Config_Repository::get_active_config();
desk_assert( 1 === count( $after_removal['sources'] ) && $data['sources'][1]['id'] === $after_removal['sources'][0]['id'] && array() === $after_removal['editorial']['voice']['banned_phrases'] && array() === $after_removal['editorial']['selection']['skip_rules'], 'Confirmed removal and deliberately empty editable lists must persist exactly.' );

$GLOBALS['desk_change_during_read'] = true;
$mixed_snapshot = Lunara_Journal_Desk_API::rest_workspace( desk_request( array(), 'GET' ) );
desk_assert( is_wp_error( $mixed_snapshot ) && 409 === $mixed_snapshot->get_error_data()['status'], 'A workspace must not label stale copy with a newer revision.' );
$GLOBALS['desk_change_during_read'] = false;
$workspace = Lunara_Journal_Desk_API::rest_workspace( desk_request( array(), 'GET' ) )->get_data();
desk_assert( 'A title & a choice' === $workspace['workspace']['title'] && is_string( $workspace['revision'] ), 'Workspace must return a raw editable title and revision.' );
$old_revision = $workspace['revision'];
update_post_meta( 42, 'journal_validation_status', 'passed' );
desk_assert( $old_revision === Lunara_Journal_Desk_API::revision_for_post( 42 ), 'Read-time validation bookkeeping must not invalidate an otherwise unchanged draft.' );
$GLOBALS['desk_post']->post_content = '<p>Changed in another editor.</p>';
$writes = $GLOBALS['desk_writes'];
$stale = Lunara_Journal_Desk_API::rest_save( desk_request( array( 'expected_revision' => $old_revision, 'content' => 'Stale replacement.' ) ) );
desk_assert( is_wp_error( $stale ) && 409 === $stale->get_error_data()['status'] && $writes === $GLOBALS['desk_writes'], 'A stale editor must not overwrite newer content.' );
$revision = Lunara_Journal_Desk_API::revision_for_post( 42 );
$stale_publish = Lunara_Journal_Desk_API::rest_publish( desk_request( array( 'expected_revision' => $old_revision, 'confirm_publish_now' => true ) ) );
desk_assert( is_wp_error( $stale_publish ) && 409 === $stale_publish->get_error_data()['status'] && $writes === $GLOBALS['desk_writes'], 'Publishing must also reject a stale version before calling the underlying action.' );
$save = Lunara_Journal_Desk_API::rest_save( desk_request( array( 'expected_revision' => $revision, 'content' => '<p>Approved replacement.</p>' ) ) );
desk_assert( ! is_wp_error( $save ) && '<p>Approved replacement.</p>' === $GLOBALS['desk_post']->post_content && 'draft' === $GLOBALS['desk_post']->post_status, 'Approved current revision must use Fast Desk save while preserving draft state.' );
desk_assert( $save->get_data()['revision'] !== $revision, 'A successful edit must return its new revision.' );

$GLOBALS['desk_post']->post_status = 'private';
$reject = Lunara_Journal_Desk_API::rest_reject( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ) ) ) );
desk_assert( ! is_wp_error( $reject ) && 'rejected' === get_post_meta( 42, 'journal_status', true ) && 'private' === $GLOBALS['desk_post']->post_status, 'Rejecting must retain private content and only change the editorial workflow state.' );
$GLOBALS['desk_post']->post_status = 'draft';
$disabled = Lunara_Journal_Desk_API::rest_publish( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ), 'confirm_publish_now' => true ) ) );
desk_assert( desk_error( $disabled, 'lunara_publish_disabled' ) && 'draft' === $GLOBALS['desk_post']->post_status, 'The app must preserve the existing disabled publishing gate.' );
$versions = Lunara_Journal_Config_Repository::get_versions();
$versions[count( $versions ) - 1]['config']['chatgpt']['may_publish'] = true;
update_option( Lunara_Journal_Config_Repository::OPTION_VERSIONS, $versions );
$unconfirmed = Lunara_Journal_Desk_API::rest_publish( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ) ) ) );
desk_assert( is_wp_error( $unconfirmed ) && 'draft' === $GLOBALS['desk_post']->post_status, 'An enabled publish endpoint still requires explicit confirmation.' );
$GLOBALS['desk_caps']['publish_posts'] = false;
$denied = Lunara_Journal_Desk_API::rest_publish( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ), 'confirm_publish_now' => true ) ) );
desk_assert( is_wp_error( $denied ) && 'draft' === $GLOBALS['desk_post']->post_status, 'Users without publish capability must not publish.' );
$GLOBALS['desk_caps']['publish_posts'] = true;
$published = Lunara_Journal_Desk_API::rest_publish( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ), 'confirm_publish_now' => true ) ) );
desk_assert( ! is_wp_error( $published ) && true === $published->get_data()['published'], 'An authorized confirmed current draft must use the existing publish action.' );
$writes = $GLOBALS['desk_writes'];
$published_edit = Lunara_Journal_Desk_API::rest_save( desk_request( array( 'expected_revision' => Lunara_Journal_Desk_API::revision_for_post( 42 ), 'title' => 'Change live copy' ) ) );
desk_assert( is_wp_error( $published_edit ) && $writes === $GLOBALS['desk_writes'], 'Published content cannot be changed through draft editor routes.' );

echo "Desk API behavior contracts passed.\n";

$GLOBALS['desk_post']->post_status = 'draft';
$GLOBALS['desk_caps']['upload_files'] = true;
$rev = Lunara_Journal_Desk_API::revision_for_post(42);
$save = Lunara_Journal_Desk_API::rest_save(desk_request(array('expected_revision'=>$rev,'featured_media'=>15,'acf'=>array('journal_image_alt'=>'A new still'))));
desk_assert(!is_wp_error($save) && get_post_thumbnail_id(42)===15, 'A current administrator save must replace the featured image.');
desk_assert(get_post_meta(15,'_wp_attachment_image_alt',true)==='A new still', 'Saved alt text must reach the featured image used by WordPress.');
$writes=$GLOBALS['desk_writes'];
$save=Lunara_Journal_Desk_API::rest_save(desk_request(array('expected_revision'=>$rev,'featured_media'=>9)));
desk_assert(is_wp_error($save) && $writes===$GLOBALS['desk_writes'], 'Stale image choices must be rejected before any write.');
foreach(array(-1, '15', 999, 0) as $bad){
    $save=Lunara_Journal_Desk_API::rest_save(desk_request(array('expected_revision'=>Lunara_Journal_Desk_API::revision_for_post(42),'featured_media'=>$bad,'content'=>'Must not save')));
    desk_assert(is_wp_error($save) && $writes===$GLOBALS['desk_writes'], 'Invalid images must not partially save the article.');
}
$GLOBALS['desk_caps']['upload_files']=false;
$save=Lunara_Journal_Desk_API::rest_save(desk_request(array('expected_revision'=>Lunara_Journal_Desk_API::revision_for_post(42),'featured_media'=>9)));
desk_assert(is_wp_error($save) && $writes===$GLOBALS['desk_writes'], 'Image writes require media permission.');
$GLOBALS['desk_caps']['upload_files']=true;
Lunara_Journal_Desk_API::register_rest_routes();
desk_assert(isset($GLOBALS['desk_routes']['/journal/app/media']), 'Private media browsing and upload must be registered.');
$GLOBALS['desk_caps']['manage_options']=false;
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media(desk_request())), 'Non-admin editors must not browse or upload through the private media route.');
$GLOBALS['desk_caps']['manage_options']=true;
$nonce_request=desk_request(); $nonce_request->set_header('X-WP-Nonce','');
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media($nonce_request)), 'Media requires the administrator REST nonce.');
echo "Desk image safety contracts passed.\n";

function wp_max_upload_size(){ return 10485760; }
function wp_get_image_mime($path){ return $GLOBALS['desk_upload_mime'] ?? 'image/jpeg'; }
function wp_strip_all_tags($value){ return strip_tags($value); }
function rest_do_request($request){
    $GLOBALS['desk_media_proxy']=$request;
    $image=array('id'=>15,'mime_type'=>'image/jpeg','source_url'=>'https://example.com/new.jpg','title'=>array('raw'=>'A still'),'alt_text'=>'A scene','media_details'=>array('width'=>1600,'height'=>900,'sizes'=>array('medium'=>array('source_url'=>'https://example.com/thumb.jpg'))),'description'=>array('raw'=>'Private internal note'));
    return new WP_REST_Response($request->get_method()==='POST' ? $image : array($image));
}
$media_request=desk_request(array(), 'GET');
$media_request->set_param('page','2'); $media_request->set_param('search','new still');
$result=Lunara_Journal_Desk_API::rest_media($media_request);
$images=$result->get_data();
desk_assert($images['images'][0]['id']===15 && $images['images'][0]['thumbnail']==='https://example.com/thumb.jpg' && $images['total_pages']===3, 'Media picker must return selectable images with pagination.');
desk_assert(!isset($images['images'][0]['description']), 'Media picker must not leak private attachment notes.');
desk_assert($GLOBALS['desk_media_proxy']->get_param('media_type')==='image' && $GLOBALS['desk_media_proxy']->get_param('context')==='edit' && $GLOBALS['desk_media_proxy']->get_param('page')===2, 'Media browsing must be bounded to image results with correct paging.');
$media_request->set_param('search',array('bad'));
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media($media_request)), 'Malformed image searches must be rejected.');
$upload=desk_request(array('post'=>42,'status'=>'publish'));
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media($upload)), 'Missing uploads must fail without creating media.');
$upload->set_file_params(array('file'=>array('name'=>'still.jpg','tmp_name'=>'/tmp/upload','error'=>0,'size'=>11*1048576)));
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media($upload)), 'Server upload limits must be enforced.');
$upload->set_file_params(array('file'=>array('name'=>'still.jpg','tmp_name'=>'/tmp/upload','error'=>0,'size'=>1024)));
$GLOBALS['desk_upload_mime']='text/html';
desk_assert(is_wp_error(Lunara_Journal_Desk_API::rest_media($upload)), 'The actual file signature must be an image, regardless of its extension.');
$GLOBALS['desk_upload_mime']='image/jpeg';
$result=Lunara_Journal_Desk_API::rest_media($upload);
desk_assert($result->get_data()['images'][0]['id']===15, 'Successful upload must return a selection without changing the draft.');
desk_assert($GLOBALS['desk_media_proxy']->get_param('post')===null && $GLOBALS['desk_media_proxy']->get_param('status')===null && isset($GLOBALS['desk_media_proxy']->get_file_params()['file']), 'Uploads must forward only the file, never arbitrary post or status mutations.');
echo "Desk media upload and paging contracts passed.\n";
