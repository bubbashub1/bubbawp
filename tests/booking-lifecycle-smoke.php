<?php
/**
 * Isolated booking lifecycle smoke tests. Run: php tests/booking-lifecycle-smoke.php
 * WordPress functions are stubbed so the tests cannot access the database.
 */
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 42; }
function user_can( $id, $cap ) { return $id === 1 && $cap === 'manage_options' || $id === 42 && $cap === 'read'; }
function get_user_meta( $id, $key, $single ) { return $id === 42 && $key === 'bh3_organiser_id' ? 17 : ''; }
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-booking-lifecycle.php';
function check( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}
$states = BH_Booking_Lifecycle_3::statuses();
check( count( $states ) === 6, 'six explicit reservation states' );
check( BH_Booking_Lifecycle_3::can_transition( 'reserved', 'confirmed' ), 'reserved to confirmed' );
check( BH_Booking_Lifecycle_3::can_transition( 'confirmed', 'enrolled' ), 'confirmed to enrolled' );
check( BH_Booking_Lifecycle_3::can_transition( 'awaiting_confirmation', 'cancelled' ), 'cancellation allowed' );
check( ! BH_Booking_Lifecycle_3::can_transition( 'cancelled', 'enrolled' ), 'cancelled terminal' );
check( ! BH_Booking_Lifecycle_3::can_transition( 'enrolled', 'reserved' ), 'no backwards enrollment' );
check( ! BH_Booking_Lifecycle_3::can_transition( 'unknown', 'confirmed' ), 'unknown state denied' );
check( BH_Booking_Lifecycle_3::can_manage_organiser( 17, 42 ), 'linked leader can access own organiser' );
check( ! BH_Booking_Lifecycle_3::can_manage_organiser( 18, 42 ), 'linked leader cannot access another organiser' );
check( ! BH_Booking_Lifecycle_3::can_manage_organiser( 17, 99 ), 'unprivileged user denied' );
check( BH_Booking_Lifecycle_3::can_manage_organiser( 18, 1 ), 'administrator allowed' );
check( ! BH_Booking_Lifecycle_3::can_manage_organiser( 0, 1 ), 'invalid organiser denied' );
echo "Booking lifecycle smoke tests passed.\n";
