<?php
/**
 * Read-only Form #7 booking resolver.
 * Guest ownership needs a separately verified one-time token; a public reference
 * and hidden form IDs alone are never sufficient to authorise a submission.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Booking_Form7_Owner_3 {
    public static function resolve_for_current_customer( $form_id, $fields ) {
        global $wpdb;
        if ( (int) $form_id !== BH_Booking_Consent_Contract_3::FORM_ID || ! is_array( $fields ) ) {
            return array( 'ok' => false, 'reason' => 'invalid_form' );
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return array( 'ok' => false, 'reason' => 'guest_verification_required' );
        }
        $reference = $fields['booking_reference'] ?? null;
        if ( ! is_string( $reference ) || '' === $reference || strlen( $reference ) > 36 ) {
            return array( 'ok' => false, 'reason' => 'invalid_reference' );
        }
        $tables = BH_Schema_3::table_names();
        $table = $tables['bookings'];
        if ( ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
            return array( 'ok' => false, 'reason' => 'storage_unavailable' );
        }
        // Query by both public reference AND authenticated customer ID.
        // Do not grant organiser/admin impersonation in the parent-facing form.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, public_reference, activity_id, session_id, organiser_id, places, customer_user_id, booking_submission_id
                 FROM `{$table}` WHERE public_reference = %s AND customer_user_id = %d LIMIT 1",
                $reference,
                $user_id
            ),
            ARRAY_A
        );
        if ( ! is_array( $row ) ) {
            return array( 'ok' => false, 'reason' => 'booking_not_found' );
        }
        if ( ! empty( $row['booking_submission_id'] ) ) {
            return array( 'ok' => false, 'reason' => 'already_submitted' );
        }
        $validated = BH_Booking_Form7_Binding_3::validate( $form_id, $fields, $row );
        if ( empty( $validated['ok'] ) ) {
            return $validated;
        }
        return array(
            'ok' => true,
            'reason' => 'verified_customer_booking',
            'booking_id' => (int) $row['id'],
            'consents' => $validated['consents'],
        );
    }
}
