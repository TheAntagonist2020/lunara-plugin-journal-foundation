<?php
/**
 * Canonical Journal configuration schema and defaults.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Config_Schema {
    public static function default_config( array $legacy = array() ) {
        $now = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );

        return array(
            'protocol_version' => Lunara_Journal_Protocol::VERSION,
            'schema_version'   => Lunara_Journal_Protocol::SCHEMA_VERSION,
            'config_version'   => '1.0.0',
            'status'           => 'active',
            'created_at_gmt'   => $now,
            'activated_at_gmt' => $now,
            'changelog'        => 'Initial Journal Control Plane configuration generated from existing Dispatch/Foundation settings.',
            'editorial'        => array(
                'purpose' => 'LUNARA Journal is the living news-desk and magazine side of LUNARA Film: selective, critic-led, film-fan-aware dispatches that exist only when Dalton has a real reason to surface the item.',
                'voice'   => array(
                    'summary' => 'Conversational, exact, sharp, fan-aware, critic-led, alive without sounding like trade-copy filler.',
                    'reader_value_test' => array(
                        'Would a film reader text this to a friend with a reaction?',
                        'Does it reveal a meaningful pressure point around a movie, filmmaker, studio, actor, audience behavior, box office signal, festival strategy, awards race, or the business of taste?',
                        'Can LUNARA add judgment, excitement, skepticism, context, taste, stakes, contradiction, or reader-facing implication beyond the source?'
                    ),
                    'banned_phrases' => array(
                        'autopsy',
                        'ever-evolving',
                        'poised to',
                        'made waves',
                        'must-see',
                        'garnering attention',
                        'cinematic discourse',
                        'in the current landscape',
                        'raises significant questions',
                        'unprecedented',
                        'game-changer',
                        'a love letter to',
                        'at the forefront of',
                        'highly anticipated',
                        'this matters because',
                        'this is significant as',
                        'could potentially',
                        'part of the conversation',
                        'worth keeping an eye on',
                        'only time will tell',
                        'fans are eagerly awaiting',
                        'delves into',
                        'underscores',
                        'a testament to'
                    ),
                ),
                'selection' => array(
                    'prefer_entries' => 2,
                    'max_entries'    => 3,
                    'minimum_words'  => 75,
                    'minimum_paragraphs' => 2,
                    'skip_marker'    => '<!-- LUNARA_SKIP: no reader-worthy items -->',
                    'skip_rules'     => array(
                        'Skip thin, generic, lightly sourced, purely promotional, routine quote, or headline-only items.',
                        'Skip anything that mainly says this happened.',
                        'Skip anything that mostly preserves the source angle, order, phrasing, or headline logic.',
                        'Prefer zero posts over polite, competent, forgettable posts.'
                    ),
                ),
                'formatting' => array(
                    'output_format'       => 'valid_html_only',
                    'entry_separator'     => '<hr>',
                    'entry_heading'       => 'h3',
                    'disallow_h2'         => true,
                    'film_title_element'  => 'em',
                    'disallow_inline_css' => true,
                    'disallow_markdown'   => true,
                    'ascii_only'          => true,
                ),
                'requirements' => array(
                    'featured_image'  => true,
                    'excerpt_or_deck'  => true,
                    'seo_description' => true,
                    'source_url'       => true,
                    'draft_only'       => true,
                    'human_publish'    => true,
                ),
            ),
            'dispatch' => array(
                'enabled'          => self::bool_from_legacy( $legacy, 'lunara_dispatch_enabled', false ),
                'schedule'         => self::string_from_legacy( $legacy, 'lunara_dispatch_schedule', 'daily' ),
                'target_post_type' => 'journal',
                'post_status'      => 'draft',
                'provider'         => self::string_from_legacy( $legacy, 'lunara_dispatch_provider', 'openai' ),
                'max_tokens'       => self::int_from_legacy( $legacy, 'lunara_dispatch_max_tokens', 4096, 1024, 16000 ),
                'models'           => array(
                    'openai' => self::string_from_legacy( $legacy, 'lunara_dispatch_openai_model', 'gpt-4o' ),
                    'claude' => self::string_from_legacy( $legacy, 'lunara_dispatch_claude_model', 'claude-opus-4-5' ),
                    'gemini' => self::string_from_legacy( $legacy, 'lunara_dispatch_gemini_model', 'gemini-2.5-pro' ),
                    'grok'   => self::string_from_legacy( $legacy, 'lunara_dispatch_grok_model', 'grok-4' ),
                ),
            ),
            'sources' => self::normalize_sources( isset( $legacy['lunara_dispatch_sources'] ) && is_array( $legacy['lunara_dispatch_sources'] ) ? $legacy['lunara_dispatch_sources'] : array() ),
            'chatgpt' => array(
                'live_configuration_required' => true,
                'may_activate_configuration'  => false,
                'may_publish'                 => true,
                'may_delete'                  => false,
                'allowed_actions'             => array( 'read', 'update', 'validate', 'mark_ready', 'run_dispatch', 'publish', 'audit', 'schema' ),
            ),
            'notion' => array(
                'sync_enabled'     => false,
                'one_way_only'     => true,
                'blocks_activation'=> false,
            ),
            'workflow' => array(
                'states' => array(
                    'collected',
                    'selected',
                    'dispatch_generated',
                    'needs_chatgpt_review',
                    'ai_reviewed',
                    'validation_failed',
                    'ready_for_dalton',
                    'published',
                    'held',
                    'rejected',
                ),
                'ready_state' => 'ready_for_dalton',
            ),
        );
    }

    public static function sanitize_config( array $config ) {
        $default = self::default_config();
        $config = self::deep_merge( $default, $config );

        $config['protocol_version'] = Lunara_Journal_Protocol::VERSION;
        $config['schema_version']   = Lunara_Journal_Protocol::SCHEMA_VERSION;
        $config['dispatch']['target_post_type'] = 'journal';
        $config['dispatch']['post_status']      = 'draft';
        $config['dispatch']['provider'] = self::sanitize_choice( $config['dispatch']['provider'], array( 'openai', 'claude', 'gemini', 'grok' ), 'openai' );
        $config['dispatch']['schedule'] = self::sanitize_choice( $config['dispatch']['schedule'], array( 'daily', 'twice_daily', 'every_4_hours', 'every_2_hours' ), 'daily' );
        $config['dispatch']['max_tokens'] = max( 1024, min( 16000, (int) $config['dispatch']['max_tokens'] ) );
        $config['dispatch']['enabled'] = ! empty( $config['dispatch']['enabled'] );
        $config['sources'] = self::normalize_sources( isset( $config['sources'] ) && is_array( $config['sources'] ) ? $config['sources'] : array() );
        $config['chatgpt']['live_configuration_required'] = true;
        $config['chatgpt']['may_activate_configuration'] = false;
        $config['chatgpt']['may_publish'] = ! empty( $config['chatgpt']['may_publish'] );
        $config['chatgpt']['may_delete'] = false;
        $config['chatgpt']['allowed_actions'] = array_values( array_unique( array_map( 'sanitize_key', isset( $config['chatgpt']['allowed_actions'] ) && is_array( $config['chatgpt']['allowed_actions'] ) ? $config['chatgpt']['allowed_actions'] : array() ) ) );
        $config['notion']['sync_enabled'] = ! empty( $config['notion']['sync_enabled'] );
        $config['notion']['one_way_only'] = true;
        $config['notion']['blocks_activation'] = false;
        return $config;
    }

    public static function validate_config( array $config ) {
        $errors = array();
        if ( empty( $config['editorial']['purpose'] ) ) {
            $errors[] = 'Editorial purpose is required.';
        }
        if ( 'journal' !== (string) $config['dispatch']['target_post_type'] ) {
            $errors[] = 'Dispatch target post type must be journal.';
        }
        if ( 'draft' !== (string) $config['dispatch']['post_status'] ) {
            $errors[] = 'Dispatch post status must be draft.';
        }
        if ( empty( $config['editorial']['formatting']['disallow_h2'] ) ) {
            $errors[] = 'Journal entries must disallow h2 headings.';
        }
        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    public static function normalize_sources( array $sources ) {
        $out = array();
        $seen = array();
        foreach ( $sources as $row ) {
            if ( ! is_array( $row ) || empty( $row['url'] ) ) {
                continue;
            }
            $label = isset( $row['label'] ) ? (string) $row['label'] : (string) $row['url'];
            $id = isset( $row['id'] ) && '' !== (string) $row['id'] ? self::sanitize_key( $row['id'] ) : self::sanitize_key( $label );
            if ( '' === $id ) {
                $id = 'source';
            }
            $base = $id;
            $i = 2;
            while ( isset( $seen[ $id ] ) ) {
                $id = $base . '-' . $i;
                $i++;
            }
            $seen[ $id ] = true;
            $out[] = array(
                'id'       => $id,
                'label'    => self::sanitize_text( $label ),
                'url'      => self::sanitize_url( $row['url'] ),
                'enabled'  => ! empty( $row['enabled'] ),
                'max'      => isset( $row['max'] ) ? max( 1, min( 50, (int) $row['max'] ) ) : 10,
                'priority' => isset( $row['priority'] ) ? max( 1, min( 10, (int) $row['priority'] ) ) : 5,
            );
        }
        return $out;
    }

    public static function deep_merge( array $base, array $override ) {
        foreach ( $override as $key => $value ) {
            if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
                $base[ $key ] = self::deep_merge( $base[ $key ], $value );
            } else {
                $base[ $key ] = $value;
            }
        }
        return $base;
    }

    private static function string_from_legacy( array $legacy, $key, $default ) {
        if ( isset( $legacy[ $key ] ) && is_scalar( $legacy[ $key ] ) && '' !== trim( (string) $legacy[ $key ] ) ) {
            return self::sanitize_text( $legacy[ $key ] );
        }
        if ( function_exists( 'get_option' ) ) {
            $value = get_option( $key, $default );
            return is_scalar( $value ) && '' !== trim( (string) $value ) ? self::sanitize_text( $value ) : $default;
        }
        return $default;
    }

    private static function bool_from_legacy( array $legacy, $key, $default ) {
        if ( isset( $legacy[ $key ] ) ) {
            return ! empty( $legacy[ $key ] );
        }
        return function_exists( 'get_option' ) ? ! empty( get_option( $key, $default ? 1 : 0 ) ) : (bool) $default;
    }

    private static function int_from_legacy( array $legacy, $key, $default, $min, $max ) {
        $value = isset( $legacy[ $key ] ) ? $legacy[ $key ] : ( function_exists( 'get_option' ) ? get_option( $key, $default ) : $default );
        return max( $min, min( $max, (int) $value ) );
    }

    private static function sanitize_choice( $value, array $allowed, $default ) {
        $value = self::sanitize_key( $value );
        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    private static function sanitize_key( $value ) {
        return function_exists( 'sanitize_key' ) ? sanitize_key( (string) $value ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
    }

    private static function sanitize_text( $value ) {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $value ) : trim( strip_tags( (string) $value ) );
    }

    private static function sanitize_url( $value ) {
        return function_exists( 'esc_url_raw' ) ? esc_url_raw( (string) $value ) : trim( (string) $value );
    }
}
