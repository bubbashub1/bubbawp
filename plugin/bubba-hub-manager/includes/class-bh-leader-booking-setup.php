<?php
/** Leader booking setup for existing sessions, staging only. */
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Booking_Setup_3 {
    public static function register() {
        add_shortcode('bh_leader_booking_setup', array(__CLASS__, 'render'));
    }
    public static function render() {
        if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'staging.bubbahub.co.uk') { return ''; }
        if (!is_user_logged_in()) { return '<p>Please sign in to manage your sessions.</p>'; }
        $owner = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        $activity_id = absint($_GET['activity_id'] ?? 0);
        if (!$owner || !$activity_id) { return '<p>Select an activity to configure its bookings.</p>'; }
        global $wpdb;
        $activity = $wpdb->get_row($wpdb->prepare("SELECT id,title FROM {$wpdb->prefix}activities WHERE id=%d AND organiser_id=%d", $activity_id, $owner), ARRAY_A);
        if (!$activity) { return '<p>Activity unavailable.</p>'; }
        $sessions = $wpdb->get_results($wpdb->prepare("SELECT id,day_of_week,start_time,end_time FROM {$wpdb->prefix}sessions WHERE activity_id=%d ORDER BY day_of_week,start_time LIMIT 200", $activity_id), ARRAY_A);
        ob_start();
        echo '<section class="bh-booking-setup"><h1>Booking setup: ' . esc_html($activity['title']) . '</h1><p>Existing timetable sessions are shown below. Booking creation is not yet enabled.</p>';
        foreach ($sessions as $session) {
            echo '<article><strong>' . esc_html($session['day_of_week']) . ' · ' . esc_html(substr($session['start_time'],0,5)) . '–' . esc_html(substr($session['end_time'],0,5)) . '</strong></article>';
        }
        if (!$sessions) { echo '<p>Add timetable sessions before setting up bookings.</p>'; }
        echo '</section>';
        return ob_get_clean();
    }
}
