<?php
/**
 * Pure binding checks for Fluent Form #7 and an already-authorised booking row.
 * Caller MUST load booking from the database, verify actor access, and provide
 * the authoritative row. This class neither queries nor modifies the database.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Booking_Form7_Binding_3 {
    public static function validate( $form_id, $submission, $booking ) {
        if ( (int) $form_id !== BH_Booking_Consent_Contract_3::FORM_ID ) {
            return array( 'ok' => false, 'reason' => 'wrong_form' );
        }
        if ( ! is_array( $submission ) || ! is_array( $booking ) ) {
            return array( 'ok' => false, 'reason' => 'invalid_input' );
        }
        // All required IDs must be positive and agree with trusted booking data.
        foreach ( array( 'activity_id', 'session_id', 'organiser_id' ) as $key ) {
            if ( ! isset( $submission[$key], $booking[$key] )
                || ! ctype_digit( (string) $submission[$key] )
                || (int) $submission[$key] < 1
                || (int) $submission[$key] !== (int) $booking[$key] ) {
                return array( 'ok' => false, 'reason' => 'booking_mismatch' );
            }
        }
        $submitted_ref = $submission['booking_reference'] ?? null;
        $stored_ref = $booking['public_reference'] ?? null;
        if ( ! is_string( $submitted_ref ) || ! is_string( $stored_ref )
            || '' === $stored_ref || ! hash_equals( $stored_ref, $submitted_ref ) ) {
            return array( 'ok' => false, 'reason' => 'booking_mismatch' );
        }
        if ( ! isset( $submission['places'], $booking['places'] )
            || ! ctype_digit( (string) $submission['places'] )
            || (int) $submission['places'] < 1
            || (int) $submission['places'] !== (int) $booking['places'] ) {
            return array( 'ok' => false, 'reason' => 'places_mismatch' );
        }
        $decisions = BH_Booking_Consent_Contract_3::decisions( $submission );
        if ( 'yes' !== $decisions['booking_terms'] ) {
            return array( 'ok' => false, 'reason' => 'terms_required' );
        }
        return array( 'ok' => true, 'reason' => 'valid', 'consents' => $decisions );
    }
}
