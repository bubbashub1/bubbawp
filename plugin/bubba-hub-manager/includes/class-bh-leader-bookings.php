<?php
/** Staging-only bookings overview. Read-only until reservation schema is verified. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Bookings_3 {
    public static function register() {
        // Do not override an existing booking implementation.
        if ( ! shortcode_exists( 'bh_leader_bookings' ) ) {
            add_shortcode( 'bh_leader_bookings', array( __CLASS__, 'render' ) );
        }
        // Preserve the existing WordPress page shortcode. An integrated booking
        // implementation can register its own handler before this fallback boots.
        if ( ! shortcode_exists( 'bh_leader_booking_dashboard' ) ) {
            add_shortcode( 'bh_leader_booking_dashboard', array( __CLASS__, 'render' ) );
        }
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return '<p>Please sign in to view your bookings dashboard.</p>';
        }
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser ) { return '<p>Your account is not linked to an organiser.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        $activities = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,title,status FROM `{$table}` WHERE organiser_id=%d ORDER BY title ASC LIMIT 200", $organiser
        ), ARRAY_A );
        ob_start(); ?>
        <section class="bh-leader-bookings">
          <header class="bh-bookings-hero"><p>LEADER HUB</p><h1>Class calendar</h1><p>See your sessions in one place. Bookings and space limits are optional.</p></header>
          <div class="bh-bookings-notice" role="status">
            <strong>Calendar preview — session dates are not connected yet.</strong>
            <p>You do not need a booking link, a capacity or available-space numbers to publish a class schedule. This preview shows linked activities only; it does not read or change reservations, attendee details, reminders or payments.</p>
          </div>
          <?php
          $month = isset( $_GET['booking_month'] ) && is_string( $_GET['booking_month'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_month'] ) ) : wp_date( 'Y-m' );
          if ( ! preg_match( '/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month ) ) { $month = wp_date( 'Y-m' ); }
          $tz = wp_timezone();
          $first = DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', $tz );
          $first = $first ?: new DateTimeImmutable( 'first day of this month', $tz );
          $start = (int) $first->format( 'N' ) - 1;
          $days = (int) $first->format( 't' );
          $prev = $first->modify( '-1 month' )->format( 'Y-m' );
          $next = $first->modify( '+1 month' )->format( 'Y-m' );
          $occurrences = array();
          $term_ranges = get_option( 'bh3_term_date_ranges', array() );
          $term_ranges = is_array( $term_ranges ) ? $term_ranges : array();
          $term_unconfigured = false;
          $ids = array_map( 'intval', wp_list_pluck( $activities ?: array(), 'id' ) );
          if ( $ids ) {
              $session_table = $wpdb->prefix . 'sessions';
              $slots = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
              $sql = "SELECT s.*,a.title FROM `{$session_table}` s JOIN `{$table}` a ON a.id=s.activity_id WHERE s.activity_id IN ($slots) AND s.frequency='weekly' LIMIT 500";
              $rules = $wpdb->get_results( $wpdb->prepare( $sql, ...$ids ), ARRAY_A );
              for ( $d = 1; $d <= $days; $d++ ) {
                  $date = $first->modify( '+' . ( $d - 1 ) . ' days' );
                  $ymd = $date->format( 'Y-m-d' );
                  foreach ( $rules as $rule ) {
                      if ( (int) $rule['day_of_week'] !== (int) $date->format( 'N' ) ) { continue; }
                      if ( ! empty( $rule['start_date'] ) && $ymd < $rule['start_date'] ) { continue; }
                      if ( ! empty( $rule['end_date'] ) && $ymd > $rule['end_date'] ) { continue; }
                      if ( in_array( $rule['status'] ?? '', array( 'cancelled', 'canceled', 'inactive' ), true ) ) { continue; }
                      if ( ! empty( $rule['term_time_only'] ) ) {
                          $in_term = false;
                          foreach ( $term_ranges as $range ) {
                              if ( is_array( $range ) && ! empty( $range['start'] ) && ! empty( $range['end'] ) && $ymd >= $range['start'] && $ymd <= $range['end'] ) { $in_term = true; break; }
                          }
                          if ( ! $in_term ) { $term_unconfigured = true; continue; }
                      }
                      $occurrences[$d][] = $rule;
                  }
              }
          }
          ?>
          <div class="bh-bookings-panel bh-bookings-calendar" aria-label="Booking calendar preview">
            <div class="bh-bookings-calendar-heading">
              <h2><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp(), $tz ) ); ?></h2>
              <nav aria-label="Calendar month">
                <a href="<?php echo esc_url( add_query_arg( 'booking_month', $prev ) ); ?>" aria-label="Previous month">← Previous</a>
                <a href="<?php echo esc_url( remove_query_arg( 'booking_month' ) ); ?>">Today</a>
                <a href="<?php echo esc_url( add_query_arg( 'booking_month', $next ) ); ?>" aria-label="Next month">Next →</a>
              </nav>
            </div>
            <p class="bh-bookings-calendar-note">Weekly timetable sessions appear here. Booking links and place numbers are optional. One-off dates and exceptions are coming later.</p>
            <?php if ( $term_unconfigured ) : ?><p role="status">Term-time sessions require configured term date ranges; no school dates are assumed.</p><?php endif; ?>
            <div class="bh-bookings-calendar-grid" role="grid" aria-label="<?php echo esc_attr( $first->format( 'F Y' ) ); ?>">
              <?php foreach ( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) as $weekday ) : ?>
                <div class="bh-bookings-calendar-weekday" role="columnheader"><?php echo esc_html( $weekday ); ?></div>
              <?php endforeach; ?>
              <?php for ( $blank = 0; $blank < $start; $blank++ ) : ?><div class="bh-bookings-calendar-empty" aria-hidden="true"></div><?php endfor; ?>
              <?php for ( $day = 1; $day <= $days; $day++ ) : ?>
                <div class="bh-bookings-calendar-day" role="gridcell" aria-label="<?php echo esc_attr( $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'm' ), $day )->format( 'j F Y' ) ); ?>">
                  <span><?php echo esc_html( (string) $day ); ?></span>
                  <?php if ( count( $activities ) === 1 ) : ?>
                    <a class="bh-calendar-add" href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>(int) $activities[0]['id'], 'session_date'=>$first->modify( '+' . ( $day - 1 ) . ' days' )->format( 'Y-m-d' ) ), home_url( '/leader/timetable/' ) ) ); ?>" aria-label="<?php echo esc_attr( 'Add session for ' . $day . ' ' . $first->format( 'F' ) ); ?>">+ Add</a>
                  <?php endif; ?>
                  <?php foreach ( $occurrences[$day] ?? array() as $session ) : ?><div class="bh-calendar-session"><a href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>(int) $session['activity_id'], 'edit_session'=>(int) $session['id'] ), home_url( '/leader/timetable/' ) ) ); ?>"><?php echo esc_html( substr( $session['start_time'], 0, 5 ) . ' ' . $session['title'] ); ?></a><?php if ( ! empty( $session['term_time_only'] ) ) : ?> · Term<?php endif; ?></div><?php endforeach; ?>
                </div>
              <?php endfor; ?>
            </div>
          </div>
          <style>
          .bh-bookings-calendar{margin:20px 0;padding:20px;border:1px solid #dce9e0;border-radius:14px;background:#fff}
          .bh-bookings-calendar-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
          .bh-bookings-calendar-heading h2{margin:0}
          .bh-bookings-calendar-heading nav{display:flex;gap:12px;flex-wrap:wrap}
          .bh-bookings-calendar-note{color:#51675c;font-size:.9rem}
          .bh-bookings-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
          .bh-bookings-calendar-weekday{text-align:center;font-size:.8rem;font-weight:700;padding:8px 0;color:#245d48}
          .bh-bookings-calendar-day,.bh-bookings-calendar-empty{min-height:66px;border:1px solid #e3eae6;border-radius:6px;padding:7px;font-size:.9rem}
          .bh-bookings-calendar-day{background:#f8fbf9;overflow-wrap:anywhere}.bh-calendar-add{display:block;font-size:.75rem;margin-top:5px}.bh-calendar-session{background:#dceee4;color:#245d48;border-radius:5px;padding:4px;margin-top:5px;font-size:.75rem}
          @media(max-width:600px){.bh-bookings-calendar{padding:12px}.bh-bookings-calendar-day,.bh-bookings-calendar-empty{min-height:45px;padding:4px;font-size:.8rem}.bh-bookings-calendar-grid{gap:2px}}
          </style>
          <div class="bh-bookings-panel">
            <h2>Your activities</h2>
            <?php if ( ! $activities ) : ?>
              <p>No linked activities found. <a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">Go to your dashboard</a>.</p>
            <?php else : ?>
              <ul class="bh-bookings-activities">
                <?php foreach ( $activities as $activity ) : ?>
                  <li><div><strong><?php echo esc_html( $activity['title'] ); ?></strong><small><?php echo esc_html( ucfirst( (string) $activity['status'] ) ); ?></small></div>
                  <span>Session calendar integration pending</span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
