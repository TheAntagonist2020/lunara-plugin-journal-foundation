<?php
/**
 * Protocol constants shared by LUNARA Journal Foundation and Dispatch.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Protocol {
    const VERSION        = '1.1.1';
    const SCHEMA_VERSION = '1.1.1';

    public static function is_compatible( $version ) {
        if ( ! is_string( $version ) || ! preg_match( '/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches ) ) {
            return false;
        }
        return (int) $matches[1] === 1;
    }

    public static function health() {
        return array(
            'protocol_version' => self::VERSION,
            'schema_version'   => self::SCHEMA_VERSION,
            'compatible_major' => 1,
        );
    }
}
