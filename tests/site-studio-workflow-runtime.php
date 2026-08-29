<?php
/**
 * Behavioral contracts for the redacted, inert Site Studio workflow handoff.
 *
 * Run: php tests/site-studio-workflow-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['ss_filters'] = array();
$GLOBALS['ss_options'] = array();
$GLOBALS['ss_writes'] = 0;

class WP_Error {}
function ss_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function __( $text ) { return $text; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) { return trim( (string) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function current_time() { return '2026-08-29 12:00:00'; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['ss_options'] ) ? $GLOBALS['ss_options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['ss_writes']++; $GLOBALS['ss_options'][ $key ] = $value; return true; }
function do_action() {}
function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['ss_filters'][ $hook ][ $priority ][] = $callback; return true; }
function apply_filters( $hook, $value ) {
	if ( empty( $GLOBALS['ss_filters'][ $hook ] ) ) { return $value; }
	ksort( $GLOBALS['ss_filters'][ $hook ] );
	foreach ( $GLOBALS['ss_filters'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $callback ) { $value = call_user_func( $callback, $value ); }
	}
	return $value;
}

require dirname( __DIR__ ) . '/includes/class-lunara-journal-protocol.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-schema.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-migration.php';
require dirname( __DIR__ ) . '/includes/class-lunara-journal-config-repository.php';

class Lunara_Journal_Control_Plane {
	const MENU_SLUG = 'lunara-journal-control-plane';
	const CAPABILITY = 'manage_options';
	public static function admin_path() { return 'edit.php?post_type=journal&page=' . self::MENU_SLUG; }
	public static function admin_url() { return admin_url( self::admin_path() ); }
}
class Lunara_Journal_Foundation { const VERSION = '1.2.13'; }

ss_assert( file_exists( dirname( __DIR__ ) . '/includes/class-lunara-journal-site-studio.php' ), 'The always-loaded Site Studio integration module must exist.' );
require dirname( __DIR__ ) . '/includes/class-lunara-journal-site-studio.php';

$empty_before = serialize( $GLOBALS['ss_options'] );
$empty_writes_before = $GLOBALS['ss_writes'];
$empty_status = lunara_journal_foundation_workflow_status();
ss_assert( $empty_before === serialize( $GLOBALS['ss_options'] ) && $empty_writes_before === $GLOBALS['ss_writes'], 'Reading workflow status with no repository state must remain strictly read-only.' );
ss_assert( 'needs-attention' === $empty_status['state'] && 0 === $empty_status['count'] && '' === $empty_status['updated_at'], 'An empty repository must produce a generic needs-attention status without bootstrapping state.' );

$config = Lunara_Journal_Config_Schema::default_config();
$config['sources'] = array(
	array( 'id' => 'secret-source', 'enabled' => true, 'label' => 'SECRET SOURCE LABEL', 'url' => 'https://secret-source.example/private-feed', 'max' => 10, 'priority' => 5 ),
);
$config['editorial']['purpose'] = 'SECRET EDITORIAL PURPOSE';
$config['editorial']['voice']['banned_phrases'] = array( 'SECRET BANNED PHRASE' );
$config['compiled_system_prompt'] = 'SECRET COMPILED PROMPT';
$GLOBALS['ss_options'] = array(
	Lunara_Journal_Config_Repository::OPTION_ACTIVE => 9,
	Lunara_Journal_Config_Repository::OPTION_VERSIONS => array(
		array(
			'id' => 9,
			'config_version' => '1.0.8',
			'status' => 'active',
			'created_at_gmt' => '2026-08-29 10:00:00',
			'activated_at_gmt' => '2026-08-29 11:00:00',
			'config' => $config,
		),
	),
	'lunara_journal_notion_page_id' => 'SECRET NOTION ID',
	'lunara_journal_notion_token' => 'SECRET NOTION TOKEN',
	'lunara_journal_notion_last_error' => 'SECRET RAW PROVIDER ERROR',
	'lunara_journal_bridge_access_profiles' => array( 'token' => 'SECRET BRIDGE TOKEN' ),
);

$before = serialize( $GLOBALS['ss_options'] );
$writes_before = $GLOBALS['ss_writes'];
Lunara_Journal_Site_Studio::bootstrap();
ss_assert( $before === serialize( $GLOBALS['ss_options'] ) && $writes_before === $GLOBALS['ss_writes'], 'Registering the optional theme filter must perform no configuration writes.' );
ss_assert( isset( $GLOBALS['ss_filters']['lunara_site_studio_surfaces'] ), 'Foundation must register an inert Site Studio surface contribution.' );

$sentinel = array( 'existing' => array( 'id' => 'existing', 'owner' => 'theme:existing' ) );
$surfaces = apply_filters( 'lunara_site_studio_surfaces', $sentinel );
ss_assert( isset( $surfaces['existing'], $surfaces['journal-workflow'] ), 'The contribution must preserve existing surfaces and add Journal Workflow.' );
$surface = $surfaces['journal-workflow'];
ss_assert( 'plugin:lunara-journal-foundation' === $surface['owner'], 'Journal Foundation must remain the declared workflow owner.' );
ss_assert( 'workflow' === $surface['kind'] && 'manage_options' === $surface['capability'], 'Journal Workflow must be a manage-options workflow handoff.' );
ss_assert( empty( $surface['supports_preview'] ) && '' === $surface['adapter_factory'], 'The plugin handoff must not create a competing preview adapter or settings store.' );
ss_assert( 'edit.php?post_type=journal&page=lunara-journal-control-plane' === $surface['admin_url'] && $surface['admin_url'] === $surface['classic_url'], 'Both destinations must point at the canonical Control Plane.' );
ss_assert( is_callable( $surface['dependency_callback'] ) && is_callable( $surface['status_callback'] ), 'Plugin-only dependency and status callbacks must remain callable without theme functions.' );

$status = lunara_journal_foundation_workflow_status();
$keys = array_keys( $status );
sort( $keys );
$allowed = array( 'action_label', 'count', 'label', 'message', 'state', 'updated_at', 'url' );
sort( $allowed );
ss_assert( $allowed === $keys, 'Workflow status must expose only the exact redacted handoff envelope.' );
ss_assert( 1 === $status['count'] && 'https://example.com/wp-admin/edit.php?post_type=journal&page=lunara-journal-control-plane' === $status['url'], 'Status may expose only the enabled-source count and canonical protected URL.' );
$serialized = serialize( $status );
foreach ( array( 'SECRET SOURCE LABEL', 'secret-source.example', 'SECRET EDITORIAL PURPOSE', 'SECRET BANNED PHRASE', 'SECRET COMPILED PROMPT', 'SECRET NOTION ID', 'SECRET NOTION TOKEN', 'SECRET RAW PROVIDER ERROR', 'SECRET BRIDGE TOKEN', 'lunara_journal_' ) as $secret ) {
	ss_assert( false === strpos( $serialized, $secret ), 'Workflow status leaked forbidden material: ' . $secret );
}
ss_assert( $before === serialize( $GLOBALS['ss_options'] ) && $writes_before === $GLOBALS['ss_writes'], 'Reading redacted status must not mutate canonical state.' );

$dependency = call_user_func( $surface['dependency_callback'], $surface );
ss_assert( is_array( $dependency ) && ! empty( $dependency['available'] ), 'The loaded owner must report an available dependency without theme calls.' );

echo "Journal Site Studio workflow runtime passed.\n";
