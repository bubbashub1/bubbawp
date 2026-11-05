<?php
/** Staging-only marketplace preferences. This module never charges or transfers money. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Marketplace_Settings_3 {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_save_commission', array( __CLASS__, 'save_commission' ) );
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
        echo '<p><strong>Stripe Connect:</strong> Not connected — checkout and automatic payouts disabled.</p><p><strong>PayPal / Invoice:</strong> Manual settlement workflow not configured.</p>';
        submit_button( 'Save commission' ); echo '</form><p>Fluent Forms collects booking details only. Direct Stripe Connect checkout, payment verification, refunds and reconciliation must pass staging QA before activation.</p></div>';
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
        echo '</select><button type="submit">Save preference</button></form></section>';
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
