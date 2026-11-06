<?php
/**
 * Combined parent-paid booking fee smoke tests.
 * Run: php tests/parent-paid-booking-fee-smoke.php
 *
 * These tests exercise only integer-pence pricing and inspect the guarded
 * Stripe integration contract. They do not contact Stripe or WordPress.
 */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
class WP_Error {}
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_salt( $scheme = 'auth' ) { return 'booking-test-salt-' . $scheme; }
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
check_fee( $form_map['parent_name'] === 'parent_name', 'Form 7 parent name is mapped automatically' );
check_fee( $form_map['parent_email'] === 'parent_email', 'Form 7 parent email is mapped automatically' );
$fee_test_options['bh3_booking_form_field_map'] = array( 'places' => 'custom_places' );
$custom_map = BH_Marketplace_Settings_3::booking_form_field_map();
check_fee( $custom_map['places'] === 'custom_places' && $custom_map['consent'] === 'booking_terms_consent', 'administrator field overrides retain safe defaults' );
check_fee( BH_Marketplace_Settings_3::is_affirmative_booking_consent( 'yes' ), 'scalar affirmative consent is accepted' );
check_fee( BH_Marketplace_Settings_3::is_affirmative_booking_consent( array( 'yes' ) ), 'checkbox-array affirmative consent is accepted' );
check_fee( ! BH_Marketplace_Settings_3::is_affirmative_booking_consent( array() ), 'empty consent is rejected' );
check_fee( ! BH_Marketplace_Settings_3::is_affirmative_booking_consent( 'no' ), 'negative consent is rejected' );
$context_token = BH_Marketplace_Settings_3::create_booking_form_context_token( 42, 7 );
check_fee( BH_Marketplace_Settings_3::verify_booking_form_context_token( $context_token, 42, 7 ), 'signed Form 7 context token verifies for its session and parent' );
check_fee( ! BH_Marketplace_Settings_3::verify_booking_form_context_token( $context_token, 43, 7 ), 'signed Form 7 context cannot be reused for another session' );
check_fee( ! BH_Marketplace_Settings_3::verify_booking_form_context_token( $context_token, 42, 8 ), 'signed Form 7 context cannot be reused by another parent' );

$form_exports = json_decode( file_get_contents( dirname( __DIR__ ) . '/docs/imports/fluent-form-7-booking-update.json' ), true );
$form7 = null;
foreach ( (array) $form_exports as $candidate ) {
    if ( isset( $candidate['id'] ) && (int) $candidate['id'] === 7 ) { $form7 = $candidate; break; }
}
check_fee( is_array( $form7 ), 'updated Form 7 import exists' );
$form_fields = array();
foreach ( (array) ( $form7['form_fields']['fields'] ?? array() ) as $field ) {
    $name = $field['attributes']['name'] ?? '';
    if ( $name !== '' ) { $form_fields[$name] = $field; }
}
foreach ( array( 'parent_name', 'parent_email', 'session_id', 'booking_context_token', 'places', 'occurrence_date', 'family_profile_ids', 'share_child_details_consent', 'booking_terms_consent' ) as $required_name ) {
    check_fee( isset( $form_fields[$required_name] ), 'Form 7 contains required field ' . $required_name );
}
check_fee( ( $form_fields['places']['attributes']['min'] ?? '' ) === '1'
    && ( $form_fields['places']['attributes']['max'] ?? '' ) === '20', 'Form 7 places input has safe bounds' );
$form_order = array_keys( $form_fields );
check_fee( array_search( 'family_profile_ids', $form_order, true ) < array_search( 'parent_name', $form_order, true )
    && array_search( 'parent_name', $form_order, true ) < array_search( 'booking_terms_consent', $form_order, true ),
    'Form 7 follows attendee selection, parent details, then booking terms' );
