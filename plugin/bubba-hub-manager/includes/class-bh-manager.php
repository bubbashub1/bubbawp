<?php
/**
 * Safe bootstrap: no migrations, routes, external calls or data writes.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class BH_Manager_3 {
    private static $booted = false;

    /** Explicitly disabled until isolated staging compatibility QA. */
    public const FEATURE_DIRECTORY = false;
    public const FEATURE_LEADER = false;
    public const FEATURE_FAMILY = false;
    public const FEATURE_BOOKINGS = false;

    public static function boot() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-audit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-preview.php';
        BH_Directory_Preview_3::register();
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
        add_action( 'admin_menu', array( 'BH_Settings_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Directory_Audit_3', 'register' ) );
        add_action( 'admin_post_bh3_save_settings', array( 'BH_Settings_3', 'save' ) );
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
        echo '<h2>' . esc_html__( 'Feature readiness', 'bubba-hub-manager' ) . '</h2>';
        echo '<ul>';
        foreach ( array( 'Directory' => self::FEATURE_DIRECTORY, 'Leader portal' => self::FEATURE_LEADER, 'My Hub' => self::FEATURE_FAMILY, 'Bookings' => self::FEATURE_BOOKINGS ) as $label => $enabled ) {
            echo '<li>' . esc_html( $label ) . ': ' . esc_html( $enabled ? __( 'Enabled', 'bubba-hub-manager' ) : __( 'Disabled — awaiting parity tests', 'bubba-hub-manager' ) ) . '</li>';
        }
        echo '</ul>';
        echo '<p>' . esc_html__( 'No database tables, user roles, rewrite rules, REST endpoints or scheduled tasks are created by this version.', 'bubba-hub-manager' ) . '</p></div>';
    }
}
