<?php
/** Staging-only reservation placement decision. No emails or payments. */
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Booking_Actions_3 {
    public static function register() {
        add_action('admin_post_bh3_booking_confirm', array(__CLASS__, 'confirm'));
    }
    public static function confirm() {
        if (strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk'
            || !is_user_logged_in() || !current_user_can('read')) {
            wp_die('Not authorised.', '', array('response'=>403));
        }
        $id = absint($_POST['reservation_id'] ?? 0);
        $owner = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        $date = sanitize_text_field(wp_unslash($_POST['booking_date'] ?? ''));
        $month = sanitize_text_field(wp_unslash($_POST['booking_month'] ?? ''));
        $session_id = absint($_POST['booking_session'] ?? 0);
        if (!$owner || !$id || !$session_id || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-[0-3][0-9]$/',$date)
            || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/',$month) || substr($date,0,7)!==$month) {
            wp_die('Invalid reservation.', '', array('response'=>400));
        }
        check_admin_referer('bh3_booking_confirm_'.$id);
        if (!class_exists('BH_Leader_Booking_Storage_3') || !BH_Leader_Booking_Storage_3::installed()) {
            wp_die('Booking storage unavailable.', '', array('response'=>409));
        }
        global $wpdb;
        $tables = BH_Leader_Booking_Storage_3::tables();
        $reservation_table = $tables['reservations'];
        $events_table = $tables['events'];
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT r.id,r.organiser_id,r.activity_id,r.session_id,r.status,r.session_starts_at FROM {$reservation_table} r
             INNER JOIN {$wpdb->prefix}activities a ON a.id=r.activity_id AND a.organiser_id=r.organiser_id
             WHERE r.id=%d AND r.organiser_id=%d AND r.session_id=%d LIMIT 1",
            $id,$owner,$session_id
        ),ARRAY_A);
        if (!$reservation || $reservation['status']!=='pending' || substr((string)$reservation['session_starts_at'],0,10)!==$date) {
            wp_die('Reservation is no longer pending or unavailable.', '', array('response'=>409));
        }
        // Capacity is rechecked at the moment of confirmation to avoid oversubscription.
        $prefs = get_option('bh3_booking_session_'.$session_id,array());
        if (empty($prefs['enabled'])) { wp_die('Booking is not enabled for this session.', '', array('response'=>409)); }
        $capacity = absint($prefs['capacity'] ?? 0);
        if ($capacity<1) { wp_die('Session capacity is not configured.', '', array('response'=>409)); }
        $wpdb->query('START TRANSACTION');
        $confirmed = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reservation_table} WHERE organiser_id=%d AND session_id=%d AND session_starts_at=%s AND status='confirmed' FOR UPDATE",
            $owner,$session_id,$reservation['session_starts_at']
        ));
        if ($confirmed >= $capacity) {
            $wpdb->query('ROLLBACK');
            wp_die('No places remain. Reservation was not changed.', '', array('response'=>409));
        }
        $now = current_time('mysql',true);
        $updated = $wpdb->update($reservation_table,
            array('status'=>'confirmed','confirmed_at'=>$now,'updated_at'=>$now),
            array('id'=>$id,'organiser_id'=>$owner,'status'=>'pending'),
            array('%s','%s','%s'),array('%d','%d','%s'));
        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            wp_die('Reservation was changed elsewhere. Refresh and try again.', '', array('response'=>409));
        }
        $logged = $wpdb->insert($events_table,array(
            'reservation_id'=>$id,'organiser_id'=>$owner,'actor_user_id'=>get_current_user_id(),
            'event_type'=>'placement_confirmed','event_detail'=>'Confirmed by leader; no email sent',
            'created_at'=>$now
        ),array('%d','%d','%d','%s','%s','%s'));
        if (!$logged) {
            $wpdb->query('ROLLBACK');
            wp_die('Could not save audit history. Reservation was not changed.', '', array('response'=>500));
        }
        $wpdb->query('COMMIT');
        $url = add_query_arg(array(
            'booking_tab'=>'calendar','booking_month'=>$month,'booking_date'=>$date,
            'booking_session'=>$session_id,'booking_notice'=>'confirmed'
        ),home_url('/leader/bookings/'));
        wp_safe_redirect($url.'#bh3-cal-session-detail');
        exit;
    }
}
