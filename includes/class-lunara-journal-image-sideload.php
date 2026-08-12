<?php
/**
 * Attach a Journal featured image from a remote URL.
 *
 * Foundation historically accepted only an already-uploaded attachment id for a
 * draft's featured image; the download itself lived in Lunara Dispatch. This
 * class adds the missing primitive: given a Journal draft and an image URL, it
 * sideloads the file into the media library, sets it as the featured image,
 * records provenance, and re-runs validation so the Featured Image Guard
 * reflects the new image immediately.
 *
 * It is triggered by writing the post-meta key
 * `_lunara_journal_set_featured_image_url` on a Journal post, so it can be driven
 * through the generic "update post meta" bridge path, and also exposes a public
 * sideload_from_url() for same-process callers.
 *
 * @package Lunara_Journal_Foundation
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Image_Sideload {
    /** Preferred source width for wide Journal editorial images. */
    const PREFERRED_REMOTE_WIDTH = 1920;

    /** Minimum width at which an existing attachment is safe to reuse. */
    const MINIMUM_REUSE_WIDTH = 1200;

    /** Trigger meta keys written by the bridge or editor to request an attach. */
    const TRIGGER_URL    = '_lunara_journal_set_featured_image_url';
    const TRIGGER_ALT    = '_lunara_journal_set_featured_image_alt';
    const TRIGGER_CREDIT = '_lunara_journal_set_featured_image_credit';

    /** Where the outcome of the most recent attach is recorded, observable via inspect. */
    const RESULT_META = '_lunara_journal_image_sideload_result';

    /** Marks an attachment with the remote URL it was sideloaded from, for dedup. */
    const ATTACHMENT_SOURCE_META = '_lunara_journal_image_source_url';

    /** Post ids queued for a shutdown-time attach, keyed to avoid duplicate work. */
    private static $queued = array();

    /** Guards against double hook registration. */
    private static $booted = false;

    public static function bootstrap() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        add_action( 'added_post_meta', array( __CLASS__, 'maybe_queue_from_meta' ), 10, 4 );
        add_action( 'updated_post_meta', array( __CLASS__, 'maybe_queue_from_meta' ), 10, 4 );
    }

    /**
     * Watch for the trigger meta key and queue a shutdown-time attach so every
     * key written in the same batch (url plus optional alt/credit) is available.
     *
     * @param int    $meta_id  Meta row id (unused).
     * @param int    $post_id  Post the meta belongs to.
     * @param string $meta_key Meta key written.
     * @return void
     */
    public static function maybe_queue_from_meta( $meta_id, $post_id, $meta_key ) {
        if ( self::TRIGGER_URL !== $meta_key ) {
            return;
        }
        $post_id = (int) $post_id;
        if ( $post_id <= 0 || 'journal' !== get_post_type( $post_id ) ) {
            return;
        }
        if ( isset( self::$queued[ $post_id ] ) ) {
            return;
        }
        self::$queued[ $post_id ] = true;
        add_action( 'shutdown', array( __CLASS__, 'process_queue' ), 20 );
    }

    /**
     * Run any queued attaches at request shutdown, after all batched meta is in.
     *
     * @return void
     */
    public static function process_queue() {
        foreach ( array_keys( self::$queued ) as $post_id ) {
            $post_id = (int) $post_id;
            $url     = (string) get_post_meta( $post_id, self::TRIGGER_URL, true );
            $alt     = (string) get_post_meta( $post_id, self::TRIGGER_ALT, true );
            $credit  = (string) get_post_meta( $post_id, self::TRIGGER_CREDIT, true );

            // Consume the trigger keys so the request stays idempotent.
            delete_post_meta( $post_id, self::TRIGGER_URL );
            delete_post_meta( $post_id, self::TRIGGER_ALT );
            delete_post_meta( $post_id, self::TRIGGER_CREDIT );

            $result = self::sideload_from_url( $post_id, $url, $alt, $credit );
            self::record_result( $post_id, $url, $result );
        }
        self::$queued = array();
    }

    /**
     * Download an image, attach it, and make it the Journal draft's featured image.
     *
     * @param int    $post_id Journal post id.
     * @param string $url     Remote image URL (http/https).
     * @param string $alt     Optional alt text; defaults to the post title.
     * @param string $credit  Optional image credit.
     * @return array<string,mixed>|WP_Error
     */
    public static function sideload_from_url( $post_id, $url, $alt = '', $credit = '' ) {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );
        if ( ! $post || 'journal' !== $post->post_type ) {
            return new WP_Error( 'lunara_image_wrong_post_type', 'Featured image sideload only applies to Journal entries.', array( 'status' => 400 ) );
        }
        if ( ! in_array( $post->post_status, array( 'draft', 'pending', 'private', 'auto-draft' ), true ) ) {
            return new WP_Error( 'lunara_image_refused_status', 'Featured image sideload refuses published, scheduled, and trashed content.', array( 'status' => 403 ) );
        }

        $url = self::validate_url( $url );
        if ( is_wp_error( $url ) ) {
            return $url;
        }

        $source_url = $url;
        $download_url = self::preferred_download_url( $source_url );
        $attachment_id = self::find_existing_attachment( $source_url );
        if ( $attachment_id && $download_url !== $source_url && ! self::attachment_meets_preferred_quality( $attachment_id ) ) {
            $attachment_id = 0;
        }
        $reused        = (bool) $attachment_id;
        if ( ! $attachment_id ) {
            $attachment_id = self::download_and_attach( $download_url, $post_id );
            if ( is_wp_error( $attachment_id ) ) {
                return $attachment_id;
            }
            update_post_meta( $attachment_id, self::ATTACHMENT_SOURCE_META, $source_url );
        }

        $alt = '' !== trim( (string) $alt ) ? sanitize_text_field( $alt ) : self::default_alt( $post );
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

        set_post_thumbnail( $post_id, $attachment_id );
        if ( (int) get_post_thumbnail_id( $post_id ) !== (int) $attachment_id ) {
            return new WP_Error( 'lunara_image_thumbnail_readback_failed', 'WordPress did not persist the featured image assignment.', array( 'status' => 409, 'attachment_id' => (int) $attachment_id ) );
        }

        // Mirror the image provenance onto the editorial ACF fields.
        self::set_field( $post_id, 'journal_image_source_url', $source_url );
        self::set_field( $post_id, 'journal_image_alt', $alt );
        if ( '' !== trim( (string) $credit ) ) {
            self::set_field( $post_id, 'journal_image_credit', sanitize_text_field( $credit ) );
        }

        // Refresh the Featured Image Guard verdict and persist validation.
        if ( class_exists( 'Lunara_Journal_Image_Guard' ) ) {
            Lunara_Journal_Image_Guard::clear_cache( $post_id );
        }
        $revalidated = null;
        if ( class_exists( 'Lunara_Journal_Validator' ) && class_exists( 'Lunara_Journal_Provenance' ) ) {
            $revalidated = Lunara_Journal_Validator::validate_post( $post_id );
            Lunara_Journal_Provenance::attach_validation_result( $post_id, $revalidated );
        }

        self::log( $post_id, $reused ? 'featured_image_reused' : 'featured_image_sideloaded', array(
            'attachment_id' => (int) $attachment_id,
            'source_url'    => $source_url,
            'download_url'  => $download_url,
        ) );

        $meta   = wp_get_attachment_metadata( $attachment_id );
        $width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
        $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

        return array(
            'attachment_id' => (int) $attachment_id,
            'reused'        => $reused,
            'url'           => wp_get_attachment_url( $attachment_id ),
            'source_url'    => $source_url,
            'download_url'  => $download_url,
            'width'         => $width,
            'height'        => $height,
            'dimensions'    => $width && $height ? $width . 'x' . $height : '',
            'alt'           => $alt,
            'validation'    => is_array( $revalidated ) ? array(
                'valid'  => ! empty( $revalidated['valid'] ),
                'status' => ! empty( $revalidated['valid'] ) ? 'passed' : 'failed',
            ) : null,
        );
    }

    private static function download_and_attach( $url, $post_id ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error(
                'lunara_image_download_failed',
                'The image could not be downloaded or attached: ' . $attachment_id->get_error_message(),
                array( 'status' => 422, 'source_url' => $url )
            );
        }
        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            return new WP_Error( 'lunara_image_attach_failed', 'The sideloaded file is not a usable attachment.', array( 'status' => 422, 'source_url' => $url ) );
        }
        return (int) $attachment_id;
    }

    private static function find_existing_attachment( $url ) {
        $ids = get_posts( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => self::ATTACHMENT_SOURCE_META,
            'meta_value'     => $url,
            'no_found_rows'  => true,
        ) );
        return $ids ? (int) $ids[0] : 0;
    }

    /**
     * Request a production-size derivative from WordPress-hosted source media.
     * The original URL remains the canonical provenance and deduplication key.
     */
    private static function preferred_download_url( $url ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( false === strpos( $path, '/wp-content/uploads/' ) ) {
            return $url;
        }

        return preg_replace_callback( '/([?&])w=(\d+)(?=(&|#|$))/i', static function ( $matches ) {
            $width = (int) $matches[2];
            if ( $width < 1 || $width >= self::PREFERRED_REMOTE_WIDTH ) {
                return $matches[0];
            }
            return $matches[1] . 'w=' . self::PREFERRED_REMOTE_WIDTH;
        }, $url );
    }

    private static function attachment_meets_preferred_quality( $attachment_id ) {
        $metadata = wp_get_attachment_metadata( (int) $attachment_id );
        return is_array( $metadata ) && isset( $metadata['width'] ) && (int) $metadata['width'] >= self::MINIMUM_REUSE_WIDTH;
    }

    private static function validate_url( $url ) {
        $url = esc_url_raw( trim( (string) $url ) );
        if ( '' === $url ) {
            return new WP_Error( 'lunara_image_url_empty', 'A featured image URL is required.', array( 'status' => 400 ) );
        }
        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'lunara_image_url_scheme', 'The featured image URL must be http or https.', array( 'status' => 400 ) );
        }
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $host || in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
            return new WP_Error( 'lunara_image_url_host', 'The featured image URL host is not permitted.', array( 'status' => 400 ) );
        }
        return $url;
    }

    private static function default_alt( WP_Post $post ) {
        return sanitize_text_field( wp_strip_all_tags( get_the_title( $post ) ) );
    }

    private static function record_result( $post_id, $url, $result ) {
        if ( is_wp_error( $result ) ) {
            $payload = array(
                'ok'         => false,
                'source_url' => (string) $url,
                'error_code' => $result->get_error_code(),
                'message'    => $result->get_error_message(),
                'at_gmt'     => current_time( 'mysql', true ),
            );
        } else {
            $payload = array_merge(
                array( 'ok' => true, 'at_gmt' => current_time( 'mysql', true ) ),
                $result
            );
        }
        update_post_meta( (int) $post_id, self::RESULT_META, $payload );
    }

    private static function log( $post_id, $action, array $context ) {
        $entry = array_merge(
            array(
                'action'        => sanitize_key( $action ),
                'actor'         => 'Foundation Image Sideload',
                'client'        => 'Journal Foundation',
                'timestamp_gmt' => current_time( 'mysql', true ),
            ),
            $context
        );
        add_post_meta( (int) $post_id, '_lunara_journal_bridge_log', $entry, false );
    }

    private static function set_field( $post_id, $field, $value ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( $field, $value, $post_id );
            return;
        }
        update_post_meta( $post_id, $field, $value );
    }
}

Lunara_Journal_Image_Sideload::bootstrap();
