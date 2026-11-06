<?php
/**
 * Combined parent-paid booking fee smoke tests.
 * Run: php tests/parent-paid-booking-fee-smoke.php
 *
 * These tests exercise only integer-pence pricing and inspect the guarded
 * Stripe integration contract. They do not contact Stripe or WordPress.
 */
define( 'ABSPATH', __DIR__ );
function get_option( $key, $default = false ) {
    return $key === 'bh3_commission_percent' ? '2.50' : $default;
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

$source = file_get_contents( dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-marketplace-settings.php' );
check_fee( strpos( $source, "'unit_amount' => $parent_total" ) !== false, 'Stripe Checkout charges parent total including fee' );
check_fee( strpos( $source, "'application_fee_amount' => $customer_fee" ) !== false, 'platform application fee receives the single parent fee' );
check_fee( strpos( $source, "!== $parent_total" ) !== false, 'Stripe Checkout response is checked against parent total' );
check_fee( strpos( $source, 'Checkout does not match the saved class price and combined booking fee.' ) !== false, 'webhook reconciliation validates fee metadata and total' );

echo "Parent-paid booking fee smoke tests passed.\\n";
