<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Shared field sanitization for BH3 WordPress activity records. */
final class BH_Activity_Fields_3 {
    public static function text( $value, $limit = 255 ) {
        return sanitize_text_field( wp_unslash( $value ) );
    }
    public static function age( $value ) {
        $raw = trim( (string) wp_unslash( $value ) );
        if ( $raw === '' ) { return null; }
        $age = filter_var( $raw, FILTER_VALIDATE_INT );
        return $age !== false && $age >= 0 && $age <= 216 ? $age : new WP_Error( 'bh3_age', 'Invalid age.' );
    }
    public static function price( $value ) {
        $raw = trim( (string) wp_unslash( $value ) );
        if ( $raw === '' ) { return null; }
        return preg_match( '/^[0-9]{1,8}(?:[.][0-9]{1,2})?$/D', $raw ) && (float) $raw <= 99999999.99 ? round( (float) $raw, 2 ) : new WP_Error( 'bh3_price', 'Invalid price.' );
    }
}
