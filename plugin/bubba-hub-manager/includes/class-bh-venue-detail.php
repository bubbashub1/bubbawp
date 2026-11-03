<?php
/**
 * Read-only venue detail inspector with linked activities.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Venue_Detail_3 {
    public static function register() {
        add_management_page( 'Bubba Hub Venue Detail', 'BH Venue Detail', 'manage_options', 'bh3-venue-detail', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $id = isset( $_GET['venue_id'] ) ? absint( wp_unslash( $_GET['venue_id'] ) ) : 0;
        echo '<div class="wrap"><h1>Venue details</h1><p>Read-only legacy venue inspection.</p>';
        if ( ! $id ) { echo '<p>Select a venue from BH Venues.</p></div>'; return; }
        $venue = $wpdb->get_row( $wpdb->prepare( 'SELECT id, activity_id, venue_name, address, town, region, postcode, latitude, longitude FROM bh_venues WHERE id = %d LIMIT 1', $id ), ARRAY_A );
        if ( ! $venue || $wpdb->last_error ) { echo '<p>Venue not available.</p></div>'; return; }
        echo '<h2>' . esc_html( $venue['venue_name'] ) . '</h2><table class="widefat striped"><tbody>';
        foreach ( array( 'address' => 'Address', 'town' => 'Town', 'region' => 'Region', 'postcode' => 'Postcode', 'latitude' => 'Latitude', 'longitude' => 'Longitude' ) as $key => $label ) {
            echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) ( $venue[ $key ] ?? '' ) ) . '</td></tr>';
        }
        echo '</tbody></table><h2>Linked activity</h2>';
        $activity = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, status FROM bh_activities WHERE id = %d', $venue['activity_id'] ), ARRAY_A );
        if ( $activity ) {
            echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activity-detail', 'activity_id' => $activity['id'] ), admin_url( 'tools.php' ) ) ) . '">' . esc_html( $activity['title'] ) . '</a> (' . esc_html( $activity['status'] ) . ')</p>';
        } else {
            echo '<p>No matching activity found.</p>';
        }
        echo '<p><a class="button" href="' . esc_url( admin_url( 'tools.php?page=bh3-venues' ) ) . '">Back to Venues</a></p></div>';
    }
}
