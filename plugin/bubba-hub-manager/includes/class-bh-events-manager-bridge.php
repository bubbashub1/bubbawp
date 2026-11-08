<?php
/**
 * Read-only Events Manager Free integration diagnostics.
 * No booking, session, payment or child data is read or changed.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Events_Manager_Bridge {
    public static function inspect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'bh_forbidden', 'Administrator access required.' );
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = get_plugins();
        $candidates = array();
        foreach ( $installed as $basename => $plugin ) {
            if ( strpos( $basename, 'events-manager/' ) === 0 ) {
                $candidates[] = array(
                    'basename' => $basename,
                    'version' => isset( $plugin['Version'] ) ? (string) $plugin['Version'] : '',
                    'active' => is_plugin_active( $basename ),
                );
            }
        }
        return array(
            'events_manager' => $candidates,
            'em_events_class_loaded' => class_exists( 'EM_Events', false ),
            'em_bookings_class_loaded' => class_exists( 'EM_Bookings', false ),
            'ninja_forms_loaded' => function_exists( 'Ninja_Forms' ),
            'native_child_profiles_preserved' => true,
            'booking_migration_ready' => false,
            'writes_performed' => false,
        );
    }
}
