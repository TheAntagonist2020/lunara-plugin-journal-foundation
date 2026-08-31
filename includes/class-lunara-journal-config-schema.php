<?php
/**
 * Canonical Journal configuration schema and defaults.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Config_Schema {
    const DEFAULT_OPENAI_MODEL = 'gpt-5.4-mini';
    const MAX_OUTPUT_TOKENS    = 2200;

    public static function allowed_openai_models() {
        return array( 'gpt-5.4-mini', 'gpt-5.4-nano' );
    }

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
                'max_tokens'       => self::int_from_legacy( $legacy, 'lunara_dispatch_max_tokens', self::MAX_OUTPUT_TOKENS, 1024, self::MAX_OUTPUT_TOKENS ),
                'models'           => array(
                    'openai' => self::sanitize_openai_model( self::string_from_legacy( $legacy, 'lunara_dispatch_openai_model', self::DEFAULT_OPENAI_MODEL ) ),
                    'claude' => self::string_from_legacy( $legacy, 'lunara_dispatch_claude_model', 'claude-opus-4-5' ),
                    'gemini' => self::string_from_legacy( $legacy, 'lunara_dispatch_gemini_model', 'gemini-2.5-pro' ),
                    'grok'   => self::string_from_legacy( $legacy, 'lunara_dispatch_grok_model', 'grok-4' ),
                ),
            ),
            'sources' => self::normalize_sources( isset( $legacy['lunara_dispatch_sources'] ) && is_array( $legacy['lunara_dispatch_sources'] ) ? $legacy['lunara_dispatch_sources'] : array() ),
            'chatgpt' => array(
                'live_configuration_required' => true,
                'may_activate_configuration'  => false,
                'may_publish'                 => false,
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
                    'ready_for_editor',
                    'published',
                    'held',
                    'rejected',
                ),
                'ready_state' => 'ready_for_editor',
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
        $config['dispatch']['max_tokens'] = max( 1024, min( self::MAX_OUTPUT_TOKENS, (int) $config['dispatch']['max_tokens'] ) );
        if ( ! isset( $config['dispatch']['models'] ) || ! is_array( $config['dispatch']['models'] ) ) {
            $config['dispatch']['models'] = $default['dispatch']['models'];
        }
        $config['dispatch']['models']['openai'] = self::sanitize_openai_model( isset( $config['dispatch']['models']['openai'] ) ? $config['dispatch']['models']['openai'] : self::DEFAULT_OPENAI_MODEL );
        foreach ( array( 'claude', 'gemini', 'grok' ) as $provider ) {
            $config['dispatch']['models'][ $provider ] = self::sanitize_text( isset( $config['dispatch']['models'][ $provider ] ) ? $config['dispatch']['models'][ $provider ] : $default['dispatch']['models'][ $provider ] );
        }
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
        if ( ! isset( $config['sources'] ) || ! is_array( $config['sources'] ) ) {
            $errors[] = 'Sources must be submitted as rows.';
        } else {
            $source_validation = self::validate_sources( $config['sources'] );
            foreach ( $source_validation['errors'] as $source_error ) {
                $errors[] = 'Source row ' . ( (int) $source_error['row'] + 1 ) . ': ' . $source_error['message'];
            }
        }
        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Validate canonical source rows without silently repairing input.
     *
     * @param array $sources Candidate canonical rows.
     * @return array{valid:bool,errors:array<int,array{row:int,field:string,message:string}>}
     */
    public static function validate_sources( array $sources ) {
        $errors = array();
        $seen_ids = array();
        $seen_urls = array();
        $expected_keys = array( 'enabled', 'id', 'label', 'max', 'priority', 'url' );

        foreach ( $sources as $index => $row ) {
            $row_number = is_int( $index ) ? $index : count( $errors );
            if ( ! is_array( $row ) ) {
                $errors[] = self::source_error( $row_number, 'row', 'Each source must be a labeled row.' );
                continue;
            }

            $actual_keys = array_keys( $row );
            sort( $actual_keys );
            if ( $expected_keys !== $actual_keys ) {
                $errors[] = self::source_error( $row_number, 'row', 'Each source row must contain only ID, enabled, label, URL, max, and priority.' );
            }

            $id = isset( $row['id'] ) && is_scalar( $row['id'] ) ? trim( (string) $row['id'] ) : '';
            if ( '' === $id || $id !== self::sanitize_key( $id ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/D', $id ) ) {
                $errors[] = self::source_error( $row_number, 'id', 'Source ID is missing or invalid.' );
            } elseif ( isset( $seen_ids[ $id ] ) ) {
                $errors[] = self::source_error( $row_number, 'id', 'Source IDs must be unique.' );
            } else {
                $seen_ids[ $id ] = true;
            }

            if ( ! array_key_exists( 'enabled', $row ) || ! in_array( $row['enabled'], array( true, false, 1, 0, '1', '0' ), true ) ) {
                $errors[] = self::source_error( $row_number, 'enabled', 'Enabled must be on or off.' );
            }

            $label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? self::sanitize_text( $row['label'] ) : '';
            if ( '' === $label ) {
                $errors[] = self::source_error( $row_number, 'label', 'Source name is required.' );
            }

            $url = isset( $row['url'] ) && is_scalar( $row['url'] ) ? self::normalize_source_url( $row['url'] ) : '';
            if ( '' === $url ) {
                $errors[] = self::source_error( $row_number, 'url', 'Use a complete HTTP or HTTPS URL without credentials or fragments.' );
            } elseif ( isset( $seen_urls[ $url ] ) ) {
                $errors[] = self::source_error( $row_number, 'url', 'Source URLs must be unique.' );
            } else {
                $seen_urls[ $url ] = true;
            }

            if ( ! isset( $row['max'] ) || ! self::strict_integer_in_range( $row['max'], 1, 50 ) ) {
                $errors[] = self::source_error( $row_number, 'max', 'Maximum items must be a whole number from 1 to 50.' );
            }
            if ( ! isset( $row['priority'] ) || ! self::strict_integer_in_range( $row['priority'], 1, 10 ) ) {
                $errors[] = self::source_error( $row_number, 'priority', 'Priority must be a whole number from 1 to 10.' );
            }
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Produce the stable URL used for storage and duplicate detection.
     *
     * @param mixed $value Candidate URL.
     * @return string
     */
    public static function normalize_source_url( $value ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $value = trim( (string) $value );
        if ( '' === $value || 0 === strpos( $value, '//' ) || preg_match( '/[\x00-\x20\x7f<>"\'{}|^`]/', $value ) || false !== strpos( $value, '\\' ) ) {
            return '';
        }

        $parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $value ) : parse_url( $value );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        $scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
        $raw_host = isset( $parts['host'] ) ? (string) $parts['host'] : '';
        $bracketed_ipv6 = strlen( $raw_host ) > 2 && '[' === substr( $raw_host, 0, 1 ) && ']' === substr( $raw_host, -1 );
        $host = strtolower( $bracketed_ipv6 ? substr( $raw_host, 1, -1 ) : rtrim( $raw_host, '.' ) );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
            return '';
        }
        if ( false !== strpos( $host, ':' ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                return '';
            }
        } elseif ( preg_match( '/[^a-z0-9.\-]/i', $host ) ) {
            return '';
        }

        $has_port = isset( $parts['port'] );
        $port = $has_port ? (int) $parts['port'] : 0;
        if ( $has_port && ( $port < 1 || $port > 65535 ) ) {
            return '';
        }
        if ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) {
            $port = 0;
        }

        $path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
        if ( '' === $path ) {
            $path = '/';
        }
        if ( '/' !== substr( $path, 0, 1 ) || false !== strpos( $path, '\\' ) ) {
            return '';
        }
        $query = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
        $host_for_url = false !== strpos( $host, ':' ) ? '[' . $host . ']' : $host;
        $normalized = $scheme . '://' . $host_for_url . ( $port ? ':' . $port : '' ) . $path . $query;

        return function_exists( 'esc_url_raw' ) ? esc_url_raw( $normalized, array( 'http', 'https' ) ) : $normalized;
    }

    public static function normalize_sources( array $sources ) {
        $out = array();
        $seen = array();
        foreach ( $sources as $row ) {
            if ( ! is_array( $row ) || empty( $row['url'] ) ) {
                continue;
            }
            $url = self::normalize_source_url( $row['url'] );
            if ( '' === $url ) {
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
                'url'      => $url,
                'enabled'  => ! empty( $row['enabled'] ),
                'max'      => isset( $row['max'] ) ? max( 1, min( 50, (int) $row['max'] ) ) : 10,
                'priority' => isset( $row['priority'] ) ? max( 1, min( 10, (int) $row['priority'] ) ) : 5,
            );
        }
        return $out;
    }

    private static function source_error( $row, $field, $message ) {
        return array(
            'row'     => (int) $row,
            'field'   => (string) $field,
            'message' => (string) $message,
        );
    }

    private static function strict_integer_in_range( $value, $minimum, $maximum ) {
        if ( is_int( $value ) ) {
            $integer = $value;
        } elseif ( is_string( $value ) && preg_match( '/^\d+$/D', $value ) ) {
            $integer = (int) $value;
        } else {
            return false;
        }
        return $integer >= $minimum && $integer <= $maximum;
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

    private static function sanitize_openai_model( $value ) {
        $value = self::sanitize_text( $value );
        return in_array( $value, self::allowed_openai_models(), true ) ? $value : self::DEFAULT_OPENAI_MODEL;
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
