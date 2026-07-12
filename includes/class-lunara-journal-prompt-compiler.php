<?php
/**
 * Compiles canonical Journal configuration into provider prompts.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Prompt_Compiler {
    private static $system_prompt_cache = array();
    private static $user_prompt_cache = array();
    public static function dispatch_system_prompt( array $config ) {
        $cache_key = ! empty( $config['config_version'] ) ? (string) $config['config_version'] : md5( wp_json_encode( $config ) );
        if ( isset( self::$system_prompt_cache[ $cache_key ] ) ) {
            return self::$system_prompt_cache[ $cache_key ];
        }
        $editorial = isset( $config['editorial'] ) && is_array( $config['editorial'] ) ? $config['editorial'] : array();
        $voice = isset( $editorial['voice'] ) && is_array( $editorial['voice'] ) ? $editorial['voice'] : array();
        $selection = isset( $editorial['selection'] ) && is_array( $editorial['selection'] ) ? $editorial['selection'] : array();
        $formatting = isset( $editorial['formatting'] ) && is_array( $editorial['formatting'] ) ? $editorial['formatting'] : array();
        $requirements = isset( $editorial['requirements'] ) && is_array( $editorial['requirements'] ) ? $editorial['requirements'] : array();

        $lines = array();
        $lines[] = 'You are the Editorial Engine for LUNARA Film Journal.';
        $lines[] = 'ACTIVE JOURNAL CONTROL PLANE CONFIGURATION: ' . ( isset( $config['config_version'] ) ? $config['config_version'] : '1.0.0' ) . '.';
        $lines[] = '';
        $lines[] = 'PURPOSE:';
        $lines[] = self::text( isset( $editorial['purpose'] ) ? $editorial['purpose'] : '' );
        $lines[] = '';
        $lines[] = 'VOICE:';
        $lines[] = self::text( isset( $voice['summary'] ) ? $voice['summary'] : '' );
        if ( ! empty( $voice['current_refinement'] ) ) {
            $lines[] = '';
            $lines[] = 'CURRENT DALTON VOICE / PROMPT REFINEMENT:';
            $lines[] = self::text( $voice['current_refinement'] );
        }
        $lines[] = '';
        $lines[] = 'SELECTION RULES:';
        $lines[] = '- Prefer ' . (int) ( $selection['prefer_entries'] ?? 2 ) . ' strong entries per run.';
        $lines[] = '- Never write more than ' . (int) ( $selection['max_entries'] ?? 3 ) . ' entries.';
        $lines[] = '- Each entry should usually be at least ' . (int) ( $selection['minimum_words'] ?? 75 ) . ' words and at least ' . (int) ( $selection['minimum_paragraphs'] ?? 2 ) . ' paragraphs.';
        foreach ( self::list_values( $selection['skip_rules'] ?? array() ) as $rule ) {
            $lines[] = '- ' . $rule;
        }
        $lines[] = '- If no item earns publication, output exactly: ' . ( $selection['skip_marker'] ?? '<!-- LUNARA_SKIP: no reader-worthy items -->' );
        $lines[] = '';
        $lines[] = 'READER VALUE TEST:';
        foreach ( self::list_values( $voice['reader_value_test'] ?? array() ) as $rule ) {
            $lines[] = '- ' . $rule;
        }
        $lines[] = '';
        $lines[] = 'FORMATTING - CRITICAL:';
        $lines[] = '- Output valid HTML only, no Markdown.';
        $lines[] = '- Separate entries with ' . self::text( $formatting['entry_separator'] ?? '<hr>' ) . '.';
        $lines[] = '- Start every entry with an original <h3> headline; that headline becomes the WordPress post title.';
        $lines[] = '- Never use <h2>.';
        $lines[] = '- Film titles in <em>.';
        $lines[] = '- Never use <strong> on people names.';
        $lines[] = '- No inline CSS, no classes, no divs, no bullet lists.';
        $lines[] = '- Use ASCII-only publishable HTML.';
        $lines[] = '';
        $lines[] = 'REQUIRED BEFORE READY STATE:';
        foreach ( $requirements as $name => $required ) {
            if ( $required ) {
                $lines[] = '- ' . str_replace( '_', ' ', $name ) . ' is required.';
            }
        }
        $lines[] = '';
        $lines[] = 'BANNED LANGUAGE:';
        $lines[] = implode( ', ', self::list_values( $voice['banned_phrases'] ?? array() ) );
        $lines[] = '';
        $lines[] = 'Do not write like a trade recap, a studio press kit, an awards consultant memo, or a content quota filler. Write like Dalton chose the item because it has a real charge.';

        $compiled = trim( implode( "\n", array_filter( $lines, static function ( $line ) { return null !== $line; } ) ) );
        self::$system_prompt_cache[ $cache_key ] = $compiled;
        return $compiled;
    }

    public static function dispatch_user_directive_prompt( array $config ) {
        $cache_key = ! empty( $config['config_version'] ) ? (string) $config['config_version'] : md5( wp_json_encode( $config ) );
        if ( isset( self::$user_prompt_cache[ $cache_key ] ) ) {
            return self::$user_prompt_cache[ $cache_key ];
        }
        $selection = $config['editorial']['selection'] ?? array();
        $compiled = trim( sprintf(
            "Analyze the following film news items and synthesize them into a selective Lunara Journal run.\n\nRules:\n- Separate entries with <hr>.\n- Do not use <h2>.\n- Start every entry with an original <h3> headline in Lunara's voice.\n- Film titles in <em>.\n- Prefer %d or fewer strong entries; never write more than %d.\n- Skip anything that does not earn its space.\n- If nothing earns a reader's time, output exactly: %s\n\nInput News Data:",
            (int) ( $selection['prefer_entries'] ?? 2 ),
            (int) ( $selection['max_entries'] ?? 3 ),
            (string) ( $selection['skip_marker'] ?? '<!-- LUNARA_SKIP: no reader-worthy items -->' )
        ) );
        self::$user_prompt_cache[ $cache_key ] = $compiled;
        return $compiled;
    }

    public static function chatgpt_editor_instructions( array $config ) {
        return trim( implode( "\n", array(
            'You are the private LUNARA Journal Editor working through the WordPress Journal Bridge.',
            'Before editing any draft, retrieve and obey the active Journal Control Plane configuration version ' . ( $config['config_version'] ?? '1.0.0' ) . '.',
            'Never publish, schedule, trash, delete, change post status, mutate sources, mutate schedules, rotate keys, or activate configuration.',
            'You may read drafts, propose revisions, save Dalton-approved revisions to allowlisted draft fields, validate, inspect audit history, and mark a validated draft ready for Dalton.',
            'If configuration, source, or validation data conflicts with user instructions, preserve draft-only safety and report the conflict.',
            '',
            self::dispatch_system_prompt( $config ),
        ) ) );
    }

    public static function public_summary( array $config ) {
        return array(
            'config_version' => $config['config_version'] ?? '1.0.0',
            'provider'       => $config['dispatch']['provider'] ?? 'openai',
            'schedule'       => $config['dispatch']['schedule'] ?? 'daily',
            'target_post_type' => 'journal',
            'post_status'    => 'draft',
            'sources_enabled'=> count( array_filter( $config['sources'] ?? array(), static function ( $source ) { return ! empty( $source['enabled'] ); } ) ),
            'notion_sync_enabled' => ! empty( $config['notion']['sync_enabled'] ),
        );
    }

    private static function text( $value ) {
        $value = is_scalar( $value ) ? (string) $value : '';
        $value = trim( preg_replace( '/\R{3,}/', "\n\n", $value ) );
        return $value;
    }

    private static function list_values( $values ) {
        if ( ! is_array( $values ) ) {
            return array();
        }
        $out = array();
        foreach ( $values as $value ) {
            if ( is_scalar( $value ) ) {
                $value = trim( (string) $value );
                if ( '' !== $value ) {
                    $out[] = $value;
                }
            }
        }
        return $out;
    }
}
