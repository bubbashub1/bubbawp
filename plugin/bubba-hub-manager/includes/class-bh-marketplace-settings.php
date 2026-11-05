<?php
/** Staging-only marketplace preferences. This module never charges or transfers money. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Marketplace_Settings_3 {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_save_commission', array( __CLASS__, 'save_commission' ) );
        add_action( 'admin_post_bh3_verify_stripe', array( __CLASS__, 'verify_stripe' ) );
        add_action( 'admin_post_bh3_stripe_onboard', array( __CLASS__, 'stripe_onboard' ) );
        add_action( 'admin_post_bh3_save_payout_method', array( __CLASS__, 'save_method' ) );
        add_shortcode( 'bh_leader_payout_settings', array( __CLASS__, 'leader_form' ) );
    }
    public static function rate() {
        $value = get_option( 'bh3_commission_percent', '3.00' );
        return is_numeric( $value ) ? min( 100, max( 0, (float) $value ) ) : 3.0;
    }
    public static function split_pence( $gross_pence ) {
        $gross = max( 0, absint( $gross_pence ) );
        $commission = (int) round( $gross * self::rate() / 100, 0, PHP_ROUND_HALF_UP );
        return array( 'gross_pence' => $gross, 'commission_pence' => $commission, 'leader_before_fees_pence' => $gross - $commission );
    }
    public static function policy() {
        $defaults = array( 'processing_fees' => 'leader', 'payout_schedule' => 'weekly', 'refund_policy' => 'manual_review' );
        $saved = get_option( 'bh3_marketplace_policy', array() );
        $saved = is_array( $saved ) ? $saved : array();
        return array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
    }
    public static function booking_snapshot( $gross_pence ) {
        return array_merge( self::split_pence( $gross_pence ), array(
            'commission_rate_percent' => number_format( self::rate(), 2, '.', '' ),
            'policy' => self::policy(),
            'currency' => 'gbp',
        ) );
    }
    /**
     * Server-authoritative booking quote. Not a capacity hold or a payment request.
     * Never accept an amount or commission from browser/Fluent Forms input.
     */
    public static function quote_session( $session_id, $places = 1 ) {
        $session_id = absint( $session_id );
        $places = filter_var( $places, FILTER_VALIDATE_INT );
        if ( ! $session_id || $places === false || $places < 1 || $places > 20 ) {
            return new WP_Error( 'invalid_booking_request', 'Invalid session or number of places.' );
        }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $activities = $wpdb->prefix . 'activities';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.id,s.status,a.id AS activity_id,a.organiser_id,a.status AS activity_status
             FROM `{$sessions}` s INNER JOIN `{$activities}` a ON a.id=s.activity_id
             WHERE s.id=%d LIMIT 1", $session_id
        ), ARRAY_A );
        if ( ! $row || $row['activity_status'] !== 'published' || $row['status'] !== 'scheduled' ) {
            return new WP_Error( 'session_unavailable', 'This session is not available for booking.' );
        }
        $configs = get_option( 'bh3_session_booking_config', array() );
        $config = is_array( $configs ) ? ( $configs[$session_id] ?? null ) : null;
        if ( ! is_array( $config ) || empty( $config['enabled'] ) ) {
            return new WP_Error( 'booking_disabled', 'Bookings are not enabled for this session.' );
        }
        $price = isset( $config['price'] ) && is_scalar( $config['price'] ) ? (string) $config['price'] : '';
        if ( ! preg_match( '/^(?:0|[1-9][0-9]{0,5})(?:\\.[0-9]{1,2})?$/', $price ) || (float) $price > 100000 ) {
            return new WP_Error( 'invalid_server_price', 'Session price is not configured correctly.' );
        }
        $parts = explode( '.', $price, 2 );
        $unit_pence = (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '', 2, '0' );
        $gross_pence = $unit_pence * $places;
        if ( $gross_pence < 1 ) {
            return new WP_Error( 'free_session', 'Free sessions do not require Stripe checkout.' );
        }
        $capacity = isset( $config['capacity'] ) ? absint( $config['capacity'] ) : 0;
        if ( $capacity && $places > $capacity ) {
            return new WP_Error( 'capacity_exceeded', 'Requested places exceed session capacity.' );
        }
        return array_merge( self::booking_snapshot( $gross_pence ), array(
            'session_id' => $session_id,
            'activity_id' => (int) $row['activity_id'],
            'organiser_id' => (int) $row['organiser_id'],
            'places' => $places,
            'unit_price_pence' => $unit_pence,
            'capacity' => $capacity,
            'capacity_checked_against_existing_bookings' => false,
            'checkout_enabled' => false,
        ) );
    }
    /**
     * Read availability for one occurrence. Pending reservations count only until expiry.
     * This is a read-only preview; reservation creation must use an atomic DB lock.
     */
    public static function occurrence_availability( $session_id, $occurrence_date ) {
        $session_id = absint( $session_id );
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $session_id || ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date ) {
            return new WP_Error( 'invalid_occurrence', 'Invalid session date.' );
        }
        $configs = get_option( 'bh3_session_booking_config', array() );
        $config = is_array( $configs ) ? ( $configs[$session_id] ?? array() ) : array();
        if ( empty( $config['enabled'] ) || empty( $config['capacity'] ) ) {
            return new WP_Error( 'capacity_not_configured', 'A capacity must be configured before paid checkout.' );
        }
        $capacity = absint( $config['capacity'] );
        if ( $capacity < 1 ) { return new WP_Error( 'invalid_capacity', 'Invalid session capacity.' ); }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return new WP_Error( 'bookings_table_missing', 'Booking storage has not been installed.' );
        }
        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(places),0) FROM `{$table}`
             WHERE session_id=%d AND occurrence_date=%s
             AND (payment_status='paid' OR (payment_status='pending' AND reservation_expires_at > UTC_TIMESTAMP()))",
            $session_id, $occurrence_date
        ) );
        return array(
            'session_id' => $session_id, 'occurrence_date' => $occurrence_date,
            'capacity' => $capacity, 'reserved_or_paid' => $used,
            'available' => max( 0, $capacity - $used ),
            'authoritative_reservation_created' => false,
        );
    }
    /**
     * Internal-only atomic reservation primitive. No public action is registered.
     * Requires an existing authenticated customer and an installed bookings table.
     */
    /**
     * Confirm a requested calendar date is an actual scheduled occurrence.
     * Reject unsupported frequency rules rather than taking payment for a non-event.
     */
    public static function validate_occurrence( $session_id, $occurrence_date ) {
        $session_id = absint( $session_id );
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $session_id || ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date ) {
            return new WP_Error( 'invalid_occurrence', 'Invalid session date.' );
        }
        $today = new DateTimeImmutable( 'today', wp_timezone() );
        if ( $date < $today || $date > $today->modify( '+365 days' ) ) {
            return new WP_Error( 'date_out_of_range', 'Session date is outside the bookable range.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT day_of_week,start_date,end_date,frequency,term_time_only,status FROM `{$table}` WHERE id=%d LIMIT 1",
            $session_id
        ), ARRAY_A );
        if ( ! $rule || $rule['status'] !== 'scheduled' ) {
            return new WP_Error( 'session_unavailable', 'Session is not scheduled.' );
        }
        if ( $rule['frequency'] === 'weekly' ) {
            if ( (int) $rule['day_of_week'] !== (int) $date->format( 'N' ) ) {
                return new WP_Error( 'wrong_weekday', 'This class does not run on the selected day.' );
            }
        } elseif ( $rule['frequency'] === 'once' || $rule['frequency'] === 'single' ) {
            if ( empty( $rule['start_date'] ) || $rule['start_date'] !== $occurrence_date ) {
                return new WP_Error( 'wrong_date', 'This class does not run on the selected date.' );
            }
        } else {
            return new WP_Error( 'unsupported_frequency', 'This timetable type is not ready for paid booking.' );
        }
        if ( ( ! empty( $rule['start_date'] ) && $occurrence_date < $rule['start_date'] )
            || ( ! empty( $rule['end_date'] ) && $occurrence_date > $rule['end_date'] ) ) {
            return new WP_Error( 'outside_schedule', 'This date is outside the class schedule.' );
        }
        if ( ! empty( $rule['term_time_only'] ) ) {
            $ranges = get_option( 'bh3_term_date_ranges', array() );
            $valid = false;
            foreach ( is_array( $ranges ) ? $ranges : array() as $range ) {
                if ( is_array( $range ) && ! empty( $range['start'] ) && ! empty( $range['end'] )
                    && $occurrence_date >= $range['start'] && $occurrence_date <= $range['end'] ) {
                    $valid = true; break;
                }
            }
            if ( ! $valid ) { return new WP_Error( 'outside_term', 'No confirmed term-time session on this date.' ); }
        }
        return true;
    }
    public static function reserve_occurrence( $session_id, $occurrence_date, $places, $customer_user_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true ) {
            return new WP_Error( 'reservations_disabled', 'Booking reservations are disabled.' );
        }
        $customer_user_id = absint( $customer_user_id );
        if ( ! $customer_user_id || ! get_userdata( $customer_user_id ) ) {
            return new WP_Error( 'customer_required', 'A valid customer account is required.' );
        }
        $quote = self::quote_session( $session_id, $places );
        if ( is_wp_error( $quote ) ) { return $quote; }
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date || $date < new DateTimeImmutable( 'today', wp_timezone() ) ) {
            return new WP_Error( 'invalid_occurrence', 'Choose a valid future session date.' );
        }
        $valid_occurrence = self::validate_occurrence( $session_id, $occurrence_date );
        if ( is_wp_error( $valid_occurrence ) ) { return $valid_occurrence; }
        // Never use client-provided price, organiser or activity IDs.
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $lock = 'bh3_' . md5( $wpdb->prefix . ':' . $quote['session_id'] . ':' . $occurrence_date );
        $acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
        if ( $acquired !== 1 ) {
            return new WP_Error( 'booking_busy', 'This session is busy. Please try again.' );
        }
        try {
            $availability = self::occurrence_availability( $quote['session_id'], $occurrence_date );
            if ( is_wp_error( $availability ) ) { return $availability; }
            if ( $availability['available'] < $quote['places'] ) {
                return new WP_Error( 'session_full', 'Not enough places remain.' );
            }
            $recheck_date = self::validate_occurrence( $session_id, $occurrence_date );
            if ( is_wp_error( $recheck_date ) ) { return $recheck_date; }
            // Fail closed if session pricing or capacity changed during the wait.
            $current = self::quote_session( $session_id, $places );
            if ( is_wp_error( $current ) || $current['gross_pence'] !== $quote['gross_pence'] ) {
                return new WP_Error( 'quote_changed', 'Session pricing changed. Please try again.' );
            }
            $reference = wp_generate_uuid4();
            $now = current_time( 'mysql', true );
            $expires = gmdate( 'Y-m-d H:i:s', time() + 35 * MINUTE_IN_SECONDS );
            $ok = $wpdb->insert( $table, array(
                'public_reference' => $reference,
                'session_id' => $quote['session_id'],
                'occurrence_date' => $occurrence_date,
                'activity_id' => $quote['activity_id'],
                'organiser_id' => $quote['organiser_id'],
                'customer_user_id' => $customer_user_id,
                'places' => $quote['places'],
                'currency' => 'gbp',
                'unit_price_pence' => $quote['unit_price_pence'],
                'gross_pence' => $quote['gross_pence'],
                'commission_pence' => $quote['commission_pence'],
                'leader_before_fees_pence' => $quote['leader_before_fees_pence'],
                'commission_rate_percent' => $quote['commission_rate_percent'],
                'payment_status' => 'pending',
                'reservation_expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ) );
            if ( $ok !== 1 ) {
                return new WP_Error( 'reservation_failed', 'Could not reserve places.' );
            }
            return array( 'booking_id' => (int) $wpdb->insert_id, 'reference' => $reference,
                'expires_at_utc' => $expires, 'status' => 'pending', 'checkout_enabled' => false );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
    /**
     * Internal Stripe Checkout test-session creator. No public route is registered.
     * Destination charges make the platform responsible for Stripe processing fees,
     * so refuse the default leader-pays policy until a compatible flow is built.
     */
    public static function create_test_checkout( $booking_id, $customer_user_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) || BH3_ENABLE_STRIPE_TEST_CHECKOUT !== true ) {
            return new WP_Error( 'checkout_disabled', 'Checkout is disabled.' );
        }
        $config = self::stripe_status();
        if ( $config['mode'] !== 'test' || ! $config['keys_configured'] ) {
            return new WP_Error( 'stripe_test_required', 'Stripe test credentials required.' );
        }
        if ( self::policy()['processing_fees'] !== 'platform' ) {
            return new WP_Error( 'fee_policy_unsupported', 'Destination charges charge processing fees to the platform. Choose platform fees or use a different payment flow.' );
        }
        $customer_user_id = absint( $customer_user_id );
        if ( ! $customer_user_id || $customer_user_id !== get_current_user_id() ) {
            return new WP_Error( 'customer_mismatch', 'Customer authentication required.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id=%d AND customer_user_id=%d LIMIT 1",
            absint( $booking_id ), $customer_user_id
        ), ARRAY_A );
        if ( ! $booking || $booking['payment_status'] !== 'pending'
            || ! empty( $booking['stripe_checkout_session_id'] )
            || empty( $booking['reservation_expires_at'] )
            || strtotime( $booking['reservation_expires_at'] . ' UTC' ) <= time() + 120 ) {
            return new WP_Error( 'invalid_reservation', 'Reservation unavailable or too close to expiry.' );
        }
        $valid = self::validate_occurrence( (int) $booking['session_id'], $booking['occurrence_date'] );
        if ( is_wp_error( $valid ) ) { return $valid; }
        // Payout account belongs to the organiser, never the customer.
        $organiser_users = get_users( array( 'meta_key' => 'bh3_organiser_id',
            'meta_value' => (string) (int) $booking['organiser_id'], 'fields' => 'ID', 'number' => 2 ) );
        if ( count( $organiser_users ) !== 1 ) {
            return new WP_Error( 'organiser_account_ambiguous', 'Organiser payout account must be uniquely linked.' );
        }
        $account = get_user_meta( (int) $organiser_users[0], 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            return new WP_Error( 'leader_not_connected', 'Leader Stripe test account is not connected.' );
        }
        $leader_status = self::leader_stripe_status( (int) $organiser_users[0] );
        if ( $leader_status['state'] !== 'ready_test' ) {
            return new WP_Error( 'leader_not_ready', 'Leader test account is not ready.' );
        }
        $gross = (int) $booking['gross_pence'];
        $commission = (int) $booking['commission_pence'];
        if ( $gross < 50 || $commission < 0 || $commission > $gross || $booking['currency'] !== 'gbp' ) {
            return new WP_Error( 'invalid_payment_amount', 'Invalid booking amount.' );
        }
        $expiry = strtotime( $booking['reservation_expires_at'] . ' UTC' );
        // Reserve a minimum 30-minute Checkout window; leave a safety margin.
        if ( $expiry < time() + 30 * MINUTE_IN_SECONDS ) {
            return new WP_Error( 'hold_too_short', 'Checkout requires a longer capacity hold; no payment session created.' );
        }
        return new WP_Error( 'checkout_not_ready', 'Checkout awaits webhook confirmation and idempotency implementation.' );
    }
    public static function stripe_status() {
        $mode = get_option( 'bh3_stripe_mode', 'test' );
        $mode = $mode === 'live' ? 'live' : 'test';
        $secret_constant = $mode === 'live' ? 'BH3_STRIPE_LIVE_SECRET_KEY' : 'BH3_STRIPE_TEST_SECRET_KEY';
        $publishable_constant = $mode === 'live' ? 'BH3_STRIPE_LIVE_PUBLISHABLE_KEY' : 'BH3_STRIPE_TEST_PUBLISHABLE_KEY';
        $secret = defined( $secret_constant ) ? constant( $secret_constant ) : '';
        $public = defined( $publishable_constant ) ? constant( $publishable_constant ) : '';
        $prefix = $mode === 'live' ? 'live' : 'test';
        $valid = is_string( $secret ) && strpos( $secret, 'sk_' . $prefix . '_' ) === 0
            && is_string( $public ) && strpos( $public, 'pk_' . $prefix . '_' ) === 0;
        return array( 'mode' => $mode, 'keys_configured' => $valid, 'connection_verified' => false );
    }
    public static function verify_stripe() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_verify_stripe' );
        $status = self::stripe_status();
        $constant = $status['mode'] === 'live' ? 'BH3_STRIPE_LIVE_SECRET_KEY' : 'BH3_STRIPE_TEST_SECRET_KEY';
        $result = 'missing';
        if ( $status['keys_configured'] && defined( $constant ) ) {
            $response = wp_remote_get( 'https://api.stripe.com/v1/account', array(
                'timeout' => 12,
                'redirection' => 0,
                'headers' => array( 'Authorization' => 'Bearer ' . constant( $constant ) ),
            ) );
            if ( is_wp_error( $response ) ) {
                $result = 'network_error';
            } elseif ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                $payload = json_decode( wp_remote_retrieve_body( $response ), true );
                $result = is_array( $payload ) && ! empty( $payload['id'] ) && strpos( $payload['id'], 'acct_' ) === 0
                    && ( ! isset( $payload['livemode'] ) || (bool) $payload['livemode'] === ( $status['mode'] === 'live' ) )
                    ? 'verified' : 'unexpected_response';
            } else {
                $result = 'rejected';
            }
        }
        // Persist only verification metadata, never Stripe keys or response payloads.
        update_option( 'bh3_stripe_verification', array(
            'mode' => $status['mode'], 'result' => $result, 'checked_at' => time(),
        ), false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-marketplace&stripe_check=' . rawurlencode( $result ) ) ); exit;
    }
    public static function stripe_onboard() {
        if ( ! is_user_logged_in() || ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) {
            wp_die( 'Linked leader account required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_stripe_onboard' );
        $status = self::stripe_status();
        if ( $status['mode'] !== 'test' || ! $status['keys_configured']
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_ONBOARDING' ) || BH3_ENABLE_STRIPE_TEST_ONBOARDING !== true ) {
            wp_die( 'Stripe test onboarding disabled.', '', array( 'response' => 403 ) );
        }
        $key = constant( 'BH3_STRIPE_TEST_SECRET_KEY' );
        $user_id = get_current_user_id();
        $account = get_user_meta( $user_id, 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            $created = wp_remote_post( 'https://api.stripe.com/v1/accounts', array(
                'timeout' => 20, 'redirection' => 0,
                'headers' => array( 'Authorization' => 'Bearer ' . $key ),
                'body' => array( 'type' => 'express', 'country' => 'GB', 'capabilities[transfers][requested]' => 'true', 'metadata[wp_user_id]' => (string) $user_id ),
            ) );
            $data = ! is_wp_error( $created ) && wp_remote_retrieve_response_code( $created ) === 200 ? json_decode( wp_remote_retrieve_body( $created ), true ) : null;
            if ( ! is_array( $data ) || empty( $data['id'] ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $data['id'] ) ) {
                wp_die( 'Stripe test account creation failed.', '', array( 'response' => 502 ) );
            }
            $account = $data['id'];
            update_user_meta( $user_id, 'bh3_stripe_test_account_id', $account );
        }
        $return = home_url( '/leader/account/' );
        $response = wp_remote_post( 'https://api.stripe.com/v1/account_links', array(
            'timeout' => 20, 'redirection' => 0,
            'headers' => array( 'Authorization' => 'Bearer ' . $key ),
            'body' => array(
                'account' => $account, 'type' => 'account_onboarding',
                'refresh_url' => add_query_arg( 'stripe_return', 'refresh', $return ),
                'return_url' => add_query_arg( 'stripe_return', 'complete', $return ),
            ),
        ) );
        $data = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
        if ( ! is_array( $data ) || empty( $data['url'] ) || strpos( $data['url'], 'https://connect.stripe.com/' ) !== 0 ) {
            wp_die( 'Stripe test onboarding link failed.', '', array( 'response' => 502 ) );
        }
        wp_redirect( esc_url_raw( $data['url'] ), 303 ); exit;
    }
    public static function leader_stripe_status( $user_id ) {
        $account = get_user_meta( absint( $user_id ), 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            return array( 'state' => 'not_connected', 'label' => 'Not connected' );
        }
        $config = self::stripe_status();
        if ( $config['mode'] !== 'test' || ! $config['keys_configured'] ) {
            return array( 'state' => 'unavailable', 'label' => 'Test connection unavailable' );
        }
        $key = constant( 'BH3_STRIPE_TEST_SECRET_KEY' );
        $response = wp_remote_get( 'https://api.stripe.com/v1/accounts/' . rawurlencode( $account ), array(
            'timeout' => 12, 'redirection' => 0,
            'headers' => array( 'Authorization' => 'Bearer ' . $key ),
        ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return array( 'state' => 'unavailable', 'label' => 'Unable to verify with Stripe' );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || ( $data['id'] ?? '' ) !== $account || ! empty( $data['livemode'] ) ) {
            return array( 'state' => 'unavailable', 'label' => 'Unexpected Stripe account response' );
        }
        if ( ! empty( $data['charges_enabled'] ) && ! empty( $data['payouts_enabled'] ) && ! empty( $data['details_submitted'] ) ) {
            return array( 'state' => 'ready_test', 'label' => 'Test account ready (no live payments)' );
        }
        if ( ! empty( $data['requirements']['disabled_reason'] ) ) {
            return array( 'state' => 'restricted', 'label' => 'Action required in Stripe' );
        }
        return array( 'state' => 'pending', 'label' => 'Onboarding incomplete or under review' );
    }
    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Marketplace Commission', 'Marketplace Commission', 'manage_options', 'bh3-marketplace', array( __CLASS__, 'admin_form' ) );
    }
    public static function admin_form() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><h1>Marketplace commission</h1><p>Default 3%. No live charges or payouts are enabled.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_save_commission' );
        echo '<input type="hidden" name="action" value="bh3_save_commission"><label>Commission (%) <input type="number" name="rate" required min="0" max="100" step="0.01" value="' . esc_attr( number_format( self::rate(), 2, '.', '' ) ) . '"></label>';
        $policy = self::policy();
        $choices = array(
            'processing_fees' => array( 'Processing fees paid by', array( 'leader' => 'Leader (recommended)', 'platform' => 'Bubba Hub' ) ),
            'payout_schedule' => array( 'Preferred payout schedule', array( 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'manual' => 'Manual review' ) ),
            'refund_policy' => array( 'Refund handling', array( 'manual_review' => 'Manual review before refund', 'leader_approval' => 'Leader approval required' ) ),
        );
        foreach ( $choices as $field => $definition ) {
            echo '<p><label for="bh3-' . esc_attr( $field ) . '">' . esc_html( $definition[0] ) . '</label><br><select id="bh3-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
            foreach ( $definition[1] as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy[$field], $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></p>';
        }
        $stripe = self::stripe_status();
        echo '<h2>Stripe Connect</h2><p><label for="bh3-stripe-mode">Environment</label><br><select id="bh3-stripe-mode" name="stripe_mode">';
        foreach ( array( 'test' => 'Test mode', 'live' => 'Live mode (checkout still disabled)' ) as $mode => $label ) {
            echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $stripe['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p><p><strong>Credentials:</strong> ' . esc_html( $stripe['keys_configured'] ? 'Configured in server environment (not yet verified with Stripe)' : 'Not configured' ) . '</p>';
        echo '<p>Set BH3_STRIPE_TEST_SECRET_KEY and BH3_STRIPE_TEST_PUBLISHABLE_KEY securely in wp-config.php or server environment. Live equivalents: BH3_STRIPE_LIVE_SECRET_KEY and BH3_STRIPE_LIVE_PUBLISHABLE_KEY. Never paste secret keys into a page or form.</p>';
        echo '<p><strong>Connection:</strong> Not verified. Stripe Checkout and automatic payouts remain disabled.</p><p><strong>PayPal / Invoice:</strong> Manual settlement workflow not configured.</p>';
        submit_button( 'Save payment settings' ); echo '</form>';
        $last = get_option( 'bh3_stripe_verification', array() );
        if ( is_array( $last ) && ! empty( $last['result'] ) ) {
            echo '<p><strong>Last verification:</strong> ' . esc_html( $last['result'] ) . ' (' . esc_html( $last['mode'] ?? '' ) . ' mode). Reverify after changing credentials.</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_verify_stripe' );
        echo '<input type="hidden" name="action" value="bh3_verify_stripe">';
        submit_button( 'Verify Stripe API connection', 'secondary' );
        echo '</form><p>Fluent Forms collects booking details only. Direct Stripe Connect checkout, payment verification, refunds and reconciliation must pass staging QA before activation.</p></div>';
    }
    public static function save_commission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_save_commission' );
        $raw = isset( $_POST['rate'] ) && is_scalar( $_POST['rate'] ) ? trim( (string) wp_unslash( $_POST['rate'] ) ) : '';
        if ( ! preg_match( '/^(?:100(?:\\.0{1,2})?|[0-9]{1,2}(?:\\.[0-9]{1,2})?)$/', $raw ) ) { wp_die( 'Invalid percentage', '', array( 'response' => 400 ) ); }
        $allowed = array(
            'processing_fees' => array( 'leader', 'platform' ),
            'payout_schedule' => array( 'weekly', 'monthly', 'manual' ),
            'refund_policy' => array( 'manual_review', 'leader_approval' ),
        );
        $policy = self::policy();
        foreach ( $allowed as $key => $values ) {
            $input = isset( $_POST[$key] ) && is_scalar( $_POST[$key] ) ? sanitize_key( wp_unslash( $_POST[$key] ) ) : '';
            if ( ! in_array( $input, $values, true ) ) { wp_die( 'Invalid payment policy', '', array( 'response' => 400 ) ); }
            $policy[$key] = $input;
        }
        update_option( 'bh3_commission_percent', number_format( (float) $raw, 2, '.', '' ), false );
        update_option( 'bh3_marketplace_policy', $policy, false );
        $mode = isset( $_POST['stripe_mode'] ) && is_scalar( $_POST['stripe_mode'] ) ? sanitize_key( wp_unslash( $_POST['stripe_mode'] ) ) : '';
        if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) { wp_die( 'Invalid Stripe mode', '', array( 'response' => 400 ) ); }
        update_option( 'bh3_stripe_mode', $mode, false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-marketplace' ) ); exit;
    }
    public static function leader_form() {
        if ( ! is_user_logged_in() ) { return '<p>Sign in to set payout preferences.</p>'; }
        if ( ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) { return '<p>A linked leader account is required.</p>'; }
        $current = get_user_meta( get_current_user_id(), 'bh3_payout_method', true );
        ob_start();
        echo '<section class="bh3-payout-settings"><h2>Payout settings</h2><p>Select your preferred method. Payments and payouts are not active yet; this does not connect a payment account.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_save_payout_method' );
        echo '<input type="hidden" name="action" value="bh3_save_payout_method"><label for="bh3-method">Payout preference</label><select id="bh3-method" name="method">';
        foreach ( array( 'stripe' => 'Stripe Connect (automated setup pending)', 'paypal' => 'PayPal (manual settlement pending)', 'invoice' => 'Invoice (manual settlement pending)' ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><button type="submit">Save preference</button></form>';
        if ( self::stripe_status()['mode'] === 'test' && self::stripe_status()['keys_configured'] && defined( 'BH3_ENABLE_STRIPE_TEST_ONBOARDING' ) && BH3_ENABLE_STRIPE_TEST_ONBOARDING === true ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'bh3_stripe_onboard' );
            echo '<input type="hidden" name="action" value="bh3_stripe_onboard"><button type="submit">Set up Stripe test payouts</button></form>';
            echo '<p>Test onboarding only. Payouts remain disabled.</p>';
        }
        if ( $current === 'stripe' ) {
            $stripe_account = self::leader_stripe_status( get_current_user_id() );
            echo '<p role="status"><strong>Stripe test payout status:</strong> ' . esc_html( $stripe_account['label'] ) . '</p>';
        }
        echo '</section>';
        return ob_get_clean();
    }
    public static function save_method() {
        if ( ! is_user_logged_in() || ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_save_payout_method' );
        $method = isset( $_POST['method'] ) && is_scalar( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
        if ( ! in_array( $method, array( 'stripe', 'paypal', 'invoice' ), true ) ) { wp_die( 'Invalid payout method', '', array( 'response' => 400 ) ); }
        update_user_meta( get_current_user_id(), 'bh3_payout_method', $method );
        wp_safe_redirect( wp_get_referer() ?: home_url( '/leader/account/' ) ); exit;
    }
}
