<?php
/**
 * Provenance and workflow metadata helpers.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Provenance {
    public static function attach_dispatch_provenance( $post_id, array $context = array() ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return false;
        }
        $config = Lunara_Journal_Control_Plane::get_active_config();
        $version = $config['config_version'] ?? '1.0.0';
        $provider = $context['provider'] ?? ( $config['dispatch']['provider'] ?? '' );
        $model = $context['model'] ?? ( $config['dispatch']['models'][ $provider ] ?? '' );
        $now = current_time( 'mysql', true );

        self::set_field( $post_id, 'journal_writer_source', 'dispatch' );
        self::set_field( $post_id, 'journal_dispatch_actor', 'Lunara Dispatch Automation' );
        self::set_field( $post_id, 'journal_status', 'needs_chatgpt_review' );
        self::set_field( $post_id, 'journal_last_bridge_action', 'dispatch_create' );
        self::set_field( $post_id, 'journal_last_bridge_actor', 'Lunara Dispatch Automation' );
        self::set_field( $post_id, 'journal_last_bridge_client', 'Dispatch PHP Integration' );
        self::set_field( $post_id, 'journal_last_bridge_updated_at', $now );
        self::set_field( $post_id, 'journal_dispatch_ingested_at', $now );
        self::set_field( $post_id, 'journal_validation_status', 'not_checked' );

        update_post_meta( $post_id, '_lunara_journal_config_version', sanitize_text_field( $version ) );
        update_post_meta( $post_id, '_lunara_journal_prompt_version', sanitize_text_field( 'journal-' . $version ) );
        update_post_meta( $post_id, '_lunara_journal_initial_provider', sanitize_text_field( $provider ) );
        update_post_meta( $post_id, '_lunara_journal_initial_model', sanitize_text_field( $model ) );
        update_post_meta( $post_id, '_lunara_journal_generated_at_gmt', $now );
        update_post_meta( $post_id, '_lunara_dispatch_version', defined( 'LUNARA_DISPATCH_VERSION' ) ? LUNARA_DISPATCH_VERSION : '' );
        update_post_meta( $post_id, '_lunara_foundation_version', defined( 'LUNARA_JOURNAL_FOUNDATION_VERSION' ) ? LUNARA_JOURNAL_FOUNDATION_VERSION : '' );

        $audit = array(
            'action'         => 'dispatch_create',
            'actor'          => 'Lunara Dispatch Automation',
            'client'         => 'Dispatch PHP Integration',
            'timestamp_gmt'  => $now,
            'config_version' => $version,
            'provider'       => $provider,
            'model'          => $model,
        );
        add_post_meta( $post_id, '_lunara_journal_bridge_log', $audit, false );
        return true;
    }

    public static function attach_validation_result( $post_id, array $result ) {
        $status = ! empty( $result['valid'] ) ? 'passed' : 'failed';
        self::set_field( (int) $post_id, 'journal_validation_status', $status );
        self::set_field( (int) $post_id, 'journal_validation_report', wp_json_encode( $result ) );
        update_post_meta( (int) $post_id, '_lunara_journal_last_validation', $result );
    }

    private static function set_field( $post_id, $field, $value ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( $field, $value, $post_id );
            return;
        }
        update_post_meta( $post_id, $field, $value );
    }
}
