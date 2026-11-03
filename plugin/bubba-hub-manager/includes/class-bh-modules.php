<?php
/** Safe admin-only module planning dashboard; no live-data access or writes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Modules_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', __( 'Modules', 'bubba-hub-manager' ), __( 'Modules', 'bubba-hub-manager' ), 'manage_options', 'bh3-modules', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ) ); }
        $modules = array(
            array( 'Directory', 'Layout preview built; real search and data connection pending', 'Preview only' ),
            array( 'Activities', 'Activity cards, detail pages, timetables and editing', 'Planned' ),
            array( 'Organisers & businesses', 'Profiles and linked listings', 'Planned' ),
            array( 'Venues', 'Venue search, map and linked sessions', 'Planned' ),
            array( 'Events', 'Event listings, ticketing and registrations', 'Planned' ),
            array( 'My Hub', 'Saved activities and family planner', 'Planned' ),
            array( 'Leader dashboard', 'Classes, venues, schedules and enrolments', 'Planned' ),
            array( 'Bookings', 'Reservations, confirmations and reminders', 'Planned' ),
            array( 'Administration', 'Import, approvals, claims and reporting', 'Planned' ),
        );
        echo '<div class="wrap"><h1>' . esc_html__( 'Bubba Hub 3.0 Modules', 'bubba-hub-manager' ) . '</h1>';
        echo '<p>Build each section, validate it on staging, then connect existing content. This screen is an inventory, not an activation panel.</p>';
        echo '<table class="widefat striped"><thead><tr><th scope="col">Section</th><th scope="col">Scope</th><th scope="col">Readiness</th></tr></thead><tbody>';
        foreach ( $modules as $module ) {
            echo '<tr><th scope="row">' . esc_html( $module[0] ) . '</th><td>' . esc_html( $module[1] ) . '</td><td>' . esc_html( $module[2] ) . '</td></tr>';
        }
        echo '</tbody></table><p><strong>Safety:</strong> No production modules are enabled by this screen. Legacy content remains authoritative.</p></div>';
    }
}
