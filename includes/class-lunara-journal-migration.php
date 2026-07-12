<?php
/**
 * Collects legacy Dispatch settings for the first canonical config.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Migration {
    public static function collect_legacy_settings() {
        $keys = array(
            'lunara_dispatch_enabled',
            'lunara_dispatch_schedule',
            'lunara_dispatch_post_status',
            'lunara_dispatch_post_type',
            'lunara_dispatch_provider',
            'lunara_dispatch_max_tokens',
            'lunara_dispatch_openai_model',
            'lunara_dispatch_claude_model',
            'lunara_dispatch_gemini_model',
            'lunara_dispatch_grok_model',
            'lunara_dispatch_voice_refinement',
            'lunara_dispatch_system_prompt_override',
            'lunara_dispatch_sources',
        );
        $legacy = array();
        foreach ( $keys as $key ) {
            $legacy[ $key ] = get_option( $key, null );
        }
        if ( empty( $legacy['lunara_dispatch_sources'] ) && class_exists( 'Lunara_Dispatch_Sources' ) && method_exists( 'Lunara_Dispatch_Sources', 'all' ) ) {
            $legacy['lunara_dispatch_sources'] = Lunara_Dispatch_Sources::all();
        }
        return $legacy;
    }

    public static function migrate_current_settings_as_active() {
        $legacy = self::collect_legacy_settings();
        $config = Lunara_Journal_Config_Schema::default_config( $legacy );

        if ( ! empty( $legacy['lunara_dispatch_voice_refinement'] ) && is_scalar( $legacy['lunara_dispatch_voice_refinement'] ) ) {
            $config['editorial']['voice']['current_refinement'] = sanitize_textarea_field( (string) $legacy['lunara_dispatch_voice_refinement'] );
        }

        if ( ! empty( $legacy['lunara_dispatch_system_prompt_override'] ) && is_scalar( $legacy['lunara_dispatch_system_prompt_override'] ) ) {
            $config['editorial']['legacy_full_prompt_override_archived'] = sanitize_textarea_field( (string) $legacy['lunara_dispatch_system_prompt_override'] );
        }

        $version = Lunara_Journal_Config_Repository::create_and_activate(
            $config,
            'Migrated current Dispatch settings into the authoritative Journal Control Plane.',
            'migration'
        );

        if ( ! is_wp_error( $version ) ) {
            update_option( Lunara_Journal_Config_Repository::OPTION_MIGRATED, current_time( 'mysql', true ), false );
        }
        return $version;
    }
}
