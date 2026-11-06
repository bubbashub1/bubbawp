<?php
/** Run with: php tests/booking-form7-binding-smoke.php */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-booking-consent-contract.php';
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-booking-form7-binding.php';
function check_binding( $ok, $label ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
}
$booking = array( 'activity_id' => 10, 'session_id' => 20, 'organiser_id' => 30, 'public_reference' => 'BH-TEST-001', 'places' => 2 );
$form = array( 'activity_id' => '10', 'session_id' => '20', 'organiser_id' => '30', 'booking_reference' => 'BH-TEST-001', 'places' => '2', 'booking_terms_consent' => 'yes' );
$result = BH_Booking_Form7_Binding_3::validate( 7, $form, $booking );
check_binding( $result['ok'] && $result['consents']['share_child_details'] === 'no', 'optional sharing defaults to no' );
check_binding( ! BH_Booking_Form7_Binding_3::validate( 6, $form, $booking )['ok'], 'wrong form rejected' );
foreach ( array( 'activity_id', 'session_id', 'organiser_id', 'booking_reference', 'places' ) as $field ) {
    $tampered = $form;
    $tampered[$field] = '999';
    check_binding( ! BH_Booking_Form7_Binding_3::validate( 7, $tampered, $booking )['ok'], "tampered {$field} rejected" );
}
$missing_terms = $form;
unset( $missing_terms['booking_terms_consent'] );
check_binding( BH_Booking_Form7_Binding_3::validate( 7, $missing_terms, $booking )['reason'] === 'terms_required', 'terms acceptance required' );
$sharing = $form;
$sharing['share_child_details_consent'] = array( 'yes' );
check_binding( BH_Booking_Form7_Binding_3::validate( 7, $sharing, $booking )['consents']['share_child_details'] === 'yes', 'explicit sharing preserved' );
echo "Form 7 binding smoke tests passed.\n";