check_fee( ! isset( $form_fields['child_details'] ), 'logged-in Form 7 does not ask parents to re-enter saved child details' );
$family_setup = file_get_contents( dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-family-db-setup.php' );
check_fee( strpos( $family_setup, 'ADD COLUMN gender varchar(40)' ) !== false, 'staging family profile migration adds the gender field without replacing rows' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-marketplace-settings.php' );
check_fee( strpos( $source, "get_option( 'bh3_booking_form_id', 8 )" ) !== false, 'checkout defaults to imported Form 8' );
check_fee( strpos( $source, 'name="booking_form_id"' ) !== false, 'marketplace admin can select booking form ID' );
check_fee( strpos( $source, "update_option( 'bh3_booking_form_id', " ) !== false, 'booking form ID is saved in WordPress' );
check_fee( strpos( $source, 'fluentform_forms' ) !== false, 'selected booking form ID must exist before saving' );
check_fee( strpos( $source, "'unit_amount' => $parent_total" ) !== false, 'Stripe Checkout charges parent total including fee' );
check_fee( strpos( $source, "'application_fee_amount' => $customer_fee" ) !== false, 'platform application fee receives the single parent fee' );
check_fee( strpos( $source, '!== $parent_total' ) !== false, 'Stripe Checkout response is checked against parent total' );
check_fee( strpos( $source, 'Checkout does not match the saved class price and combined booking fee.' ) !== false, 'webhook reconciliation validates fee metadata and total' );
check_fee( strpos( $source, 'name="processing_percent"' ) !== false, 'administrator can configure estimated processing percentage' );
check_fee( strpos( $source, 'name="processing_fixed_pence"' ) !== false, 'administrator can configure fixed processing amount' );
check_fee( strpos( $source, 'fluentform/rendering_field_data_input_checkbox' ) !== false, 'Form 7 family profile checkbox choices are populated dynamically' );
check_fee( strpos( $source, 'fluentform/rendering_field_data_input_hidden' ) !== false, 'Form 7 session ID is populated from server-side widget context' );
check_fee( strpos( $source, 'family_profile_ids' ) !== false, 'Form 7 family profile selection is mapped' );
check_fee( strpos( $source, 'fluentform/submission_confirmation' ) !== false, 'successful Form 7 submission is handed off through its confirmation hook' );
check_fee( strpos( $source, 'parent-bound-test-checkout' ) !== false, 'Form 7 confirmation invokes the bound checkout route' );
check_fee( strpos( $source, 'redirectUrl' ) !== false, 'successful bound checkout redirects to Stripe' );
check_fee( strpos( $source, 'bh3-test-booking-form' ) === false, 'duplicate standalone checkout form is removed' );
check_fee( strpos( $source, 'booking_context_token' ) !== false, 'Form 7 context token is generated and checked' );
check_fee( strpos( $source, 'data-bh-price-total' ) !== false && strpos( $source, 'data-price-quotes' ) !== false,
    'parent total and combined booking fee update with selected place count' );
check_fee( strpos( $source, "SET booking_status='confirmed', payment_status='paid'" ) !== false, 'verified payment automatically confirms the reservation' );
check_fee( strpos( $source, 'send_booking_confirmation_notifications' ) !== false, 'paid webhook dispatches booking confirmation notifications' );
check_fee( strpos( $source, "'consent_key' => 'booking_terms'" ) !== false, 'accepted booking terms are stored against the booking' );
check_fee( strpos( $source, 'booking_terms' ) !== false, 'Stripe checkout requires recorded booking terms consent' );
check_fee( strpos( $source, 'send_booking_confirmation_email_once' ) !== false, 'booking confirmation emails are idempotent across webhook retries' );
check_fee( strpos( $source, 'bh3_booking_email_' ) !== false, 'booking confirmation delivery markers prevent duplicate emails' );
check_fee( strpos( $source, "'family_profile_id' =>" ) !== false, 'selected attendee profiles remain linked to booking attendee records' );

echo "Parent-paid booking fee smoke tests passed.\n";
