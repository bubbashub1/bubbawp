<?php
/** Staging-only, read-only monthly leader timetable calendar. */
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Booking_Calendar_3 {
    public static function register() { add_shortcode('bh_leader_booking_calendar', array(__CLASS__, 'render')); }
    public static function render() {
        if (strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') { return ''; }
        if (!is_user_logged_in() || !current_user_can('read')) { return '<p>Sign in to see your timetable.</p>'; }
        $owner = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        if (!$owner) { return '<p>Link an organiser to view its timetable.</p>'; }
        $month = sanitize_text_field(wp_unslash($_GET['booking_month'] ?? wp_date('Y-m')));
        if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month)) { $month = wp_date('Y-m'); }
        $tz = wp_timezone();
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', $tz);
        if (!$first) { return '<p>Invalid month.</p>'; }
        $activity_id = absint($_GET['activity_id'] ?? 0);
        global $wpdb;
        $where = 'a.organiser_id=%d AND s.status=%s AND s.frequency=%s';
        $args = array($owner, 'scheduled', 'weekly');
        if ($activity_id) { $where .= ' AND a.id=%d'; $args[] = $activity_id; }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT s.id,s.activity_id,s.day_of_week,s.start_time,s.end_time,s.start_date,s.end_date,s.term_time_only,a.title FROM {$wpdb->prefix}sessions s INNER JOIN {$wpdb->prefix}activities a ON a.id=s.activity_id WHERE {$where} ORDER BY s.start_time,a.title LIMIT 300", $args), ARRAY_A);
        $by_day = array();
        foreach ((array)$rows as $row) { $day = (int)$row['day_of_week']; if ($day >= 1 && $day <= 7) { $by_day[$day][] = $row; } }
        $base = home_url('/leader/bookings/');
        if ($activity_id) { $base = add_query_arg('activity_id', $activity_id, $base); }
        $previous = $first->modify('-1 month')->format('Y-m');
        $next = $first->modify('+1 month')->format('Y-m');
        $start_offset = (int)$first->format('N') - 1;
        $days_in_month = (int)$first->format('t');
        ob_start(); ?>
        <section class="bh3-booking-calendar" aria-label="Leader timetable calendar">
        <style>
        .bh3-booking-calendar{max-width:1150px;margin:22px auto;padding:20px;border:1px solid #dbe7df;border-radius:18px;background:#fff;color:#1b3c31;box-sizing:border-box}
        .bh3-booking-calendar *{box-sizing:border-box}.bh3-cal-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
        .bh3-cal-head h2{margin:0;font-size:24px}.bh3-cal-head a{border:1px solid #c6d8cd;border-radius:9px;padding:9px 13px;color:#1a5743;text-decoration:none;font-weight:600}
        .bh3-cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px;margin-top:16px}.bh3-cal-dayname{text-align:center;font-weight:700;font-size:13px;padding:8px 0}
        .bh3-cal-day{border:1px solid #e1eae4;border-radius:9px;min-height:105px;padding:7px;overflow-wrap:anywhere}.bh3-cal-day time{font-weight:700;font-size:13px}
        .bh3-cal-item{display:block;background:#edf6f0;border-left:3px solid #2c7555;border-radius:5px;padding:5px;margin-top:5px;font-size:12px;line-height:1.35}
        .bh3-cal-item small{display:block;color:#52685c}.bh3-cal-muted{opacity:.35}.bh3-cal-note{font-size:13px;color:#53675c;margin:12px 0 0}
        @media(max-width:700px){.bh3-booking-calendar{padding:12px}.bh3-cal-grid{gap:3px}.bh3-cal-day{padding:4px;min-height:74px}.bh3-cal-item{font-size:10px;padding:3px}.bh3-cal-dayname{font-size:11px}}
        </style>
        <div class="bh3-cal-head">
          <h2><?php echo esc_html(wp_date('F Y', $first->getTimestamp(), $tz)); ?></h2>
          <nav aria-label="Calendar month"><a href="<?php echo esc_url(add_query_arg('booking_month',$previous,$base)); ?>">← Previous</a> <a href="<?php echo esc_url(add_query_arg('booking_month',$next,$base)); ?>">Next →</a></nav>
        </div>
        <p class="bh3-cal-note">Weekly timetable preview, not confirmed booking availability. Term-time sessions may not run during holidays.</p>
        <div class="bh3-cal-grid">
        <?php foreach (array('Mon','Tue','Wed','Thu','Fri','Sat','Sun') as $name) { echo '<div class="bh3-cal-dayname">'.esc_html($name).'</div>'; }
        for ($blank=0;$blank<$start_offset;$blank++) { echo '<div class="bh3-cal-day bh3-cal-muted" aria-hidden="true"></div>'; }
        for ($number=1;$number<=$days_in_month;$number++) {
            $date = $first->setDate((int)$first->format('Y'),(int)$first->format('m'),$number);
            $ymd = $date->format('Y-m-d');
            echo '<div class="bh3-cal-day"><time datetime="'.esc_attr($ymd).'">'.esc_html($number).'</time>';
            foreach (($by_day[(int)$date->format('N')] ?? array()) as $session) {
                if ((!empty($session['start_date']) && $ymd < $session['start_date']) || (!empty($session['end_date']) && $ymd > $session['end_date'])) { continue; }
                echo '<span class="bh3-cal-item"><strong>'.esc_html(substr($session['start_time'],0,5)).'</strong> '.esc_html($session['title']);
                if (!empty($session['term_time_only'])) { echo '<small>Term time · verify date</small>'; }
                echo '</span>';
            }
            echo '</div>';
        }
        ?></div>
        <?php if (!$rows) { echo '<p>No weekly timetable sessions found for this organiser.</p>'; } ?>
        </section>
        <?php return ob_get_clean();
    }
}
