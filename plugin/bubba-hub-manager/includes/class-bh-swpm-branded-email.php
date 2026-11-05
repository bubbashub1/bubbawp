<?php
/**
 * Site-branded Simple Membership activation email.
 * Enable "Send Email as HTML" in Simple Membership settings.
 * FluentSMTP delivers the resulting WordPress email.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Swpm_Branded_Email_3 {
    public static function register() {
        add_filter( 'swpm_email_registration_complete_body', array( __CLASS__, 'wrap_activation' ), 30, 1 );
    }

    public static function wrap_activation( $body ) {
        if ( ! is_string( $body ) || trim( $body ) === '' || ! class_exists( 'SwpmSettings' ) ) { return $body; }
        $settings = SwpmSettings::get_instance();
        if ( ! $settings->get_value( 'email-enable-html' ) ) { return $body; }
        // Do not brand unrelated registration or payment emails through this hook.
        // The token URL is already substituted by Simple Membership at this stage.
        if ( strpos( $body, 'swpm_email_activation=1' ) === false && strpos( $body, 'swpm_email_activation=1' ) === false ) { return $body; }

        $logo = '';
        $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) { $logo = wp_get_attachment_image_url( $custom_logo_id, 'medium' ); }
        if ( ! $logo && has_site_icon() ) { $logo = get_site_icon_url( 192 ); }
        $site = get_bloginfo( 'name' ) ?: 'Bubba Hub';
        $home = home_url( '/' );
        $logo_html = $logo ? '<img src="' . esc_url( $logo ) . '" width="160" alt="' . esc_attr( $site ) . '" style="max-width:160px;height:auto;display:block;margin:0 auto 8px">' : '';
        // Simple Membership may already provide HTML content; preserve its activation URL.
        $message = wp_kses_post( $body );
        if ( strpos( $message, '<' ) === false ) { $message = nl2br( esc_html( $body ) ); }
        return '<div style="margin:0;padding:28px 12px;background:#f3f8f3;font-family:Arial,sans-serif;color:#263b32">'
            . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:14px;overflow:hidden">'
            . '<div style="background:#dceee4;padding:26px;text-align:center">' . $logo_html
            . '<strong style="color:#245d48;font-size:18px">' . esc_html( $site ) . '</strong></div>'
            . '<div style="padding:30px 26px;font-size:15px;line-height:1.7"><h2 style="color:#245d48;margin-top:0">Confirm your email address</h2>'
            . $message . '</div><div style="background:#f5f7f4;padding:18px 26px;text-align:center;font-size:12px;color:#456257">'
            . '<a href="' . esc_url( $home ) . '" style="color:#245d48">' . esc_html( $home ) . '</a></div></div></div>';
    }
}
