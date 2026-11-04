<?php
/** Leader booking setup for existing sessions, staging only. */
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Booking_Setup_3 {
    public static function register() {
        add_shortcode('bh_leader_booking_setup', array(__CLASS__, 'render'));
        add_action('admin_post_bh3_booking_preferences_save', array(__CLASS__, 'save'));
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
            $sid = absint($session['id']);
            $prefs = get_option('bh3_booking_session_' . $sid, array());
            echo '<article style="padding:20px;margin:14px 0;border:1px solid #dbe5df;border-radius:14px;background:#fff"><strong>Session ' . esc_html($session['day_of_week']) . ' · ' . esc_html(substr($session['start_time'],0,5)) . '–' . esc_html(substr($session['end_time'],0,5)) . '</strong>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bh3_booking_preferences_save"><input type="hidden" name="activity_id" value="' . esc_attr($activity_id) . '"><input type="hidden" name="session_id" value="' . esc_attr($sid) . '">';
            wp_nonce_field('bh3_booking_preferences_' . $activity_id . '_' . $sid);
            echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked(!empty($prefs['enabled']), true, false) . '> Enable bookings at launch</label></p>';
            echo '<p><label>Maximum places <input type="number" name="capacity" min="1" max="500" required value="' . esc_attr($prefs['capacity'] ?? 10) . '"></label></p>';
            echo '<p><label><input type="checkbox" name="waitlist" value="1" ' . checked(!empty($prefs['waitlist']), true, false) . '> Waiting list at launch</label></p>';
            echo '<button type="submit">Save settings</button></form></article>';
        }
        if (!$sessions) { echo '<p>Add timetable sessions before setting up bookings.</p>'; }
        if (isset($_GET['saved']) && $_GET['saved'] === '1') { echo '<p role="status">Settings saved.</p>'; }
        echo '</section>';
        return ob_get_clean();
    }

    public static function save() {
        if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'staging.bubbahub.co.uk' || !is_user_logged_in() || !current_user_can('read')) { wp_die('Not authorised.', '', array('response'=>403)); }
        $activity = absint($_POST['activity_id'] ?? 0);
        $session = absint($_POST['session_id'] ?? 0);
        check_admin_referer('bh3_booking_preferences_' . $activity . '_' . $session);
        $owner = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        if (!$owner || !$activity || !$session) { wp_die('Not authorised.', '', array('response'=>403)); }
        global $wpdb;
        $valid = $wpdb->get_var($wpdb->prepare("SELECT s.id FROM {$wpdb->prefix}sessions s INNER JOIN {$wpdb->prefix}activities a ON a.id=s.activity_id WHERE s.id=%d AND a.id=%d AND a.organiser_id=%d LIMIT 1", $session, $activity, $owner));
        if (!$valid) { wp_die('Session unavailable.', '', array('response'=>403)); }
        $capacity = absint($_POST['capacity'] ?? 0);
        if ($capacity < 1 || $capacity > 500) { wp_die('Capacity must be between 1 and 500.', '', array('response'=>400)); }
        update_option('bh3_booking_session_' . $session, array('enabled'=>empty($_POST['enabled'])?0:1, 'capacity'=>$capacity, 'waitlist'=>empty($_POST['waitlist'])?0:1), false);
        wp_safe_redirect(add_query_arg(array('activity_id'=>$activity,'saved'=>'1'), home_url('/leader/bookings/')));
        exit;
    }
}
