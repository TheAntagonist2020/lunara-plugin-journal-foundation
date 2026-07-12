<?php
/**
 * Authoritative Journal Control Plane API, REST routes, and admin UI glue.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Control_Plane {
    private static $active_config_cache = null;
    const MENU_SLUG = 'lunara-journal-control-plane';

    public static function bootstrap() {
        add_action( 'init', array( __CLASS__, 'ensure_bootstrap_state' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
        add_action( 'admin_post_lunara_journal_control_plane_save', array( __CLASS__, 'admin_save' ) );
        add_action( 'admin_post_lunara_journal_control_plane_migrate', array( __CLASS__, 'admin_migrate' ) );
        add_action( 'admin_post_lunara_journal_control_plane_rollback', array( __CLASS__, 'admin_rollback' ) );
        add_action( 'admin_post_lunara_journal_control_plane_sync_notion', array( __CLASS__, 'admin_sync_notion' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
        add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
        add_filter( 'lunara_dispatch_control_plane_runtime', array( __CLASS__, 'filter_dispatch_runtime' ) );
        Lunara_Journal_Notion_Sync::bootstrap();
    }

    public static function activate() {
        self::ensure_bootstrap_state();
    }

    public static function ensure_bootstrap_state() {
        Lunara_Journal_Config_Repository::ensure_default_version();
    }

    public static function is_active() {
        $config = self::get_active_config();
        return ! empty( $config );
    }

    public static function get_active_config() {
        if ( is_array( self::$active_config_cache ) ) {
            return self::$active_config_cache;
        }
        self::$active_config_cache = Lunara_Journal_Config_Repository::get_active_config();
        return self::$active_config_cache;
    }

    public static function get_active_version() {
        return Lunara_Journal_Config_Repository::get_active_version();
    }

    public static function get_dispatch_runtime_config() {
        $config = self::get_active_config();
        return array(
            'protocol_version' => Lunara_Journal_Protocol::VERSION,
            'config_version'   => $config['config_version'] ?? '1.0.0',
            'enabled'          => ! empty( $config['dispatch']['enabled'] ),
            'schedule'         => $config['dispatch']['schedule'] ?? 'daily',
            'target_post_type' => 'journal',
            'post_status'      => 'draft',
            'provider'         => $config['dispatch']['provider'] ?? 'openai',
            'models'           => $config['dispatch']['models'] ?? array(),
            'max_tokens'       => (int) ( $config['dispatch']['max_tokens'] ?? 4096 ),
            'sources'          => $config['sources'] ?? array(),
            'compiled_system_prompt' => Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $config ),
            'compiled_user_directive_prompt' => Lunara_Journal_Prompt_Compiler::dispatch_user_directive_prompt( $config ),
        );
    }

    public static function filter_dispatch_runtime( $runtime ) {
        return self::get_dispatch_runtime_config();
    }

    public static function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=journal',
            'Journal Control Plane',
            'Control Plane',
            'manage_options',
            self::MENU_SLUG,
            array( __CLASS__, 'render_admin_page' )
        );
    }

    public static function register_rest_routes() {
        register_rest_route( 'lunara/v1', '/journal/config/active', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'rest_active_config' ),
            'permission_callback' => array( 'Lunara_Journal_Foundation', 'rest_permissions_check' ),
        ) );
        register_rest_route( 'lunara/v1', '/journal/config/compiled', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'rest_compiled_config' ),
            'permission_callback' => array( 'Lunara_Journal_Foundation', 'rest_permissions_check' ),
        ) );
        register_rest_route( 'lunara/v1', '/journal/config/health', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'rest_health' ),
            'permission_callback' => array( 'Lunara_Journal_Foundation', 'rest_permissions_check' ),
        ) );
    }

    public static function rest_active_config( WP_REST_Request $request ) {
        $config = self::get_active_config();
        return rest_ensure_response( array(
            'active_version' => Lunara_Journal_Config_Repository::get_active_version_id(),
            'config'         => self::public_config( $config ),
            'summary'        => Lunara_Journal_Prompt_Compiler::public_summary( $config ),
        ) );
    }

    public static function rest_compiled_config( WP_REST_Request $request ) {
        $config = self::get_active_config();
        return rest_ensure_response( array(
            'config_version' => $config['config_version'] ?? '1.0.0',
            'dispatch_system_prompt' => Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $config ),
            'dispatch_user_directive_prompt' => Lunara_Journal_Prompt_Compiler::dispatch_user_directive_prompt( $config ),
            'chatgpt_editor_instructions' => Lunara_Journal_Prompt_Compiler::chatgpt_editor_instructions( $config ),
        ) );
    }

    public static function rest_health( WP_REST_Request $request ) {
        $config = self::get_active_config();
        return rest_ensure_response( array(
            'ok'                    => true,
            'control_plane'         => true,
            'protocol'              => Lunara_Journal_Protocol::health(),
            'active_version'        => Lunara_Journal_Config_Repository::get_active_version_id(),
            'summary'               => Lunara_Journal_Prompt_Compiler::public_summary( $config ),
            'configuration_valid'   => Lunara_Journal_Config_Schema::validate_config( $config ),
            'notion_has_credentials'=> Lunara_Journal_Notion_Client::has_credentials(),
            'notion_last_sync'      => get_option( Lunara_Journal_Notion_Client::OPTION_LAST, '' ),
            'notion_last_error'     => get_option( Lunara_Journal_Notion_Client::OPTION_ERROR, '' ),
            'refused_external_activation' => true,
        ) );
    }

    public static function public_config( array $config ) {
        unset( $config['secrets'], $config['tokens'], $config['api_keys'] );
        return $config;
    }

    public static function register_acf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }
        acf_add_local_field_group( array(
            'key' => 'group_lunara_journal_control_plane_provenance',
            'title' => 'LUNARA Journal Control Plane Provenance',
            'fields' => array(
                array( 'key' => 'field_lunara_journal_config_version', 'label' => 'Configuration Version', 'name' => '_lunara_journal_config_version', 'type' => 'text', 'readonly' => 1 ),
                array( 'key' => 'field_lunara_journal_prompt_version', 'label' => 'Prompt Version', 'name' => '_lunara_journal_prompt_version', 'type' => 'text', 'readonly' => 1 ),
                array( 'key' => 'field_lunara_journal_initial_provider', 'label' => 'Initial Provider', 'name' => '_lunara_journal_initial_provider', 'type' => 'text', 'readonly' => 1 ),
                array( 'key' => 'field_lunara_journal_initial_model', 'label' => 'Initial Model', 'name' => '_lunara_journal_initial_model', 'type' => 'text', 'readonly' => 1 ),
                array( 'key' => 'field_lunara_journal_generated_at_gmt', 'label' => 'Generated At GMT', 'name' => '_lunara_journal_generated_at_gmt', 'type' => 'text', 'readonly' => 1 ),
            ),
            'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'journal' ) ) ),
            'position' => 'side',
            'style' => 'default',
            'active' => true,
            'show_in_rest' => 1,
        ) );
    }

    public static function admin_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'lunara-journal-foundation' ) );
        }
        check_admin_referer( 'lunara_journal_control_plane_save' );
        $current = self::get_active_config();
        $config = $current;
        $config['dispatch']['enabled'] = ! empty( $_POST['dispatch_enabled'] );
        $config['dispatch']['schedule'] = isset( $_POST['dispatch_schedule'] ) ? sanitize_key( wp_unslash( $_POST['dispatch_schedule'] ) ) : 'daily';
        $config['dispatch']['provider'] = isset( $_POST['dispatch_provider'] ) ? sanitize_key( wp_unslash( $_POST['dispatch_provider'] ) ) : 'openai';
        $config['dispatch']['max_tokens'] = isset( $_POST['dispatch_max_tokens'] ) ? (int) $_POST['dispatch_max_tokens'] : 4096;
        foreach ( array( 'openai', 'claude', 'gemini', 'grok' ) as $provider ) {
            $field = 'dispatch_model_' . $provider;
            if ( isset( $_POST[ $field ] ) ) {
                $config['dispatch']['models'][ $provider ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            }
        }
        if ( isset( $_POST['editorial_purpose'] ) ) {
            $config['editorial']['purpose'] = sanitize_textarea_field( wp_unslash( $_POST['editorial_purpose'] ) );
        }
        if ( isset( $_POST['voice_summary'] ) ) {
            $config['editorial']['voice']['summary'] = sanitize_textarea_field( wp_unslash( $_POST['voice_summary'] ) );
        }
        if ( isset( $_POST['current_refinement'] ) ) {
            $config['editorial']['voice']['current_refinement'] = sanitize_textarea_field( wp_unslash( $_POST['current_refinement'] ) );
        }
        if ( isset( $_POST['banned_phrases'] ) ) {
            $phrases = preg_split( '/\R+/', wp_unslash( $_POST['banned_phrases'] ) );
            $config['editorial']['voice']['banned_phrases'] = array_values( array_filter( array_map( 'sanitize_text_field', $phrases ) ) );
        }
        if ( isset( $_POST['sources_json'] ) ) {
            $decoded = json_decode( wp_unslash( $_POST['sources_json'] ), true );
            if ( is_array( $decoded ) ) {
                $config['sources'] = $decoded;
            }
        }
        $config['chatgpt']['may_publish'] = ! empty( $_POST['chatgpt_may_publish'] );
        $config['notion']['sync_enabled'] = ! empty( $_POST['notion_sync_enabled'] );
        if ( isset( $_POST['notion_page_id'] ) ) {
            update_option( Lunara_Journal_Notion_Client::OPTION_PAGE_ID, sanitize_text_field( wp_unslash( $_POST['notion_page_id'] ) ), false );
        }
        if ( isset( $_POST['notion_token'] ) && '' !== trim( (string) $_POST['notion_token'] ) ) {
            update_option( Lunara_Journal_Notion_Client::OPTION_TOKEN, sanitize_text_field( wp_unslash( $_POST['notion_token'] ) ), false );
        }
        $changelog = isset( $_POST['changelog'] ) ? sanitize_textarea_field( wp_unslash( $_POST['changelog'] ) ) : 'Control Plane update.';
        $version = Lunara_Journal_Config_Repository::create_and_activate( $config, $changelog, 'wp_admin' );
        if ( is_wp_error( $version ) ) {
            wp_die( esc_html( $version->get_error_message() ) );
        }
        wp_safe_redirect( admin_url( 'edit.php?post_type=journal&page=' . self::MENU_SLUG . '&updated=1' ) );
        exit;
    }

    public static function admin_migrate() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'lunara-journal-foundation' ) ); }
        check_admin_referer( 'lunara_journal_control_plane_migrate' );
        $result = Lunara_Journal_Migration::migrate_current_settings_as_active();
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( admin_url( 'edit.php?post_type=journal&page=' . self::MENU_SLUG . '&migrated=1' ) );
        exit;
    }

    public static function admin_rollback() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'lunara-journal-foundation' ) ); }
        check_admin_referer( 'lunara_journal_control_plane_rollback' );
        $id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
        $result = Lunara_Journal_Config_Repository::clone_prior_as_new_active( $id, 'Rollback cloned from version #' . $id . '.', 'wp_admin' );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( admin_url( 'edit.php?post_type=journal&page=' . self::MENU_SLUG . '&rolled-back=1' ) );
        exit;
    }

    public static function admin_sync_notion() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'lunara-journal-foundation' ) ); }
        check_admin_referer( 'lunara_journal_control_plane_sync_notion' );
        $result = Lunara_Journal_Notion_Client::sync_config( self::get_active_config() );
        $arg = is_wp_error( $result ) ? 'notion-error=1' : 'notion-synced=1';
        wp_safe_redirect( admin_url( 'edit.php?post_type=journal&page=' . self::MENU_SLUG . '&' . $arg ) );
        exit;
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $version = self::get_active_version();
        $config = self::get_active_config();
        $versions = array_reverse( Lunara_Journal_Config_Repository::get_versions() );
        $summary = Lunara_Journal_Prompt_Compiler::public_summary( $config );
        $compiled = Lunara_Journal_Prompt_Compiler::dispatch_system_prompt( $config );
        ?>
        <div class="wrap lunara-control-plane">
            <h1>LUNARA Journal Control Plane</h1>
            <p><strong>Authoritative runtime configuration.</strong> Dispatch, the Fast Journal Desk, validation, and provenance consume this active version. The optional Notion mirror is not part of the GPT operational path. Only WordPress administrators can activate or roll back configuration.</p>
            <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>New Control Plane version activated.</p></div><?php endif; ?>
            <?php if ( isset( $_GET['migrated'] ) ) : ?><div class="notice notice-success"><p>Current Dispatch settings migrated into a new active Control Plane version.</p></div><?php endif; ?>
            <?php if ( isset( $_GET['notion-error'] ) ) : ?><div class="notice notice-warning"><p>Notion sync did not complete. Check the Notion status below.</p></div><?php endif; ?>
            <h2>Active Version</h2>
            <table class="widefat striped" style="max-width:1100px"><tbody>
                <tr><th>Configuration</th><td><?php echo esc_html( $summary['config_version'] ); ?> (version ID <?php echo esc_html( Lunara_Journal_Config_Repository::get_active_version_id() ); ?>)</td></tr>
                <tr><th>Dispatch Runtime</th><td><?php echo esc_html( $summary['provider'] . ' / ' . $summary['schedule'] . ' / journal draft' ); ?></td></tr>
                <tr><th>Sources Enabled</th><td><?php echo esc_html( (string) $summary['sources_enabled'] ); ?></td></tr>
                <tr><th>Notion Mirror</th><td><?php echo ! empty( $config['notion']['sync_enabled'] ) ? 'Enabled' : 'Disabled'; ?>; last sync: <?php echo esc_html( get_option( Lunara_Journal_Notion_Client::OPTION_LAST, 'never' ) ); ?></td></tr>
                <tr><th>GPT Publishing</th><td><?php echo ! empty( $config['chatgpt']['may_publish'] ) ? 'Enabled with explicit publish action' : 'Disabled'; ?></td></tr>
            </tbody></table>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:1100px;margin-top:24px;">
                <?php wp_nonce_field( 'lunara_journal_control_plane_save' ); ?>
                <input type="hidden" name="action" value="lunara_journal_control_plane_save" />
                <h2>Workflow and AI Runtime</h2>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row">Automation</th><td><label><input type="checkbox" name="dispatch_enabled" value="1" <?php checked( ! empty( $config['dispatch']['enabled'] ) ); ?> /> Dispatch automation enabled</label><p class="description">Publication remains manual unless you explicitly use the dedicated GPT publish action.</p></td></tr>
                    <tr><th scope="row">GPT Publishing</th><td><label><input type="checkbox" name="chatgpt_may_publish" value="1" <?php checked( ! empty( $config['chatgpt']['may_publish'] ) ); ?> /> Allow the private LUNARA GPT to publish a single validated Journal entry when you explicitly instruct it to publish.</label><p class="description">This does not enable bulk publishing or scheduling. The draft must pass validation, including the featured-image guard.</p></td></tr>
                    <tr><th scope="row">Schedule</th><td><?php self::select_field( 'dispatch_schedule', $config['dispatch']['schedule'], array( 'daily' => 'Daily', 'twice_daily' => 'Twice Daily', 'every_4_hours' => 'Every 4 Hours', 'every_2_hours' => 'Every 2 Hours' ) ); ?></td></tr>
                    <tr><th scope="row">Provider</th><td><?php self::select_field( 'dispatch_provider', $config['dispatch']['provider'], array( 'openai' => 'OpenAI', 'claude' => 'Claude', 'gemini' => 'Gemini', 'grok' => 'Grok' ) ); ?></td></tr>
                    <tr><th scope="row">Max Tokens</th><td><input type="number" min="1024" max="16000" name="dispatch_max_tokens" value="<?php echo esc_attr( (string) $config['dispatch']['max_tokens'] ); ?>" /></td></tr>
                    <?php foreach ( array( 'openai' => 'OpenAI Model', 'claude' => 'Claude Model', 'gemini' => 'Gemini Model', 'grok' => 'Grok Model' ) as $key => $label ) : ?>
                    <tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><input type="text" class="regular-text" name="dispatch_model_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $config['dispatch']['models'][ $key ] ?? '' ); ?>" /></td></tr>
                    <?php endforeach; ?>
                </tbody></table>

                <h2>Editorial Specification</h2>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row">Purpose</th><td><textarea name="editorial_purpose" rows="4" class="large-text"><?php echo esc_textarea( $config['editorial']['purpose'] ?? '' ); ?></textarea></td></tr>
                    <tr><th scope="row">Voice Summary</th><td><textarea name="voice_summary" rows="4" class="large-text"><?php echo esc_textarea( $config['editorial']['voice']['summary'] ?? '' ); ?></textarea></td></tr>
                    <tr><th scope="row">Current Refinement</th><td><textarea name="current_refinement" rows="5" class="large-text"><?php echo esc_textarea( $config['editorial']['voice']['current_refinement'] ?? '' ); ?></textarea><p class="description">This replaces the old Dispatch voice-refinement field as the authoritative steering note.</p></td></tr>
                    <tr><th scope="row">Banned Phrases</th><td><textarea name="banned_phrases" rows="8" class="large-text code"><?php echo esc_textarea( implode( "\n", $config['editorial']['voice']['banned_phrases'] ?? array() ) ); ?></textarea></td></tr>
                </tbody></table>

                <h2>Sources</h2>
                <p>Edit as JSON during stabilization. The next UI pass can make this a table editor after runtime behavior is verified.</p>
                <textarea name="sources_json" rows="12" class="large-text code"><?php echo esc_textarea( wp_json_encode( $config['sources'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>

                <h2>Optional Notion Mirror</h2>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row">Enable Mirror</th><td><label><input type="checkbox" name="notion_sync_enabled" value="1" <?php checked( ! empty( $config['notion']['sync_enabled'] ) ); ?> /> Sync active configuration one-way to Notion (optional; not used by Fast Journal Desk)</label><p class="description">Notion is a mirror. It never activates production configuration.</p></td></tr>
                    <tr><th scope="row">Notion Page ID</th><td><input type="text" class="regular-text" name="notion_page_id" value="<?php echo esc_attr( get_option( Lunara_Journal_Notion_Client::OPTION_PAGE_ID, '' ) ); ?>" /></td></tr>
                    <tr><th scope="row">Notion Token</th><td><input type="password" class="regular-text" name="notion_token" value="" autocomplete="off" /><p class="description">Leave blank to keep existing token. Tokens are stored separately and are never exported in configuration JSON.</p></td></tr>
                </tbody></table>

                <h2>Activate New Version</h2>
                <p><textarea name="changelog" rows="3" class="large-text" placeholder="Describe why this configuration version is being activated."></textarea></p>
                <?php submit_button( 'Save and Activate New Configuration Version' ); ?>
            </form>

            <hr />
            <h2>Operations</h2>
            <p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
                    <?php wp_nonce_field( 'lunara_journal_control_plane_migrate' ); ?>
                    <input type="hidden" name="action" value="lunara_journal_control_plane_migrate" />
                    <?php submit_button( 'Migrate Current Dispatch Settings', 'secondary', 'submit', false ); ?>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                    <?php wp_nonce_field( 'lunara_journal_control_plane_sync_notion' ); ?>
                    <input type="hidden" name="action" value="lunara_journal_control_plane_sync_notion" />
                    <?php submit_button( 'Sync Notion Mirror Now', 'secondary', 'submit', false ); ?>
                </form>
            </p>

            <h2>Compiled Dispatch Prompt Preview</h2>
            <textarea readonly rows="16" class="large-text code"><?php echo esc_textarea( $compiled ); ?></textarea>

            <h2>Version History</h2>
            <table class="widefat striped" style="max-width:1100px"><thead><tr><th>ID</th><th>Version</th><th>Status</th><th>Created</th><th>Changelog</th><th>Rollback</th></tr></thead><tbody>
            <?php foreach ( $versions as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( (string) $row['id'] ); ?></td>
                    <td><?php echo esc_html( $row['config_version'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $row['status'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $row['created_at_gmt'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $row['changelog'] ?? '' ); ?></td>
                    <td><?php if ( 'active' !== ( $row['status'] ?? '' ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'lunara_journal_control_plane_rollback' ); ?><input type="hidden" name="action" value="lunara_journal_control_plane_rollback" /><input type="hidden" name="version_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>" /><?php submit_button( 'Clone as Rollback', 'small', 'submit', false ); ?></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private static function select_field( $name, $current, array $choices ) {
        echo '<select name="' . esc_attr( $name ) . '">';
        foreach ( $choices as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }
}
