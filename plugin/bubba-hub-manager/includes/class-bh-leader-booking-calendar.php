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
        // Query monthly reservation totals once, never once per calendar cell.
        $reservation_totals = array();
        $storage_ready = class_exists('BH_Leader_Booking_Storage_3') && BH_Leader_Booking_Storage_3::installed();
        if ($storage_ready) {
            $booking_table = BH_Leader_Booking_Storage_3::tables()['reservations'];
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($booking_table)));
            if ($exists === $booking_table) {
                $start = $first->format('Y-m-d') . ' 00:00:00';
                $end = $first->modify('+1 month')->format('Y-m-d') . ' 00:00:00';
                $bookings = $wpdb->get_results($wpdb->prepare(
                    "SELECT session_id,session_starts_at,status,COUNT(*) AS total FROM {$booking_table} WHERE organiser_id=%d AND session_starts_at >= %s AND session_starts_at < %s AND status IN ('pending','confirmed','waitlisted') GROUP BY session_id,session_starts_at,status",
                    $owner, $start, $end
                ), ARRAY_A);
                foreach ((array)$bookings as $booking) {
                    $key = absint($booking['session_id']) . '|' . substr($booking['session_starts_at'],0,10);
                    if (!isset($reservation_totals[$key])) { $reservation_totals[$key] = array('pending'=>0,'confirmed'=>0,'waitlisted'=>0); }
                    $status = $booking['status'];
                    if (isset($reservation_totals[$key][$status])) { $reservation_totals[$key][$status] += (int)$booking['total']; }
                }
            } else { $storage_ready = false; }
        }
        $session_capacity = array();
        foreach ((array)$rows as $row) {
            $prefs = get_option('bh3_booking_session_' . absint($row['id']), array());
            $session_capacity[absint($row['id'])] = !empty($prefs['enabled']) ? max(1,absint($prefs['capacity'] ?? 10)) : null;
        }
        $by_day = array();
        foreach ((array)$rows as $row) { $day = (int)$row['day_of_week']; if ($day >= 1 && $day <= 7) { $by_day[$day][] = $row; } }
        $base = home_url('/leader/bookings/');
        if ($activity_id) { $base = add_query_arg('activity_id', $activity_id, $base); }
        $base = add_query_arg('booking_tab', 'calendar', $base);
        $selected = sanitize_text_field(wp_unslash($_GET['booking_date'] ?? ''));
        if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])$/', $selected)) { $selected = ''; }
        $selected_date = $selected ? DateTimeImmutable::createFromFormat('!Y-m-d', $selected, $tz) : false;
        if (!$selected_date || $selected_date->format('Y-m-d') !== $selected || $selected_date->format('Y-m') !== $month) { $selected = ''; $selected_date = false; }
        $selected_session = absint($_GET['booking_session'] ?? 0);
        $selected_session_row = null;
        $selected_reservations = array();
        if ($selected && $selected_session) {
            foreach ((array)$rows as $row) {
                if (absint($row['id']) !== $selected_session || (int)$row['day_of_week'] !== (int)$selected_date->format('N')) { continue; }
                if ((!empty($row['start_date']) && $selected < $row['start_date']) || (!empty($row['end_date']) && $selected > $row['end_date'])) { continue; }
                $selected_session_row = $row;
                break;
            }
            if ($selected_session_row && $storage_ready) {
                $selected_reservations = $wpdb->get_results($wpdb->prepare(
                    "SELECT id,parent_first_name,parent_last_name,child_first_name,child_age_months,status,created_at FROM {$booking_table} WHERE organiser_id=%d AND activity_id=%d AND session_id=%d AND session_starts_at >= %s AND session_starts_at < %s ORDER BY FIELD(status,'pending','confirmed','waitlisted','cancelled'),created_at DESC LIMIT 100",
                    $owner, absint($selected_session_row['activity_id']), $selected_session,
                    $selected . ' 00:00:00', $selected_date->modify('+1 day')->format('Y-m-d') . ' 00:00:00'
                ), ARRAY_A);
            }
        }
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
        .bh3-cal-item small{display:block;color:#52685c}.bh3-cal-date-link{display:inline-block;padding:2px 6px;border-radius:7px;color:#195940;text-decoration:none}.bh3-cal-date-link:hover,.bh3-cal-date-link:focus{background:#dbeee1}.bh3-cal-day.is-selected{border:2px solid #195940;background:#f1f8f3}.bh3-cal-selected{margin-top:18px;border:1px solid #d5e4da;border-radius:13px;padding:17px}.bh3-cal-selected ul{margin:8px 0;padding-left:20px}.bh3-cal-session-link{display:inline-block;margin-left:8px;font-size:12px;color:#195940;font-weight:700}.bh3-cal-roster{margin-top:16px;overflow-x:auto}.bh3-cal-roster table{width:100%;border-collapse:collapse}.bh3-cal-roster th,.bh3-cal-roster td{padding:10px;text-align:left;border-bottom:1px solid #e0eae3}.bh3-cal-muted{opacity:.35}.bh3-cal-note{font-size:13px;color:#53675c;margin:12px 0 0}
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
            echo '<div class="bh3-cal-day'.($selected === $ymd ? ' is-selected' : '').'"><time datetime="'.esc_attr($ymd).'"><a class="bh3-cal-date-link" href="'.esc_url(add_query_arg(array('booking_month'=>$month,'booking_date'=>$ymd),$base)).'" aria-label="View sessions for '.esc_attr(wp_date(get_option('date_format'),$date->getTimestamp(),$tz)).'">'.esc_html($number).'</a></time>';
            foreach (($by_day[(int)$date->format('N')] ?? array()) as $session) {
                if ((!empty($session['start_date']) && $ymd < $session['start_date']) || (!empty($session['end_date']) && $ymd > $session['end_date'])) { continue; }
                echo '<span class="bh3-cal-item"><strong>'.esc_html(substr($session['start_time'],0,5)).'</strong> '.esc_html($session['title']);
                $key = absint($session['id']) . '|' . $ymd;
                if ($storage_ready && isset($reservation_totals[$key])) {
                    $total = $reservation_totals[$key];
                    echo '<small>'.esc_html($total['confirmed']).' confirmed · '.esc_html($total['pending']).' pending</small>';
                }
                if (!empty($session['term_time_only'])) { echo '<small>Term time · verify date</small>'; }
                echo '</span>';
            }
            echo '</div>';
        }
        ?></div>
        <?php if ($selected_date) : ?>
          <div class="bh3-cal-selected" id="bh3-cal-selected" aria-live="polite">
            <h3><?php echo esc_html(wp_date(get_option('date_format'), $selected_date->getTimestamp(), $tz)); ?> · Scheduled sessions</h3>
            <?php
            $shown = 0;
            foreach (($by_day[(int)$selected_date->format('N')] ?? array()) as $session) {
                if ((!empty($session['start_date']) && $selected < $session['start_date']) || (!empty($session['end_date']) && $selected > $session['end_date'])) { continue; }
                if (!$shown) { echo '<ul>'; }
                $shown++;
                echo '<li><strong>'.esc_html($session['title']).'</strong> · '.esc_html(substr($session['start_time'],0,5).'–'.substr($session['end_time'],0,5));
                $key = absint($session['id']) . '|' . $selected;
                if ($storage_ready) {
                    $total = $reservation_totals[$key] ?? array('confirmed'=>0,'pending'=>0,'waitlisted'=>0);
                    echo ' <small>· '.esc_html($total['confirmed']).' confirmed, '.esc_html($total['pending']).' pending, '.esc_html($total['waitlisted']).' waiting</small>';
                    $capacity = $session_capacity[absint($session['id'])] ?? null;
                    if ($capacity !== null) { echo ' <small>· '.esc_html(max(0,$capacity-$total['confirmed'])).' of '.esc_html($capacity).' places not confirmed</small>'; }
                } else { echo ' <small>· Booking totals unavailable until setup</small>'; }
                if (!empty($session['term_time_only'])) { echo ' <small>(term-time date requires verification)</small>'; }
                echo ' <a class="bh3-cal-session-link" href="'.esc_url(add_query_arg(array('booking_month'=>$month,'booking_date'=>$selected,'booking_session'=>absint($session['id'])),$base)).'#bh3-cal-session-detail">View reservations</a>';
                echo '</li>';
            }
            if ($shown) { echo '</ul>'; } else { echo '<p>No timetable sessions scheduled for this date.</p>'; }
            ?>
            <?php if ($selected_session_row) : ?>
              <div class="bh3-cal-roster" id="bh3-cal-session-detail">
                <h4><?php echo esc_html($selected_session_row['title']); ?> · <?php echo esc_html(substr($selected_session_row['start_time'],0,5)); ?></h4>
                <p class="bh3-cal-note">Confirm pending placements here. Changes are recorded in an audit log; no emails are sent.</p>
                <?php if (isset($_GET['booking_notice']) && sanitize_key(wp_unslash($_GET['booking_notice']))==='confirmed') : ?><p role="status">Placement confirmed. No email was sent.</p><?php endif; ?>
                <?php if (!$storage_ready) : ?>
                  <p>Reservation records are unavailable until the booking schema is installed.</p>
                <?php elseif (!$selected_reservations) : ?>
                  <p>No reservations recorded for this session.</p>
                <?php else : ?>
                  <table><thead><tr><th scope="col">Parent</th><th scope="col">Child</th><th scope="col">Age</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
                  <?php foreach ($selected_reservations as $reservation) : ?>
                    <tr>
                      <td><?php echo esc_html(trim($reservation['parent_first_name'].' '.($reservation['parent_last_name'] ? substr($reservation['parent_last_name'],0,1).'.' : ''))); ?></td>
                      <td><?php echo esc_html($reservation['child_first_name']); ?></td>
                      <td><?php echo $reservation['child_age_months'] === null ? '—' : esc_html((string)absint($reservation['child_age_months']).' months'); ?></td>
                      <td><?php echo esc_html(ucfirst($reservation['status'])); ?></td>
                      <td>
                        <?php if ($reservation['status']==='pending') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                          <input type="hidden" name="action" value="bh3_booking_confirm">
                          <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation['id']); ?>">
                          <input type="hidden" name="booking_date" value="<?php echo esc_attr($selected); ?>">
                          <input type="hidden" name="booking_month" value="<?php echo esc_attr($month); ?>">
                          <input type="hidden" name="booking_session" value="<?php echo esc_attr($selected_session); ?>">
                          <?php wp_nonce_field('bh3_booking_confirm_'.absint($reservation['id'])); ?>
                          <button type="submit">Confirm place</button>
                        </form>
                        <?php else : ?>—<?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody></table>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <p class="bh3-cal-note">Counts reflect stored reservations only, not verified availability. No booking actions are enabled yet.</p>
          </div>
        <?php endif; ?>
        <?php if (!$rows) { echo '<p>No weekly timetable sessions found for this organiser.</p>'; } ?>
        </section>
        <?php return ob_get_clean();
    }
}
