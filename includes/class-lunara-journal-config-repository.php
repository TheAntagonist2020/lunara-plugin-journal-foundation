<?php
/**
 * Immutable active/draft configuration repository for the Journal Control Plane.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Config_Repository {
    const OPTION_VERSIONS = 'lunara_journal_control_plane_versions';
    const OPTION_ACTIVE   = 'lunara_journal_control_plane_active_version';
    const OPTION_MIGRATED = 'lunara_journal_control_plane_migrated_at';

    public static function ensure_default_version() {
        $versions = self::get_versions();
        if ( ! empty( $versions ) ) {
            return;
        }

        $legacy = Lunara_Journal_Migration::collect_legacy_settings();
        $config = Lunara_Journal_Config_Schema::sanitize_config( Lunara_Journal_Config_Schema::default_config( $legacy ) );
        self::create_and_activate( $config, 'Initial active Control Plane version.', 'system' );
    }

    public static function get_versions() {
        $versions = get_option( self::OPTION_VERSIONS, array() );
        return is_array( $versions ) ? $versions : array();
    }

    public static function get_version( $id ) {
        $id = (int) $id;
        foreach ( self::get_versions() as $version ) {
            if ( isset( $version['id'] ) && (int) $version['id'] === $id ) {
                return $version;
            }
        }
        return null;
    }

    public static function get_active_version_id() {
        return (int) get_option( self::OPTION_ACTIVE, 0 );
    }

    public static function get_active_version() {
        $active = self::get_version( self::get_active_version_id() );
        if ( $active ) {
            return $active;
        }
        self::ensure_default_version();
        return self::get_version( self::get_active_version_id() );
    }

    public static function get_active_config() {
        $version = self::get_active_version();
        if ( ! $version || empty( $version['config'] ) || ! is_array( $version['config'] ) ) {
            return Lunara_Journal_Config_Schema::sanitize_config( Lunara_Journal_Config_Schema::default_config() );
        }
        return Lunara_Journal_Config_Schema::sanitize_config( $version['config'] );
    }

    private static function create_version( array $config, $changelog, $actor = '' ) {
        $versions = self::get_versions();
        $next_id = 1;
        foreach ( $versions as $existing ) {
            $next_id = max( $next_id, isset( $existing['id'] ) ? ( (int) $existing['id'] + 1 ) : 1 );
        }

        $config = Lunara_Journal_Config_Schema::sanitize_config( $config );
        $config['config_version'] = self::semantic_config_version_for_id( $next_id );
        $now = current_time( 'mysql', true );
        $version = array(
            'id'             => $next_id,
            'config_version' => $config['config_version'],
            'status'         => 'stored',
            'created_at_gmt' => $now,
            'created_by'     => self::clean_actor( $actor ),
            'activated_at_gmt' => '',
            'activated_by'   => '',
            'changelog'      => sanitize_textarea_field( (string) $changelog ),
            'config'         => $config,
        );
        $versions[] = $version;
        if ( ! update_option( self::OPTION_VERSIONS, $versions, false ) ) {
            return new WP_Error( 'lunara_config_storage_failed', 'Configuration version could not be stored.' );
        }
        return $version;
    }

    private static function activate_version( $id, $actor = '' ) {
        $id = (int) $id;
        $versions = self::get_versions();
        $found = false;
        $now = current_time( 'mysql', true );
        foreach ( $versions as &$version ) {
            if ( isset( $version['id'] ) && (int) $version['id'] === $id ) {
                $validation = Lunara_Journal_Config_Schema::validate_config( $version['config'] );
                if ( empty( $validation['valid'] ) ) {
                    return new WP_Error( 'lunara_invalid_config', implode( ' ', $validation['errors'] ) );
                }
                $version['status'] = 'active';
                $version['activated_at_gmt'] = $now;
                $version['activated_by'] = self::clean_actor( $actor );
                $found = true;
            } elseif ( isset( $version['status'] ) && 'active' === $version['status'] ) {
                $version['status'] = 'superseded';
            }
        }
        unset( $version );

        if ( ! $found ) {
            return new WP_Error( 'lunara_missing_config_version', 'Configuration version not found.' );
        }

        if ( ! update_option( self::OPTION_VERSIONS, $versions, false ) ) {
            return new WP_Error( 'lunara_config_activation_failed', 'Configuration version status could not be activated.' );
        }
        if ( ! update_option( self::OPTION_ACTIVE, $id, false ) ) {
            return new WP_Error( 'lunara_config_activation_failed', 'The active configuration pointer could not be updated.' );
        }
        do_action( 'lunara_journal_control_plane_activated', $id, self::get_active_config() );
        return true;
    }

    public static function create_and_activate( array $config, $changelog, $actor = '' ) {
        $versions_before = self::get_versions();
        $active_before = self::get_active_version_id();
        $validation = Lunara_Journal_Config_Schema::validate_config( $config );
        if ( empty( $validation['valid'] ) ) {
            return new WP_Error( 'lunara_invalid_config', implode( ' ', $validation['errors'] ), $validation );
        }

        $config = Lunara_Journal_Config_Schema::sanitize_config( $config );
        $validation = Lunara_Journal_Config_Schema::validate_config( $config );
        if ( empty( $validation['valid'] ) ) {
            return new WP_Error( 'lunara_invalid_config', implode( ' ', $validation['errors'] ), $validation );
        }

        $version = self::create_version( $config, $changelog, $actor );
        if ( is_wp_error( $version ) ) {
            return $version;
        }
        $activated = self::activate_version( $version['id'], $actor );
        if ( is_wp_error( $activated ) ) {
            self::restore_repository_state( $versions_before, $active_before, $version );
            return $activated;
        }
        return self::get_version( $version['id'] );
    }

    public static function clone_prior_as_new_active( $id, $changelog, $actor = '' ) {
        $prior = self::get_version( $id );
        if ( ! $prior || empty( $prior['config'] ) || ! is_array( $prior['config'] ) ) {
            return new WP_Error( 'lunara_missing_config_version', 'Configuration version not found.' );
        }
        return self::create_and_activate( $prior['config'], $changelog, $actor );
    }

    public static function semantic_config_version_for_id( $id ) {
        $patch = max( 0, (int) $id - 1 );
        return '1.0.' . $patch;
    }

    private static function restore_repository_state( array $versions, $active_id, array $attempted_version ) {
        if ( self::get_active_version_id() !== (int) $active_id ) {
            return false;
        }
        $current = self::get_versions();
        if ( count( $current ) !== count( $versions ) + 1 || empty( $attempted_version['id'] ) ) {
            return false;
        }
        $expected = array();
        foreach ( $versions as $version ) {
            if ( ! is_array( $version ) || ! isset( $version['id'] ) ) {
                return false;
            }
            $expected[ (int) $version['id'] ] = $version;
        }
        $attempted_id = (int) $attempted_version['id'];
        $attempted_seen = false;
        foreach ( $current as $version ) {
            if ( ! is_array( $version ) || ! isset( $version['id'] ) ) {
                return false;
            }
            $id = (int) $version['id'];
            $comparison = $version;
            unset( $comparison['status'], $comparison['activated_at_gmt'], $comparison['activated_by'] );
            if ( $id === $attempted_id ) {
                $attempted_comparison = $attempted_version;
                unset( $attempted_comparison['status'], $attempted_comparison['activated_at_gmt'], $attempted_comparison['activated_by'] );
                if ( $attempted_seen || $comparison !== $attempted_comparison ) {
                    return false;
                }
                $attempted_seen = true;
                continue;
            }
            if ( ! isset( $expected[ $id ] ) ) {
                return false;
            }
            $expected_comparison = $expected[ $id ];
            unset( $expected_comparison['status'], $expected_comparison['activated_at_gmt'], $expected_comparison['activated_by'] );
            if ( $comparison !== $expected_comparison ) {
                return false;
            }
        }
        if ( ! $attempted_seen ) {
            return false;
        }
        return update_option( self::OPTION_VERSIONS, $versions, false );
    }

    private static function clean_actor( $actor ) {
        if ( '' !== (string) $actor ) {
            return sanitize_text_field( (string) $actor );
        }
        if ( function_exists( 'wp_get_current_user' ) ) {
            $user = wp_get_current_user();
            if ( $user && ! empty( $user->user_login ) ) {
                return sanitize_text_field( $user->user_login );
            }
        }
        return 'system';
    }
}
