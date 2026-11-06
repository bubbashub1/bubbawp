<?php
/**
 * Pure booking lifecycle rules. No writes, migrations or payment side effects.
 *
 * Booking status describes a reservation; payment status is separate.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Booking_Lifecycle_3 {
    public static function statuses() {
        return array(
            'reserved' => 'Reserved',
            'awaiting_confirmation' => 'Awaiting confirmation',
            'confirmed' => 'Confirmed',
            'enrolled' => 'Enrolled',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
        );
    }

    public static function transitions() {
        return array(
            'reserved' => array( 'awaiting_confirmation', 'confirmed', 'cancelled', 'expired' ),
            'awaiting_confirmation' => array( 'confirmed', 'cancelled', 'expired' ),
            'confirmed' => array( 'enrolled', 'cancelled' ),
            'enrolled' => array( 'cancelled' ),
            'cancelled' => array(),
            'expired' => array(),
        );
    }

    public static function can_transition( $from, $to ) {
        $transitions = self::transitions();
        return isset( $transitions[$from] ) && in_array( $to, $transitions[$from], true );
    }

    /**
     * Determine whether the current user may manage a booking owned by an organiser.
     * Admin access is explicit; leaders must have a matching organiser association.
     * This does not authorize refunds or Stripe operations.
     */
    public static function can_manage_organiser( $organiser_id, $user_id = 0 ) {
        $organiser_id = absint( $organiser_id );
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $organiser_id || ! $user_id ) { return false; }
        if ( user_can( $user_id, 'manage_options' ) ) { return true; }
        if ( ! user_can( $user_id, 'read' ) ) { return false; }
        return absint( get_user_meta( $user_id, 'bh3_organiser_id', true ) ) === $organiser_id;
    }
}
