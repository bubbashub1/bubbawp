<?php
/** Staging-only, read-only Events Manager compatibility screen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Events_Manager_Diagnostics {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }
    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Booking Integration', 'Booking Integration', 'manage_options', 'bh-booking-integration', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Not permitted.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) ); }
        $result = BH_Events_Manager_Bridge::inspect();
        echo '<div class="wrap"><h1>Booking Integration — staging diagnostics</h1>';
        echo '<p>Read-only checks. This screen does not enable bookings, synchronise events or modify personal information.</p>';
        if ( is_wp_error( $result ) ) {
            echo '<p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            return;
        }
        echo '<table class="widefat striped"><tbody>';
        foreach ( $result as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = empty( $value ) ? 'Not detected' : wp_json_encode( $value );
            } elseif ( is_bool( $value ) ) {
                $value = $value ? 'Yes' : 'No';
            }
            echo '<tr><th scope="row">' . esc_html( str_replace( '_', ' ', ucfirst( $key ) ) ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        echo '</tbody></table><p><strong>Booking migration remains disabled.</strong> Existing checkout, forms and child records are unchanged.</p></div>';
    }
}
