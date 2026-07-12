<?php
/**
 * Non-blocking Notion mirror orchestration.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Notion_Sync {
    const CRON_HOOK = 'lunara_journal_notion_sync';

    public static function bootstrap() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ) );
        add_action( 'lunara_journal_control_plane_activated', array( __CLASS__, 'queue_after_activation' ), 10, 2 );
    }

    public static function queue_after_activation( $version_id, array $config ) {
        if ( empty( $config['notion']['sync_enabled'] ) ) {
            return;
        }
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 60, self::CRON_HOOK );
        }
    }

    public static function run_scheduled() {
        $config = Lunara_Journal_Control_Plane::get_active_config();
        if ( empty( $config['notion']['sync_enabled'] ) ) {
            return;
        }
        Lunara_Journal_Notion_Client::sync_config( $config );
    }
}
