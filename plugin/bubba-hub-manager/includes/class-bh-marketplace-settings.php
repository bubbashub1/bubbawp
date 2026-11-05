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
