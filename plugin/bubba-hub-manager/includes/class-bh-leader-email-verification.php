<?php
/**
 * Email verification for leader publishing. wp_mail is delivered by the site's
 * configured mail transport (FluentSMTP when active).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Leader_Email_Verification_3 {
    const VERIFIED = 'bh3_verified_email_address';
    const TOKEN = 'bh3_email_verification_token_hash';
    const EXPIRES = 'bh3_email_verification_expires';
    public static function register() {
        add_action( 'admin_post_bh3_send_email_verification', array( __CLASS__, 'send' ) );
        add_action( 'admin_post_bh3_confirm_email_verification', array( __CLASS__, 'confirm' ) );
        add_action( 'admin_post_nopriv_bh3_confirm_email_verification', array( __CLASS__, 'confirm' ) );
    }
    public static function verified( $user_id ) {
        $user = get_userdata( (int) $user_id );
        // Fail closed until Simple Membership's activation-completed signal is integrated.
        // An active membership or the earlier custom token must not grant publishing.
        return false;
    }
    public static function send() {
        if ( ! is_user_logged_in() ) wp_die( 'Sign in to verify your email.', '', array( 'response' => 403 ) );
        check_admin_referer( 'bh3_send_email_verification' );
        $user = wp_get_current_user();
        if ( ! is_email( $user->user_email ) ) wp_die( 'A valid account email is required.', '', array( 'response' => 400 ) );
        if ( self::verified( $user->ID ) ) { wp_safe_redirect( home_url( '/leader/new-activity/' ) ); exit; }
        $last = (int) get_user_meta( $user->ID, 'bh3_email_verification_last_sent', true );
        if ( $last && time() - $last < 300 ) wp_die( 'Please wait five minutes before requesting another verification email.', '', array( 'response' => 429 ) );
        $token = bin2hex( random_bytes( 32 ) );
        $url = add_query_arg( array( 'action' => 'bh3_confirm_email_verification', 'uid' => $user->ID, 'token' => $token ), admin_url( 'admin-post.php' ) );
        update_user_meta( $user->ID, self::TOKEN, hash( 'sha256', $token ) );
        update_user_meta( $user->ID, self::EXPIRES, time() + DAY_IN_SECONDS );
        update_user_meta( $user->ID, 'bh3_email_verification_last_sent', time() );
        $sent = wp_mail( $user->user_email, 'Verify your Bubba Hub email', "Please confirm your email address using this link (valid for 24 hours):\n\n" . $url . "\n\nIf you did not request this, ignore this email." );
        if ( ! $sent ) wp_die( 'The verification email could not be sent. Please try again later.', '', array( 'response' => 503 ) );
        wp_safe_redirect( add_query_arg( 'verification', 'sent', home_url( '/leader/new-activity/' ) ) ); exit;
    }
    public static function confirm() {
        $id = absint( $_GET['uid'] ?? 0 );
        $token = isset( $_GET['token'] ) && is_string( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $hash = (string) get_user_meta( $id, self::TOKEN, true );
        $expiry = (int) get_user_meta( $id, self::EXPIRES, true );
        $user = get_userdata( $id );
        if ( ! $user || ! preg_match( '/^[a-f0-9]{64}$/', $token ) || ! $hash || $expiry < time() || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
            wp_die( 'This verification link is invalid or has expired. Request a new link from your leader account.', '', array( 'response' => 400 ) );
        }
        update_user_meta( $id, self::VERIFIED, strtolower( trim( $user->user_email ) ) );
        delete_user_meta( $id, self::TOKEN );
        delete_user_meta( $id, self::EXPIRES );
        wp_safe_redirect( add_query_arg( 'verification', 'complete', home_url( '/leader/new-activity/' ) ) ); exit;
    }
}
