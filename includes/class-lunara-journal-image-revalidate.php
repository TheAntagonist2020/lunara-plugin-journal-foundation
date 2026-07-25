<?php
/**
 * Re-run Journal validation whenever a featured image is set or changed.
 *
 * Dispatch (and the image-sideload primitive) can attach a featured image to a
 * Journal draft after it was first created. When that happens the stored
 * validation verdict goes stale: the draft can carry `journal_validation_status
 * = failed` with a "Featured image is required." report even though a usable
 * image is now attached, so it keeps reading as blocked in the Control Tower and
 * the Fast Journal Desk.
 *
 * This listener watches the core `_thumbnail_id` post-meta on `journal` posts and
 * re-runs the deterministic validator, persisting the fresh result, so any image
 * attach — Dispatch backfill, the sideload primitive, or a manual edit —
 * refreshes the verdict instead of leaving a stale one behind.
 *
 * @package Lunara_Journal_Foundation
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Image_Revalidate {
    /** Per-request guard so a post is re-validated at most once per request. */
    private static $done = array();
    private static $booted = false;

    public static function bootstrap() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        add_action( 'added_post_meta', array( __CLASS__, 'on_meta_write' ), 20, 3 );
        add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_write' ), 20, 3 );
        add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta_deleted' ), 20, 3 );
    }

    /**
     * @param int    $meta_id  Meta row id (unused).
     * @param int    $post_id  Post the meta belongs to.
     * @param string $meta_key Meta key written.
     * @return void
     */
    public static function on_meta_write( $meta_id, $post_id, $meta_key ) {
        if ( '_thumbnail_id' === $meta_key ) {
            self::revalidate( (int) $post_id );
        }
    }

    /**
     * @param array  $meta_ids Meta row ids (unused).
     * @param int    $post_id  Post the meta belonged to.
     * @param string $meta_key Meta key removed.
     * @return void
     */
    public static function on_meta_deleted( $meta_ids, $post_id, $meta_key ) {
        if ( '_thumbnail_id' === $meta_key ) {
            self::revalidate( (int) $post_id );
        }
    }

    private static function revalidate( $post_id ) {
        if ( $post_id <= 0 || isset( self::$done[ $post_id ] ) ) {
            return;
        }
        if ( 'journal' !== get_post_type( $post_id ) ) {
            return;
        }
        if ( ! class_exists( 'Lunara_Journal_Validator' ) || ! class_exists( 'Lunara_Journal_Provenance' ) ) {
            return;
        }
        self::$done[ $post_id ] = true;
        if ( class_exists( 'Lunara_Journal_Image_Guard' ) ) {
            Lunara_Journal_Image_Guard::clear_cache( $post_id );
        }
        $result = Lunara_Journal_Validator::validate_post( $post_id );
        Lunara_Journal_Provenance::attach_validation_result( $post_id, $result );
    }
}

Lunara_Journal_Image_Revalidate::bootstrap();
