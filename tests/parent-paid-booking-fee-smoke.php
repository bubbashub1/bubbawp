<?php
/**
 * Combined parent-paid booking fee smoke tests.
 * Run: php tests/parent-paid-booking-fee-smoke.php
 *
 * These tests exercise only integer-pence pricing and inspect the guarded
 * Stripe integration contract. They do not contact Stripe or WordPress.
 */
define( 'ABSPATH', __DIR__ );
class WP_Error {}
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$fee_test_options = array();
function get_option( $key, $default = false ) {
    global $fee_test_options;
    if ( $key === 'bh3_commission_percent' ) { return '2.50'; }
    return array_key_exists( $key, $fee_test_options ) ? $fee_test_options[$key] : $default;
}
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-marketplace-settings.php';

function check_fee( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$quote = BH_Marketplace_Settings_3::parent_paid_fee_preview( 500 );
check_fee( ! is_wp_error( $quote ), '£5.00 class quote is valid' );
check_fee( $quote['leader_price_pence'] === 500, 'leader class price remains £5.00' );
check_fee( $quote['platform_component_pence'] === 13, '2.5% platform component rounds half up to 13p' );
check_fee( $quote['parent_total_pence'] === 542, 'parent total gross-ups estimated 1.5% + 20p processing' );
check_fee( $quote['booking_fee_pence'] === 42, 'one customer-facing fee covers platform and processing components' );
check_fee( $quote['booking_fee_pence'] === $quote['platform_component_pence'] + $quote['estimated_processing_pence'], 'fee components reconcile' );
check_fee( $quote['parent_total_pence'] - $quote['booking_fee_pence'] === 500, 'leader receives full advertised class price before Stripe processing' );

global $fee_test_options;
$fee_test_options['bh3_parent_booking_fee_settings'] = array( 'processing_percent' => '2.00', 'processing_fixed_pence' => 25 );
$custom = BH_Marketplace_Settings_3::parent_paid_fee_preview( 500 );
check_fee( $custom['processing_percent_assumption'] === 2.0, 'admin-configured processing percentage is used' );
check_fee( $custom['processing_fixed_pence_assumption'] === 25, 'admin-configured fixed processing amount is used' );
check_fee( $custom['parent_total_pence'] > $quote['parent_total_pence'], 'updated processing assumptions change parent quote' );
unset( $fee_test_options['bh3_parent_booking_fee_settings'] );

$form_map = BH_Marketplace_Settings_3::booking_form_field_map();
check_fee( $form_map['session_id'] === 'session_id', 'Form 7 session field is mapped automatically' );
check_fee( $form_map['occurrence_date'] === 'occurrence_date', 'Form 7 occurrence date field is mapped automatically' );
check_fee( $form_map['places'] === 'places', 'Form 7 places field is mapped automatically' );
check_fee( $form_map['consent'] === 'booking_terms_consent', 'Form 7 booking terms checkbox is mapped automatically' );
check_fee( $form_map['profile_ids'] === 'family_profile_ids', 'Form 7 family profile field is mapped automatically' );
check_fee( $form_map['share_consent'] === 'share_child_details_consent', 'Form 7 attendee sharing consent is mapped automatically' );
$fee_test_options['bh3_booking_form_field_map'] = array( 'places' => 'custom_places' );
$custom_map = BH_Marketplace_Settings_3::booking_form_field_map();
check_fee( $custom_map['places'] === 'custom_places' && $custom_map['consent'] === 'booking_terms_consent', 'administrator field overrides retain safe defaults' );
check_fee( BH_Marketplace_Settings_3::is_affirmative_booking_consent( 'yes' ), 'scalar affirmative consent is accepted' );
check_fee( BH_Marketplace_Settings_3::is_affirmative_booking_consent( array( 'yes' ) ), 'checkbox-array affirmative consent is accepted' );
check_fee( ! BH_Marketplace_Settings_3::is_affirmative_booking_consent( array() ), 'empty consent is rejected' );
check_fee( ! BH_Marketplace_Settings_3::is_affirmative_booking_consent( 'no' ), 'negative consent is rejected' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-marketplace-settings.php' );
check_fee( strpos( $source, "'unit_amount' => $parent_total" ) !== false, 'Stripe Checkout charges parent total including fee' );
check_fee( strpos( $source, "'application_fee_amount' => $customer_fee" ) !== false, 'platform application fee receives the single parent fee' );
check_fee( strpos( $source, '!== $parent_total' ) !== false, 'Stripe Checkout response is checked against parent total' );
check_fee( strpos( $source, 'Checkout does not match the saved class price and combined booking fee.' ) !== false, 'webhook reconciliation validates fee metadata and total' );
check_fee( strpos( $source, 'name="processing_percent"' ) !== false, 'administrator can configure estimated processing percentage' );
check_fee( strpos( $source, 'name="processing_fixed_pence"' ) !== false, 'administrator can configure fixed processing amount' );
check_fee( strpos( $source, 'fluentform/rendering_field_data_input_checkbox' ) !== false, 'Form 7 family profile checkbox choices are populated dynamically' );
check_fee( strpos( $source, 'fluentform/rendering_field_data_input_hidden' ) !== false, 'Form 7 session ID is populated from server-side widget context' );
check_fee( strpos( $source, 'family_profile_ids' ) !== false, 'Form 7 family profile selection is mapped' );

echo "Parent-paid booking fee smoke tests passed.\n";
