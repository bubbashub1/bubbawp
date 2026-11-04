<?php
/** Read-only leader booking queue; staging-only, never installs tables. */
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Booking_Queue_3 {
    public static function register() {
        add_shortcode('bh_leader_booking_queue', array(__CLASS__, 'render'));
    }
    public static function render() {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') { return ''; }
        if (!is_user_logged_in() || !current_user_can('read')) { return '<p>Please sign in to view bookings.</p>'; }
        $owner = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        if (!$owner) { return '<p>Your account is not linked to an organiser.</p>'; }
        if (!class_exists('BH_Leader_Booking_Storage_3') || !BH_Leader_Booking_Storage_3::installed()) {
            return '<p>Booking records are not yet available. The staging administrator must install the booking schema before reservations can be shown.</p>';
        }
        global $wpdb;
        $table = BH_Leader_Booking_Storage_3::tables()['reservations'];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return '<p>Booking storage is unavailable.</p>';
        }
        $filter = sanitize_key(wp_unslash($_GET['booking_status'] ?? 'pending'));
        if (!in_array($filter, array('pending','confirmed','waitlisted','cancelled','all'), true)) { $filter = 'pending'; }
        $activity_id = absint($_GET['activity_id'] ?? 0);
        if ($activity_id) {
            $owned = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}activities WHERE id=%d AND organiser_id=%d", $activity_id, $owner));
            if (!$owned) { return '<p>Activity unavailable.</p>'; }
        }
        $where = 'organiser_id=%d';
        $params = array($owner);
        if ($filter !== 'all') { $where .= ' AND status=%s'; $params[] = $filter; }
        if ($activity_id) { $where .= ' AND activity_id=%d'; $params[] = $activity_id; }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,activity_id,session_starts_at,parent_first_name,parent_last_name,parent_email,parent_phone,child_first_name,child_age_months,status,created_at FROM {$table} WHERE {$where} ORDER BY created_at DESC,id DESC LIMIT 100", $params), ARRAY_A);
        $counts = $wpdb->get_results($wpdb->prepare("SELECT status,COUNT(*) total FROM {$table} WHERE organiser_id=%d GROUP BY status", $owner), ARRAY_A);
        $totals = array('pending'=>0,'confirmed'=>0,'waitlisted'=>0,'cancelled'=>0);
        foreach ((array)$counts as $count) { if (isset($totals[$count['status']])) { $totals[$count['status']] = (int)$count['total']; } }
        $base = home_url('/leader/bookings/');
        if ($activity_id) { $base = add_query_arg('activity_id', $activity_id, $base); }
        ob_start();
        echo '<section class="bh-leader-booking-queue" style="max-width:1100px;margin:24px auto;padding:16px"><h2>Reservations</h2><p>Read-only staging preview. No placement decisions or emails are sent here.</p>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:15px 0">';
        foreach (array('pending'=>'Pending','confirmed'=>'Confirmed','waitlisted'=>'Waiting list','cancelled'=>'Cancelled','all'=>'All') as $key=>$label) {
            $url = add_query_arg('booking_status',$key,$base);
            $count = $key==='all' ? array_sum($totals) : $totals[$key];
            echo '<a style="padding:9px 13px;border:1px solid #cadcd0;border-radius:12px" href="'.esc_url($url).'">'.esc_html($label).' ('.esc_html($count).')</a>';
        }
        echo '</div>';
        if (!$rows) { echo '<p>No matching reservations.</p></section>'; return ob_get_clean(); }
        echo '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th scope="col">Parent</th><th scope="col">Child</th><th scope="col">Activity ID</th><th scope="col">Session</th><th scope="col">Status</th><th scope="col">Contact</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $name = trim($row['parent_first_name'].' '.($row['parent_last_name'] ? substr($row['parent_last_name'],0,1).'.' : ''));
            $session = $row['session_starts_at'] ? wp_date(get_option('date_format').' '.get_option('time_format'), (new DateTimeImmutable($row['session_starts_at'], wp_timezone()))->getTimestamp(), wp_timezone()) : 'Not assigned';
            echo '<tr>';
            foreach (array($name,$row['child_first_name'],$row['activity_id'],$session,ucfirst($row['status']),$row['parent_email']) as $value) {
                echo '<td style="padding:12px;border-bottom:1px solid #e5ede7">'.esc_html($value).'</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
        return ob_get_clean();
    }
}
