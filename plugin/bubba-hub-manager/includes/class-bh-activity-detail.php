<?php
/**
 * Admin-only legacy activity detail inspection.
 * Read-only: never invokes the old standalone admin API.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Detail_3 {
    public static function register() {
        add_management_page( 'Bubba Hub Activity Detail', 'BH Activity Detail', 'manage_options', 'bh3-activity-detail', array( __CLASS__, 'render' ) );
    }
    private static function has_table( $db, $table ) {
        return $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) === $table;
    }
    private static function table( $heading, $rows, $columns ) {
        echo '<h2>' . esc_html( $heading ) . '</h2><table class="widefat striped"><thead><tr>';
        foreach ( $columns as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            foreach ( array_keys( $columns ) as $key ) { echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>'; }
            echo '</tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="' . esc_attr( (string) count( $columns ) ) . '">No records.</td></tr>'; }
        echo '</tbody></table>';
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;
        $id = isset( $_GET['activity_id'] ) ? absint( wp_unslash( $_GET['activity_id'] ) ) : 0;
        echo '<div class="wrap"><h1>Bubba Hub Activity Detail</h1><p>Read-only database inspection. No editing or publishing actions are enabled.</p>';
        if ( ! $id || ! self::has_table( $wpdb, 'bh_activities' ) ) {
            echo '<p>Choose an activity from the Activities list, or verify that the legacy table exists.</p></div>';
            return;
        }
        $activity = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, slug, description, category, age_range, price_from, status, organiser_id FROM bh_activities WHERE id = %d LIMIT 1', $id ), ARRAY_A );
        if ( $wpdb->last_error || ! $activity ) { echo '<p>Activity unavailable.</p></div>'; return; }
        if ( isset( $_GET['bh3_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['bh3_saved'] ) ) ) { echo '<div class="notice notice-success"><p>Activity details saved.</p></div>'; }
        echo '<h2>' . esc_html( $activity['title'] ) . '</h2>';
        self::table( 'Listing fields', array( $activity ), array( 'id' => 'ID', 'slug' => 'Slug', 'category' => 'Category', 'age_range' => 'Age range', 'price_from' => 'Price from', 'status' => 'Status', 'organiser_id' => 'Organiser ID' ) );
        echo '<h2>Description</h2><div style="max-width:850px">' . wp_kses_post( wpautop( $activity['description'] ?? '' ) ) . '</div>';
        if ( self::has_table( $wpdb, 'bh_venues' ) ) {
            $venues = $wpdb->get_results( $wpdb->prepare( 'SELECT id, venue_name, town, region, postcode FROM bh_venues WHERE activity_id = %d ORDER BY id', $id ), ARRAY_A );
            self::table( 'Linked venues', $venues ?: array(), array( 'id' => 'ID', 'venue_name' => 'Venue', 'town' => 'Town', 'region' => 'Region', 'postcode' => 'Postcode' ) );
        }
        BH_Activity_Editor_3::form( $activity );
        echo '<p><a class="button" href="' . esc_url( admin_url( 'tools.php?page=bh3-activities' ) ) . '">Back to Activities</a></p></div>';
    }
}
