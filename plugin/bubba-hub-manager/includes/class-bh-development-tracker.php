<?php
/**
 * Admin-only development roadmap. Statuses are manually maintained, not automated test results.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Legacy tracker remains available as a read-only data source for the unified Developments screen.
// Do not register a second admin submenu; preserve existing option values.

function bh_development_tracker_items() {
    return array(
        'family-profile' => array( 'Family Hub', 'Family profiles & children', 'In progress' ),
        'family-planner' => array( 'Family Hub', 'Planner, favourites & visited', 'In progress' ),
        'family-reminders' => array( 'Family Hub', 'School & pregnancy reminders', 'Planned' ),
        'leader-dashboard' => array( 'Leader Portal', 'Leader dashboard & storefront', 'In progress' ),
        'leader-venues' => array( 'Leader Portal', 'Venue and timetable editor', 'In progress' ),
        'leader-payouts' => array( 'Leader Portal', 'Payout settings', 'Needs testing' ),
        'booking-enquiries' => array( 'Bookings', 'Enquiry and joining form', 'Needs testing' ),
        'booking-consent' => array( 'Bookings', 'Consent form and child selection', 'Needs testing' ),
        'booking-management' => array( 'Bookings', 'Enrolment, reminders & calendar', 'In progress' ),
        'booking-payments' => array( 'Bookings', 'Payments and refunds', 'Planned' ),
        'directory-filters' => array( 'Directory', 'Filters, search and AJAX', 'In progress' ),
        'directory-views' => array( 'Directory', 'List, calendar and planner views', 'In progress' ),
        'activity-pages' => array( 'Activities', 'Activity pages and timetable', 'Needs testing' ),
        'activity-wizard' => array( 'Activities', 'Activity creation wizard', 'Needs testing' ),
        'support-questions' => array( 'Support', 'Ask a Question and moderation', 'Needs testing' ),
        'support-resources' => array( 'Support', 'Resources and guidance', 'In progress' ),
        'membership' => array( 'Membership', 'Family/Leader Free and Pro', 'Needs testing' ),
        'notifications' => array( 'Communications', 'Email templates, logs and reminders', 'Planned' ),
        'reporting' => array( 'Reporting', 'Admin reporting and metrics', 'Planned' ),
    );
}

function bh_render_development_tracker() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'bubba-hub-manager' ) ); }
    $items = bh_development_tracker_items();
    $statuses = array( 'Planned', 'In progress', 'Needs testing', 'Ready to deploy', 'Deployed', 'Blocked', 'Complete' );
    $saved = get_option( 'bh_development_tracker_statuses', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }
    if ( isset( $_POST['bh_tracker_save'] ) ) {
        check_admin_referer( 'bh_tracker_save' );
        $submitted = isset( $_POST['bh_tracker_status'] ) && is_array( $_POST['bh_tracker_status'] ) ? wp_unslash( $_POST['bh_tracker_status'] ) : array();
        foreach ( $items as $key => $item ) {
            if ( isset( $submitted[$key] ) && is_string( $submitted[$key] ) && in_array( $submitted[$key], $statuses, true ) ) {
                $saved[$key] = $submitted[$key];
            }
        }
        update_option( 'bh_development_tracker_statuses', $saved, false );
        echo '<div class="notice notice-success"><p>Development statuses saved.</p></div>';
    }
    $groups = array();
    foreach ( $items as $key => $item ) { $groups[$item[0]][$key] = $item; }
    echo '<div class="wrap"><h1>Bubba Hub 3.0 — Development Tracker</h1>';
    echo '<p>Admin-only roadmap. Initial statuses are planning estimates, not verified deployment or testing results. Update each element as work progresses.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'bh_tracker_save' );
    foreach ( $groups as $group => $rows ) {
        echo '<h2>' . esc_html( $group ) . '</h2><table class="widefat striped"><thead><tr><th style="width:65%">Element</th><th>Status</th></tr></thead><tbody>';
        foreach ( $rows as $key => $item ) {
            $current = isset( $saved[$key] ) && in_array( $saved[$key], $statuses, true ) ? $saved[$key] : $item[2];
            echo '<tr><td><strong>' . esc_html( $item[1] ) . '</strong></td><td><label class="screen-reader-text" for="bh-status-' . esc_attr( $key ) . '">Status for ' . esc_html( $item[1] ) . '</label><select id="bh-status-' . esc_attr( $key ) . '" name="bh_tracker_status[' . esc_attr( $key ) . ']">';
            foreach ( $statuses as $status ) { echo '<option value="' . esc_attr( $status ) . '"' . selected( $current, $status, false ) . '>' . esc_html( $status ) . '</option>'; }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
    }
    submit_button( 'Save statuses', 'primary', 'bh_tracker_save' );
    echo '</form></div>';
}
