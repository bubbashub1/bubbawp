<?php
/**
 * Bridge Simple Membership's completed email activation to WordPress users.
 * FluentSMTP handles delivery of Simple Membership's activation emails.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Leader_Email_Verification_3 {
    const VERIFIED = 'bh3_swpm_verified_email';
    const MEMBER_ID = 'bh3_swpm_verified_member_id';

    public static function register() {
        // Simple Membership invokes this filter only after consuming a valid
        // one-time activation token in handle_email_activation().
        add_filter( 'swpm_activation_feature_override_account_status', array( __CLASS__, 'activation_completed' ), 20, 1 );
    }

    public static function activation_completed( $status ) {
        if ( ! class_exists( 'SwpmMemberUtils' ) ) { return $status; }
        $member_id = isset( $_GET['swpm_member_id'] ) ? absint( $_GET['swpm_member_id'] ) : 0;
        if ( ! $member_id ) { return $status; }
        $member = SwpmMemberUtils::get_user_by_id( $member_id );
        if ( ! $member || $member->account_state !== 'activation_required' || ! is_email( $member->email ) || empty( $member->user_name ) ) { return $status; }
        $wp_user = get_user_by( 'login', $member->user_name );
        if ( ! $wp_user || strtolower( $wp_user->user_email ) !== strtolower( $member->email ) ) { return $status; }
        update_user_meta( $wp_user->ID, self::VERIFIED, strtolower( $member->email ) );
        update_user_meta( $wp_user->ID, self::MEMBER_ID, $member_id );
        return $status;
    }

    public static function verified( $user_id ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user || ! is_email( $user->user_email ) || ! class_exists( 'SwpmMemberUtils' ) ) { return false; }
        $member_id = absint( get_user_meta( $user_id, self::MEMBER_ID, true ) );
        $verified_email = strtolower( trim( (string) get_user_meta( $user_id, self::VERIFIED, true ) ) );
        if ( ! $member_id || ! $verified_email || $verified_email !== strtolower( trim( $user->user_email ) ) ) { return false; }
        $member = SwpmMemberUtils::get_user_by_id( $member_id );
        return $member && $member->account_state === 'active'
            && $member->user_name === $user->user_login
            && strtolower( (string) $member->email ) === $verified_email;
    }
}
