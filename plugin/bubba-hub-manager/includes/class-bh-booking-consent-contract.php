<?php
/** Fluent Form #7 booking contract. No submission hooks or database writes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Booking_Consent_Contract_3 {
    public const FORM_ID = 7;
    public const CONSENT_VERSION = 'booking-v1';

    public static function fields() {
        return array(
            'parent_name', 'parent_email', 'parent_phone',
            'activity_id', 'session_id', 'organiser_id', 'booking_reference',
            'session_selection', 'places', 'family_profile_ids',
            'child_details', 'booking_notes',
            'share_child_details_consent', 'booking_terms_consent',
        );
    }

    public static function consent_keys() {
        return array(
            'share_child_details_consent' => 'share_child_details',
            'booking_terms_consent' => 'booking_terms',
        );
    }

    /**
     * Normalise checkbox input without assuming that the field was submitted.
     * Fluent Forms may return a scalar or an array depending on configuration.
     */
    public static function checked( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( self::checked( $item ) ) { return true; }
            }
            return false;
        }
        if ( ! is_scalar( $value ) ) { return false; }
        return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on', 'checked', 'accepted' ), true );
    }

    /**
     * Returns only explicit consent decisions. An absent checkbox means no.
     * This does not validate form identity or booking ownership.
     */
    public static function decisions( $submission ) {
        $submission = is_array( $submission ) ? $submission : array();
        $decisions = array();
        foreach ( self::consent_keys() as $field => $key ) {
            $decisions[$key] = self::checked( $submission[$field] ?? null ) ? 'yes' : 'no';
        }
        return $decisions;
    }
}
