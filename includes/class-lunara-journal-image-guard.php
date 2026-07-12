<?php
/**
 * Deterministic featured-image presence and quality diagnostics.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Image_Guard {
    const HARD_MIN_WIDTH  = 800;
    const HARD_MIN_HEIGHT = 450;
    const PREFERRED_WIDTH = 1200;
    const PREFERRED_HEIGHT = 630;
    const LANDSCAPE_RATIO_MIN = 1.50;
    const LANDSCAPE_RATIO_MAX = 2.10;

    /**
     * Request-local inspection cache.
     *
     * @var array<int,array>
     */
    private static $cache = array();

    /**
     * Inspect the featured image attached to a Journal post.
     *
     * Missing, invalid, non-image, broken, dimensionless, and severely
     * undersized attachments are hard errors. Preferred-size, aspect-ratio,
     * and alt-text issues are warnings so Dalton can make the final crop or
     * replacement decision without losing the attachment.
     *
     * @param int $post_id Journal post ID.
     * @return array<string,mixed>
     */
    public static function inspect( $post_id ) {
        $post_id = absint( $post_id );
        if ( isset( self::$cache[ $post_id ] ) ) {
            return self::$cache[ $post_id ];
        }

        $result = array(
            'attachment_id'              => 0,
            'attached'                   => false,
            'usable'                     => false,
            'preferred_quality'          => false,
            'status'                     => 'missing',
            'mime_type'                  => '',
            'url'                        => '',
            'width'                      => 0,
            'height'                     => 0,
            'dimensions'                 => '',
            'aspect_ratio'               => 0.0,
            'landscape_friendly'         => false,
            'meets_hard_minimum'         => false,
            'meets_preferred_dimensions' => false,
            'alt_text'                   => '',
            'errors'                     => array(),
            'warnings'                   => array(),
            'requirements'               => array(
                'hard_minimum' => self::HARD_MIN_WIDTH . 'x' . self::HARD_MIN_HEIGHT,
                'preferred'    => self::PREFERRED_WIDTH . 'x' . self::PREFERRED_HEIGHT,
                'landscape_ratio_range' => self::LANDSCAPE_RATIO_MIN . '-' . self::LANDSCAPE_RATIO_MAX,
            ),
        );

        $attachment_id = absint( get_post_thumbnail_id( $post_id ) );
        if ( ! $attachment_id ) {
            $result['errors'][] = 'Featured image is required.';
            return self::$cache[ $post_id ] = $result;
        }

        $result['attachment_id'] = $attachment_id;
        $result['attached'] = true;

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            $result['status'] = 'unusable';
            $result['errors'][] = 'Featured image attachment could not be resolved in the media library.';
            return self::$cache[ $post_id ] = $result;
        }

        $mime = (string) get_post_mime_type( $attachment_id );
        $result['mime_type'] = $mime;
        if ( 0 !== strpos( strtolower( $mime ), 'image/' ) ) {
            $result['status'] = 'unusable';
            $result['errors'][] = 'Featured media must be an image attachment.';
            return self::$cache[ $post_id ] = $result;
        }

        $url = wp_get_attachment_url( $attachment_id );
        $result['url'] = is_string( $url ) ? $url : '';
        if ( '' === $result['url'] ) {
            $result['status'] = 'unusable';
            $result['errors'][] = 'Featured image file URL could not be resolved.';
            return self::$cache[ $post_id ] = $result;
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        $width  = is_array( $metadata ) && isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0;
        $height = is_array( $metadata ) && isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0;

        if ( ( ! $width || ! $height ) && function_exists( 'wp_get_attachment_image_src' ) ) {
            $src = wp_get_attachment_image_src( $attachment_id, 'full' );
            if ( is_array( $src ) ) {
                $width  = isset( $src[1] ) ? absint( $src[1] ) : $width;
                $height = isset( $src[2] ) ? absint( $src[2] ) : $height;
            }
        }

        $result['width'] = $width;
        $result['height'] = $height;
        $result['dimensions'] = $width && $height ? $width . 'x' . $height : '';
        $result['aspect_ratio'] = $height > 0 ? round( $width / $height, 3 ) : 0.0;
        $result['alt_text'] = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

        if ( ! $width || ! $height ) {
            $result['status'] = 'unusable';
            $result['errors'][] = 'Featured image dimensions could not be determined.';
            return self::$cache[ $post_id ] = $result;
        }

        $result['meets_hard_minimum'] = $width >= self::HARD_MIN_WIDTH && $height >= self::HARD_MIN_HEIGHT;
        $result['meets_preferred_dimensions'] = $width >= self::PREFERRED_WIDTH && $height >= self::PREFERRED_HEIGHT;
        $result['landscape_friendly'] = $result['aspect_ratio'] >= self::LANDSCAPE_RATIO_MIN && $result['aspect_ratio'] <= self::LANDSCAPE_RATIO_MAX;

        if ( ! $result['meets_hard_minimum'] ) {
            $result['errors'][] = sprintf(
                'Featured image is too small (%1$s). Minimum usable dimensions are %2$dx%3$d.',
                $result['dimensions'],
                self::HARD_MIN_WIDTH,
                self::HARD_MIN_HEIGHT
            );
        }

        if ( $result['meets_hard_minimum'] && ! $result['meets_preferred_dimensions'] ) {
            $result['warnings'][] = sprintf(
                'Featured image is usable but below the preferred %1$dx%2$d resolution (%3$s attached).',
                self::PREFERRED_WIDTH,
                self::PREFERRED_HEIGHT,
                $result['dimensions']
            );
        }

        if ( ! $result['landscape_friendly'] ) {
            $result['warnings'][] = 'Featured image aspect ratio is not landscape-friendly for Journal cards and social previews.';
        }

        if ( '' === $result['alt_text'] ) {
            $result['warnings'][] = 'Featured image alt text is missing.';
        }

        $result['usable'] = empty( $result['errors'] );
        $result['preferred_quality'] = $result['usable'] && $result['meets_preferred_dimensions'] && $result['landscape_friendly'];

        if ( ! $result['usable'] ) {
            $result['status'] = 'unusable';
        } elseif ( ! empty( $result['warnings'] ) ) {
            $result['status'] = 'needs_attention';
        } else {
            $result['status'] = 'ready';
        }

        return self::$cache[ $post_id ] = $result;
    }

    /**
     * Clear one post or the complete request-local cache.
     *
     * @param int $post_id Optional post ID.
     * @return void
     */
    public static function clear_cache( $post_id = 0 ) {
        $post_id = absint( $post_id );
        if ( $post_id ) {
            unset( self::$cache[ $post_id ] );
            return;
        }
        self::$cache = array();
    }
}
