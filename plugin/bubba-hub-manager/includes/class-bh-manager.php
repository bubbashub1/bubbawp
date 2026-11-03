<?php
/**
 * Safe bootstrap: no migrations, routes, external calls or data writes.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class BH_Manager_3 {
    private static $booted = false;

    public static function boot() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
    }

    public static function register_admin_page() {
        add_management_page(
            __( 'Bubba Hub 3.0 Status', 'bubba-hub-manager' ),
            __( 'Bubba Hub 3.0', 'bubba-hub-manager' ),
            'manage_options',
            'bubba-hub-3-status',
            array( __CLASS__, 'render_status' )
        );
    }

    public static function render_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'bubba-hub-manager' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Bubba Hub Manager 3.0', 'bubba-hub-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Foundation installed. All business modules remain disabled pending like-for-like audit and staging tests.', 'bubba-hub-manager' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Version:', 'bubba-hub-manager' ) . '</strong> ' . esc_html( BUBBA_HUB_MANAGER_VERSION ) . '</p>';
        echo '<p>' . esc_html__( 'No database tables, user roles, rewrite rules, REST endpoints or scheduled tasks are created by this version.', 'bubba-hub-manager' ) . '</p></div>';
    }
}
