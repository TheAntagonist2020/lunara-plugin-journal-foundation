<?php
/**
 * Behavioral contracts for strict Control Plane source rows and immutable versions.
 *
 * Run: php tests/control-plane-sources-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['cp_options'] = array();
$GLOBALS['cp_transients'] = array();
$GLOBALS['cp_actions'] = array();
$GLOBALS['cp_option_failures'] = array();
$GLOBALS['cp_user_id'] = 7;
$GLOBALS['cp_uuid_queue'] = array(
	'11111111-1111-4111-8111-111111111111',
	'22222222-2222-4222-8222-222222222222',
);

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function cp_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function cp_method( $class, $method ) {
	cp_assert( method_exists( $class, $method ), $class . '::' . $method . ' must exist.' );
	$reflection = new ReflectionMethod( $class, $method );
	$reflection->setAccessible( true );
	return $reflection;
}

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['cp_options'] ) ? $GLOBALS['cp_options'][ $key ] : $default; }
function update_option( $key, $value ) {
	if ( ! empty( $GLOBALS['cp_option_failures'][ $key ] ) ) {
		$GLOBALS['cp_option_failures'][ $key ]--;
		return false;
	}
	$GLOBALS['cp_options'][ $key ] = $value;
	return true;
}
function current_time() { return '2026-08-29 12:00:00'; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) { return trim( filter_var( (string) $value, FILTER_SANITIZE_URL ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_current_user() { return (object) array( 'user_login' => 'tester' ); }
function get_current_user_id() { return $GLOBALS['cp_user_id']; }
function wp_generate_uuid4() { return array_shift( $GLOBALS['cp_uuid_queue'] ); }
function set_transient( $key, $value, $ttl ) { $GLOBALS['cp_transients'][ $key ] = array( 'value' => $value, 'ttl' => $ttl ); return true; }
function get_transient( $key ) { return isset( $GLOBALS['cp_transients'][ $key ] ) ? $GLOBALS['cp_transients'][ $key ]['value'] : false; }
function delete_transient( $key ) { unset( $GLOBALS['cp_transients'][ $key ] ); return true; }
function do_action( $hook ) { $GLOBALS['cp_actions'][] = array( $hook, array_slice( func_get_args(), 1 ) ); }
function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-migration.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-repository.php';

class Lunara_Journal_Notion_Client {
	const OPTION_PAGE_ID = 'lunara_journal_notion_page_id';
	const OPTION_TOKEN = 'lunara_journal_notion_token';
}
require dirname( __DIR__ ) . '/includes/class-lunara-journal-control-plane.php';

$valid_sources = array(
	array( 'id' => 'deadline', 'enabled' => true, 'label' => 'Deadline', 'url' => 'https://deadline.com/feed', 'max' => 1, 'priority' => 10 ),
	array( 'id' => 'variety', 'enabled' => false, 'label' => 'Variety', 'url' => 'http://variety.com/feed', 'max' => 50, 'priority' => 1 ),
);
$valid_result = Lunara_Journal_Config_Schema::validate_sources( $valid_sources );
cp_assert( ! empty( $valid_result['valid'] ) && empty( $valid_result['errors'] ), 'Valid HTTP(S) rows and range boundaries must pass.' );
cp_assert( 'https://example.com/feed/' === Lunara_Journal_Config_Schema::normalize_source_url( 'HTTPS://Example.COM:443/feed/' ), 'Source URL normalization must lowercase the origin and remove the default port without changing the path.' );
cp_assert( 'http://example.com/' === Lunara_Journal_Config_Schema::normalize_source_url( 'http://Example.com:80/' ), 'Root paths must remain rooted after default-port normalization.' );
cp_assert( 'http://[2001:db8::1]/feed' === Lunara_Journal_Config_Schema::normalize_source_url( 'HTTP://[2001:DB8::1]:80/feed' ), 'Valid bracketed IPv6 source origins must normalize without being discarded.' );

$invalid_rows = array(
	'malformed row' => array( 'not-an-array' ),
	'missing label' => array( array( 'id' => 'x', 'enabled' => true, 'label' => '', 'url' => 'https://example.com', 'max' => 1, 'priority' => 1 ) ),
	'missing URL' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => '', 'max' => 1, 'priority' => 1 ) ),
	'unsafe scheme' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'ftp://example.com/feed', 'max' => 1, 'priority' => 1 ) ),
	'protocol relative URL' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => '//example.com/feed', 'max' => 1, 'priority' => 1 ) ),
	'URL credentials' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://user:pass@example.com/feed', 'max' => 1, 'priority' => 1 ) ),
	'URL fragment' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/feed#private', 'max' => 1, 'priority' => 1 ) ),
	'unsafe URL characters' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/<script>', 'max' => 1, 'priority' => 1 ) ),
	'max below range' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 0, 'priority' => 1 ) ),
	'max above range' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 51, 'priority' => 1 ) ),
	'noninteger max' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => '1.5', 'priority' => 1 ) ),
	'priority below range' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 1, 'priority' => 0 ) ),
	'priority above range' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 1, 'priority' => 11 ) ),
	'noninteger priority' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 1, 'priority' => 'high' ) ),
	'unexpected field' => array( array( 'id' => 'x', 'enabled' => true, 'label' => 'X', 'url' => 'https://example.com/a', 'max' => 1, 'priority' => 1, 'prompt' => 'leak' ) ),
);
foreach ( $invalid_rows as $label => $rows ) {
	$result = Lunara_Journal_Config_Schema::validate_sources( $rows );
	cp_assert( empty( $result['valid'] ) && ! empty( $result['errors'] ), ucfirst( $label ) . ' must be rejected.' );
}

$duplicate_id = $valid_sources;
$duplicate_id[1]['id'] = 'deadline';
cp_assert( empty( Lunara_Journal_Config_Schema::validate_sources( $duplicate_id )['valid'] ), 'Duplicate immutable IDs must be rejected.' );
$duplicate_url = $valid_sources;
$duplicate_url[1]['url'] = 'HTTPS://DEADLINE.COM:443/feed';
cp_assert( empty( Lunara_Journal_Config_Schema::validate_sources( $duplicate_url )['valid'] ), 'Duplicate normalized URLs must be rejected.' );

$prepare = cp_method( 'Lunara_Journal_Control_Plane', 'prepare_source_submission' );
$current_sources = array(
	array( 'id' => 'deadline', 'enabled' => true, 'label' => 'Deadline', 'url' => 'https://deadline.com/feed', 'max' => 10, 'priority' => 5 ),
	array( 'id' => 'variety', 'enabled' => true, 'label' => 'Variety', 'url' => 'https://variety.com/feed', 'max' => 10, 'priority' => 5 ),
);
$submitted = array(
	'row-deadline' => array( 'id' => 'deadline', 'enabled' => '1', 'label' => 'Deadline Film', 'url' => 'HTTPS://Deadline.com:443/film/', 'max' => '12', 'priority' => '4' ),
	'new-1' => array( 'id' => '', 'enabled' => '0', 'label' => 'IndieWire', 'url' => 'https://www.indiewire.com/feed/', 'max' => '8', 'priority' => '7' ),
);
$prepared = $prepare->invoke( null, $submitted, $current_sources, array( 'variety' ) );
cp_assert( ! empty( $prepared['valid'] ) && 2 === count( $prepared['sources'] ), 'A confirmed removal plus one new row must prepare successfully.' );
cp_assert( 'deadline' === $prepared['sources'][0]['id'], 'An existing source ID must remain immutable.' );
cp_assert( 'source-11111111-1111-4111-8111-111111111111' === $prepared['sources'][1]['id'], 'A valid new row must receive a server-generated ID.' );
cp_assert( 'https://deadline.com/film/' === $prepared['sources'][0]['url'], 'Prepared rows must normalize only the origin and preserve the exact source path.' );

$unconfirmed = $prepare->invoke( null, array( 'row-deadline' => $submitted['row-deadline'] ), $current_sources, array() );
cp_assert( empty( $unconfirmed['valid'] ) && in_array( 'removal', array_column( $unconfirmed['errors'], 'field' ), true ), 'Omitted existing rows must require explicit removal confirmation.' );
$unknown = $submitted;
$unknown['row-deadline']['id'] = 'forged-id';
$unknown_result = $prepare->invoke( null, $unknown, $current_sources, array( 'deadline', 'variety' ) );
cp_assert( empty( $unknown_result['valid'] ) && in_array( 'id', array_column( $unknown_result['errors'], 'field' ), true ), 'Unknown nonempty IDs must be rejected rather than accepted as new identities.' );
$nested = $submitted;
$nested['row-deadline']['id'] = array( 'deadline' );
$nested['new-1']['enabled'] = array( '1' );
$nested_result = $prepare->invoke( null, $nested, $current_sources, array( 'deadline', 'variety' ) );
cp_assert( empty( $nested_result['valid'] ) && in_array( 'id', array_column( $nested_result['errors'], 'field' ), true ) && in_array( 'enabled', array_column( $nested_result['errors'], 'field' ), true ), 'Nested forged identity and enabled fields must be rejected.' );
$bad_confirmation = $prepare->invoke( null, array( 'row-deadline' => $submitted['row-deadline'] ), $current_sources, array( array( 'variety' ) ) );
cp_assert( empty( $bad_confirmation['valid'] ) && in_array( 'removal', array_column( $bad_confirmation['errors'], 'field' ), true ), 'Nested removal confirmations must be rejected without a type error.' );

$retain = cp_method( 'Lunara_Journal_Control_Plane', 'retain_source_stage' );
$consume = cp_method( 'Lunara_Journal_Control_Plane', 'consume_source_stage' );
$stage = array(
	'rows' => array( array( 'id' => '', 'enabled' => false, 'label' => 'Bad row', 'url' => 'javascript:private', 'max' => 'bad', 'priority' => 'bad' ) ),
	'errors' => array( array( 'row' => '0', 'field' => 'url', 'message' => 'Use HTTP or HTTPS.' ) ),
	'removed_ids' => array( 'variety' ),
	'notion_token' => 'must-not-be-retained',
);
$retain->invoke( null, $stage, 7 );
cp_assert( 600 === reset( $GLOBALS['cp_transients'] )['ttl'], 'Invalid source stages must expire after ten minutes.' );
cp_assert( false === $consume->invoke( null, 8 ), 'A retained source stage must be bound to its submitting user.' );
$retained = $consume->invoke( null, 7 );
cp_assert( 'Bad row' === $retained['rows'][0]['label'] && ! isset( $retained['notion_token'] ), 'The retained stage must preserve display-safe row input and exclude unrelated secrets.' );
cp_assert( false === $consume->invoke( null, 7 ), 'A retained stage must be consumed only once.' );

$base_config = Lunara_Journal_Config_Schema::default_config();
$base_config['sources'] = $current_sources;
$GLOBALS['cp_options'] = array(
	Lunara_Journal_Config_Repository::OPTION_VERSIONS => array(
		array(
			'id' => 1,
			'config_version' => '1.0.0',
			'status' => 'active',
			'created_at_gmt' => '2026-08-28 12:00:00',
			'created_by' => 'system',
			'activated_at_gmt' => '2026-08-28 12:00:00',
			'activated_by' => 'system',
			'changelog' => 'Initial.',
			'config' => $base_config,
		),
	),
	Lunara_Journal_Config_Repository::OPTION_ACTIVE => 1,
	Lunara_Journal_Notion_Client::OPTION_PAGE_ID => 'old-page',
	Lunara_Journal_Notion_Client::OPTION_TOKEN => 'old-token',
);
$save_submission = cp_method( 'Lunara_Journal_Control_Plane', 'save_admin_submission' );
$invalid_post = array(
	'sources' => array(
		'deadline' => array( 'id' => 'deadline', 'enabled' => '1', 'label' => 'Deadline', 'url' => 'https://deadline.com/feed', 'max' => '10', 'priority' => '11' ),
		'variety' => array( 'id' => 'variety', 'enabled' => '1', 'label' => 'Variety', 'url' => 'https://variety.com/feed', 'max' => '10', 'priority' => '5' ),
	),
	'notion_page_id' => 'new-page-must-not-save',
	'notion_token' => 'new-token-must-not-save',
);
$before_invalid_submission = serialize( $GLOBALS['cp_options'] );
$invalid_submission = $save_submission->invoke( null, $invalid_post, 7 );
cp_assert( is_wp_error( $invalid_submission ) && 'lunara_invalid_sources' === $invalid_submission->get_error_code(), 'Invalid admin source rows must return a field-preserving rejection.' );
cp_assert( $before_invalid_submission === serialize( $GLOBALS['cp_options'] ), 'Rejected admin rows must not change versions, active state, or Notion settings.' );
cp_assert( false !== $consume->invoke( null, 7 ), 'Rejected admin rows must retain a stage for the submitting user.' );

$before = serialize( $GLOBALS['cp_options'] );
$repository_snapshot = $GLOBALS['cp_options'];
$invalid_config = $base_config;
$invalid_config['sources'][0]['priority'] = 11;
$invalid_version = Lunara_Journal_Config_Repository::create_and_activate( $invalid_config, 'Invalid.', 'tester' );
cp_assert( is_wp_error( $invalid_version ) && 'lunara_invalid_config' === $invalid_version->get_error_code(), 'Invalid candidates must fail before version creation.' );
cp_assert( $before === serialize( $GLOBALS['cp_options'] ) && empty( $GLOBALS['cp_actions'] ), 'Invalid candidates must leave versions, active state, options, and activation actions untouched.' );

$valid_config = $base_config;
$valid_config['sources'][0]['label'] = 'Deadline Film';
$GLOBALS['cp_option_failures'][ Lunara_Journal_Config_Repository::OPTION_VERSIONS ] = 1;
$storage_failure = Lunara_Journal_Config_Repository::create_and_activate( $valid_config, 'Storage failure.', 'tester' );
cp_assert( is_wp_error( $storage_failure ) && 'lunara_config_storage_failed' === $storage_failure->get_error_code(), 'A failed immutable-version write must return a storage error.' );
cp_assert( $before === serialize( $GLOBALS['cp_options'] ) && empty( $GLOBALS['cp_actions'] ), 'A failed immutable-version write must preserve the exact repository and emit no activation action.' );

$GLOBALS['cp_option_failures'][ Lunara_Journal_Config_Repository::OPTION_ACTIVE ] = 1;
$activation_failure = Lunara_Journal_Config_Repository::create_and_activate( $valid_config, 'Activation failure.', 'tester' );
cp_assert( is_wp_error( $activation_failure ) && 'lunara_config_activation_failed' === $activation_failure->get_error_code(), 'A failed active-pointer write must return an activation error.' );
cp_assert( $before === serialize( $GLOBALS['cp_options'] ) && empty( $GLOBALS['cp_actions'] ), 'A failed active-pointer write must restore the exact repository and emit no false success action.' );

$concurrent_version = $repository_snapshot[ Lunara_Journal_Config_Repository::OPTION_VERSIONS ][0];
$concurrent_version['id'] = 2;
$concurrent_version['config_version'] = '1.0.1';
$concurrent_version['status'] = 'active';
$concurrent_version['changelog'] = 'Concurrent activation.';
$concurrent_version['config'] = $valid_config;
$GLOBALS['cp_options'][ Lunara_Journal_Config_Repository::OPTION_VERSIONS ][0]['status'] = 'superseded';
$GLOBALS['cp_options'][ Lunara_Journal_Config_Repository::OPTION_VERSIONS ][] = $concurrent_version;
$GLOBALS['cp_options'][ Lunara_Journal_Config_Repository::OPTION_ACTIVE ] = 2;
$concurrent_snapshot = serialize( $GLOBALS['cp_options'] );
$restore_repository = cp_method( 'Lunara_Journal_Config_Repository', 'restore_repository_state' );
$attempted_version = $concurrent_version;
$attempted_version['changelog'] = 'Activation failure.';
$restore_result = $restore_repository->invoke( null, $repository_snapshot[ Lunara_Journal_Config_Repository::OPTION_VERSIONS ], 1, $attempted_version );
cp_assert( false === $restore_result && $concurrent_snapshot === serialize( $GLOBALS['cp_options'] ), 'Failure recovery must not overwrite a concurrent successful activation.' );
$GLOBALS['cp_options'] = $repository_snapshot;

$version = Lunara_Journal_Config_Repository::create_and_activate( $valid_config, 'Valid.', 'tester' );
cp_assert( ! is_wp_error( $version ) && 2 === $version['id'], 'A valid candidate must create exactly the next immutable version.' );
cp_assert( 2 === count( get_option( Lunara_Journal_Config_Repository::OPTION_VERSIONS ) ) && 2 === get_option( Lunara_Journal_Config_Repository::OPTION_ACTIVE ), 'A valid candidate must add exactly one version and make it active.' );
cp_assert( 'superseded' === get_option( Lunara_Journal_Config_Repository::OPTION_VERSIONS )[0]['status'], 'A valid activation must supersede the prior active version.' );
cp_assert( 1 === count( $GLOBALS['cp_actions'] ) && 'lunara_journal_control_plane_activated' === $GLOBALS['cp_actions'][0][0], 'A valid activation must emit exactly one canonical action.' );

$create_method = new ReflectionMethod( 'Lunara_Journal_Config_Repository', 'create_version' );
$activate_method = new ReflectionMethod( 'Lunara_Journal_Config_Repository', 'activate_version' );
cp_assert( $create_method->isPrivate() && $activate_method->isPrivate(), 'Low-level storage and activation methods must be private.' );

$prior_id = 1;
$rollback = Lunara_Journal_Config_Repository::clone_prior_as_new_active( $prior_id, 'Rollback.', 'tester' );
cp_assert( ! is_wp_error( $rollback ) && 3 === $rollback['id'] && 3 === get_option( Lunara_Journal_Config_Repository::OPTION_ACTIVE ), 'Rollback must clone a prior version into a new active ID.' );
cp_assert( 'superseded' === Lunara_Journal_Config_Repository::get_version( $prior_id )['status'], 'Rollback must not reactivate or mutate the historical version.' );

$GLOBALS['cp_options'] = array();
$GLOBALS['cp_actions'] = array();
Lunara_Journal_Config_Repository::ensure_default_version();
Lunara_Journal_Config_Repository::ensure_default_version();
cp_assert( 1 === count( Lunara_Journal_Config_Repository::get_versions() ) && 1 === count( $GLOBALS['cp_actions'] ), 'Default bootstrap must use the one immutable activation path exactly once.' );

echo "Journal Control Plane source runtime passed.\n";
