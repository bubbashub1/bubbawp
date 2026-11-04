<?php
/** Staging-only wp-admin access boundary for non-team accounts. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Portal_Access_3 {
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'guard_admin' ), 1 );
        add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
        add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
    }
    private static function staging() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function team_member( $user = null ) {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if ( ! $user || ! $user->exists() ) { return false; }
        // Explicit administrator / team capabilities; never infer team membership from the account name.
        return user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' );
    }
    private static function destination( $user ) {
        if ( $user instanceof WP_User && absint( get_user_meta( $user->ID, 'bh3_organiser_id', true ) ) ) {
            return home_url( '/leader/' );
        }
        return home_url( '/family/' );
    }
    public static function guard_admin() {
        if ( ! self::staging() || ! is_user_logged_in() || self::team_member() ) { return; }
        // admin-ajax.php is used by public forms, Fluent plugins and other front-end integrations.
        if ( wp_doing_ajax() || wp_doing_cron() ) { return; }
        // Admin-post actions must still reach their own nonce and ownership checks.
        global $pagenow;
        if ( $pagenow === 'admin-post.php' ) { return; }
        wp_safe_redirect( self::destination( wp_get_current_user() ) );
        exit;
    }
    public static function admin_bar( $show ) {
        return self::staging() && is_user_logged_in() && ! self::team_member() ? false : $show;
    }
    public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! self::staging() || ! ( $user instanceof WP_User ) || self::team_member( $user ) ) { return $redirect_to; }
        // Honour safe explicit front-end destinations, but never send non-team accounts to wp-admin.
        $requested = wp_validate_redirect( $requested_redirect_to, '' );
        if ( $requested && strpos( $requested, admin_url() ) !== 0 ) { return $requested; }
        return self::destination( $user );
    }
}
