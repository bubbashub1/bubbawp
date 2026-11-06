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
        $admin_preview = current_user_can( 'manage_options' );
        $preview_organisers = array();
        if ( $admin_preview ) {
            global $wpdb;
            $org_table = $wpdb->prefix . 'organisers';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $org_table ) ) ) === $org_table ) {
                $preview_organisers = $wpdb->get_results( "SELECT id, name FROM `{$org_table}` ORDER BY name ASC LIMIT 200", ARRAY_A );
                $allowed = array_map( 'intval', wp_list_pluck( $preview_organisers, 'id' ) );
                $requested = isset( $_GET['bh_preview_organiser'] ) ? absint( $_GET['bh_preview_organiser'] ) : 0;
                if ( $requested && in_array( $requested, $allowed, true ) ) { $organiser = $requested; }
                elseif ( ! $organiser && $allowed ) { $organiser = $allowed[0]; }
            }
        }
        if ( ! $organiser ) { return '<p>Your account is not linked to an organiser. An administrator can select a test organisation on staging.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        $activities = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,title,status FROM `{$table}` WHERE organiser_id=%d ORDER BY title ASC LIMIT 200", $organiser
        ), ARRAY_A );
        ob_start(); ?>
        <section class="bh-leader-bookings">
          <?php if ( $admin_preview ) : ?>
          <form method="get" class="bh-bookings-filters" aria-label="Administrator staging organisation preview">
            <label><strong>Staging admin: preview organisation</strong>
              <select name="bh_preview_organiser" onchange="this.form.submit()">
                <?php foreach ( $preview_organisers as $org ) : ?>
                  <option value="<?php echo esc_attr( $org['id'] ); ?>" <?php selected( $organiser, (int) $org['id'] ); ?>><?php echo esc_html( $org['name'] ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="submit">View organisation</button>
          </form>
          <p><small>Administrator-only staging preview. This does not impersonate a leader or grant access to other leader editing screens.</small></p>
          <?php endif; ?>
          <header class="bh-bookings-hero"><p>LEADER HUB</p><h1>Class calendar</h1><p>See your sessions in one place. Bookings and space limits are optional.</p></header>
          <div class="bh-bookings-notice" role="status">
            <strong>Session calendar — staging preview.</strong>
            <p>You do not need a booking link, a capacity or available-space numbers to publish a class schedule. Sessions are projected from weekly timetable rules; this calendar does not accept reservations, handle attendee details, reminders or payments.</p>
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
          $search = isset( $_GET['session_search'] ) && is_string( $_GET['session_search'] ) ? sanitize_text_field( wp_unslash( $_GET['session_search'] ) ) : '';
          $listing_filter = absint( $_GET['listing_filter'] ?? 0 );
          $mode_filter = isset( $_GET['session_mode'] ) && is_string( $_GET['session_mode'] ) ? sanitize_key( wp_unslash( $_GET['session_mode'] ) ) : 'all';
          if ( ! in_array( $mode_filter, array( 'all', 'booking', 'enquiry' ), true ) ) { $mode_filter = 'all'; }
          $owned_ids = array_map( 'intval', wp_list_pluck( $activities ?: array(), 'id' ) );
          if ( $listing_filter && ! in_array( $listing_filter, $owned_ids, true ) ) { $listing_filter = 0; }
          $enquiry_flags = get_option( 'bh3_session_enquiry_enabled', array() );
          $enquiry_flags = is_array( $enquiry_flags ) ? $enquiry_flags : array();
          $booking_flags = get_option( 'bh3_session_booking_config', array() );
          $booking_flags = is_array( $booking_flags ) ? $booking_flags : array();
          $occurrences = array();
          $session_titles = get_option( 'bh3_session_titles', array() );
          if ( ! is_array( $session_titles ) ) { $session_titles = array(); }
          $term_ranges = get_option( 'bh3_term_date_ranges', array() );
          $term_ranges = is_array( $term_ranges ) ? $term_ranges : array();
          $term_unconfigured = false;
          $ids = array_map( 'intval', wp_list_pluck( $activities ?: array(), 'id' ) );
          if ( $ids ) {
              $session_table = $wpdb->prefix . 'sessions';
              $slots = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
              $sql = "SELECT s.*,a.title FROM `{$session_table}` s JOIN `{$table}` a ON a.id=s.activity_id WHERE s.activity_id IN ($slots) AND s.frequency='weekly' LIMIT 500";
              $assignment_table = $wpdb->prefix . 'session_listing_links';
              if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $assignment_table ) ) ) === $assignment_table ) {
                  $sql = "SELECT DISTINCT s.*,a.title FROM `{$session_table}` s JOIN `{$table}` a ON a.id=s.activity_id LEFT JOIN `{$assignment_table}` sl ON sl.session_id=s.id WHERE a.organiser_id=%d AND (s.activity_id IN ($slots) OR sl.activity_id IN ($slots)) AND s.frequency='weekly' LIMIT 500";
                  $rules = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( array( $organiser ), $ids, $ids ) ), ARRAY_A );
              } else {
                  $rules = $wpdb->get_results( $wpdb->prepare( $sql, ...$ids ), ARRAY_A );
              }
              $shared_listing_map = array();
              if ( isset( $assignment_table ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $assignment_table ) ) ) === $assignment_table ) {
                  $session_ids = array_map( 'intval', wp_list_pluck( $rules ?: array(), 'id' ) );
                  if ( $session_ids ) {
                      $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
                      $links = $wpdb->get_results( $wpdb->prepare( "SELECT session_id,activity_id FROM `{$assignment_table}` WHERE session_id IN ($placeholders)", ...$session_ids ), ARRAY_A );
                      foreach ( $links as $link ) { $shared_listing_map[(int) $link['session_id']][] = (int) $link['activity_id']; }
                  }
              }
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
                          if ( ! $in_term ) { if ( ! $term_ranges ) { $term_unconfigured = true; } continue; }
                      }
                      $session_id = (int) $rule['id'];
                      $session_name = (string) ( $session_titles[$session_id] ?? $rule['title'] );
                      if ( $search !== '' ) {
                          $searchable = $session_name . ' ' . $rule['title'];
                          foreach ( $activities as $listing ) {
                              if ( (int) $listing['id'] === (int) $rule['activity_id'] || in_array( (int) $listing['id'], $shared_listing_map[$session_id] ?? array(), true ) ) {
                                  $searchable .= ' ' . $listing['title'];
                              }
                          }
                          if ( stripos( $searchable, $search ) === false ) { continue; }
                      }
                      if ( $mode_filter === 'booking' && empty( $booking_flags[$session_id]['enabled'] ) ) { continue; }
                      if ( $mode_filter === 'enquiry' && empty( $enquiry_flags[$session_id] ) ) { continue; }
                      if ( $listing_filter ) {
                          $primary_match = (int) $rule['activity_id'] === $listing_filter;
                          $shared_match = false;
                          if ( ! $primary_match && ! empty( $shared_listing_map[$session_id] ) ) {
                              $shared_match = in_array( $listing_filter, $shared_listing_map[$session_id], true );
                          }
                          if ( ! $primary_match && ! $shared_match ) { continue; }
                      }
                      $occurrences[$d][] = $rule;
                  }
              }
          }
          ?>
          <?php
          // Read-only occurrence inspector. Resolve only from the organiser-scoped,
          // date-validated calendar results above; never trust query parameters alone.
          $inspect_session = isset( $_GET['bh_occurrence_session'] ) ? absint( $_GET['bh_occurrence_session'] ) : 0;
          $inspect_date = isset( $_GET['bh_occurrence_date'] ) && is_scalar( $_GET['bh_occurrence_date'] )
              ? sanitize_text_field( wp_unslash( $_GET['bh_occurrence_date'] ) ) : '';
          $inspected = null;
          if ( $inspect_session && preg_match( '/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $inspect_date ) ) {
              $candidate = DateTimeImmutable::createFromFormat( '!Y-m-d', $inspect_date, $tz );
              if ( $candidate && $candidate->format( 'Y-m-d' ) === $inspect_date && $candidate->format( 'Y-m' ) === $first->format( 'Y-m' ) ) {
                  $candidate_day = (int) $candidate->format( 'j' );
                  foreach ( $occurrences[$candidate_day] ?? array() as $occurrence ) {
                      if ( (int) $occurrence['id'] === $inspect_session ) { $inspected = $occurrence; break; }
                  }
              }
          }
          ?>
          <?php if ( $inspected ) :
              $inspect_id = (int) $inspected['id'];
              $inspect_config = $booking_flags[$inspect_id] ?? array();
              $inspect_config = is_array( $inspect_config ) ? $inspect_config : array();
              $inspect_capacity = isset( $inspect_config['capacity'] ) ? absint( $inspect_config['capacity'] ) : 0;
          ?>
          <?php
          // Read-only, organiser-scoped counts for one selected occurrence.
          // Unrecognised statuses must never be silently treated as free places.
          $booking_table = $wpdb->prefix . 'bh3_bookings';
          $booking_table_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $booking_table ) ) ) === $booking_table;
          $booking_totals = null;
          if ( $booking_table_ready && ! empty( $inspect_config['enabled'] ) ) {
              $booking_totals = $wpdb->get_results( $wpdb->prepare(
                  "SELECT booking_status,payment_status,reservation_expires_at,places FROM `{$booking_table}`
                   WHERE organiser_id=%d AND session_id=%d AND occurrence_date=%s LIMIT 1001",
                  $organiser, $inspect_id, $inspect_date
              ), ARRAY_A );
          }
          $capacity_counts = array( 'reserved'=>0, 'confirmed'=>0, 'occupied'=>0, 'unknown'=>false, 'stale_holds'=>0 );
          if ( is_array( $booking_totals ) ) {
              if ( count( $booking_totals ) > 1000 ) { $capacity_counts['unknown'] = true; }
              else {
                  foreach ( $booking_totals as $booking_row ) {
                      $status = strtolower( trim( (string) $booking_row['booking_status'] ) );
                      $places = (int) $booking_row['places'];
                      if ( $places < 1 ) { $capacity_counts['unknown'] = true; continue; }
                      if ( in_array( $status, array( 'cancelled', 'canceled', 'expired', 'rejected' ), true ) ) { continue; }
                      if ( $status === 'reserved' ) {
                          // Expired holds remain occupied until expiry processing is verified.
                          $capacity_counts['reserved'] += $places;
                           if ( (string) $booking_row['payment_status'] === 'pending'
                               && ! empty( $booking_row['reservation_expires_at'] )
                               && strtotime( $booking_row['reservation_expires_at'] . ' UTC' ) <= time() ) {
                               $capacity_counts['stale_holds'] += $places;
                           }
                      } elseif ( in_array( $status, array( 'confirmed', 'enrolled' ), true ) ) {
                          $capacity_counts['confirmed'] += $places;
                      } else { $capacity_counts['unknown'] = true; }
                  }
                  $capacity_counts['occupied'] = $capacity_counts['reserved'] + $capacity_counts['confirmed'];
              }
          }
          ?>
          <section class="bh-bookings-panel bh-occurrence-inspector" aria-label="Selected session date">
            <h2><?php echo esc_html( $session_titles[$inspect_id] ?? $inspected['title'] ); ?> — <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $inspect_date . ' 12:00:00' ) ) ); ?></h2>
            <p><strong>Time:</strong> <?php echo esc_html( substr( (string) $inspected['start_time'], 0, 5 ) . '–' . substr( (string) $inspected['end_time'], 0, 5 ) ); ?></p>
            <p><strong>Configured capacity:</strong> <?php echo $inspect_capacity ? esc_html( (string) $inspect_capacity ) : 'Not set'; ?></p>
            <?php if ( $booking_totals === null ) : ?>
              <p role="status">Reservation counts unavailable: booking storage is not ready or bookings are disabled for this session.</p>
            <?php elseif ( $capacity_counts['unknown'] ) : ?>
              <p role="status">Reservation counts need review: unsupported or incomplete booking records were found. Remaining places are not displayed.</p>
            <?php else : ?>
              <p><strong>Reserved:</strong> <?php echo esc_html( (string) $capacity_counts['reserved'] ); ?> · <strong>Confirmed/enrolled:</strong> <?php echo esc_html( (string) $capacity_counts['confirmed'] ); ?></p>
              <?php if ( $inspect_capacity > 0 && $capacity_counts['occupied'] <= $inspect_capacity ) : ?>
                <p><strong>Unallocated capacity (preview):</strong> <?php echo esc_html( (string) ( $inspect_capacity - $capacity_counts['occupied'] ) ); ?></p>
              <?php elseif ( $inspect_capacity > 0 ) : ?>
                <p role="status">Recorded places exceed configured capacity. Please review the booking data.</p>
              <?php endif; ?>
              <?php if ( $capacity_counts['stale_holds'] > 0 ) : ?>
               <p role="status"><strong>Needs review:</strong> <?php echo esc_html( (string) $capacity_counts['stale_holds'] ); ?> place(s) are held by unpaid reservations past their expiry time. They remain occupied until a verified expiry transition releases them.</p>
               <?php endif; ?>
               <p role="status">Read-only record totals. Expired reservations remain counted until the expiry process is verified. These numbers do not guarantee live availability or permit checkout.</p>
            <?php endif; ?>
            <?php if ( $booking_table_ready && is_array( $booking_totals ) && ! $capacity_counts['unknown'] ) :
                $record_rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT id,booking_status,payment_status,places FROM `{$booking_table}`
                     WHERE organiser_id=%d AND session_id=%d AND occurrence_date=%s
                     ORDER BY id DESC LIMIT 25",
                    $organiser, $inspect_id, $inspect_date
                ), ARRAY_A );
            ?>
            <h3>Booking records (read-only preview)</h3>
            <?php if ( ! $record_rows ) : ?>
              <p>No booking records for this date.</p>
            <?php else : ?>
              <div style="overflow-x:auto"><table><thead><tr><th scope="col">Reference</th><th scope="col">Places</th><th scope="col">Booking status</th><th scope="col">Payment status</th><th scope="col">Action</th></tr></thead><tbody>
              <?php foreach ( $record_rows as $record_row ) : ?>
                <tr><td><?php echo esc_html( '#' . absint( $record_row['id'] ) ); ?></td>
                <td><?php echo esc_html( (string) absint( $record_row['places'] ) ); ?></td>
                <td><?php echo esc_html( sanitize_text_field( $record_row['booking_status'] ) ); ?></td>
                <td><?php echo esc_html( sanitize_text_field( $record_row['payment_status'] ) ); ?></td>
                <td>
                <?php
                $can_enrol = defined( 'BH3_ENABLE_LEADER_BOOKING_ACTIONS' ) && BH3_ENABLE_LEADER_BOOKING_ACTIONS === true
                    && ! $admin_preview
                    && class_exists( 'BH_Marketplace_Settings_3' )
                    && $record_row['booking_status'] === 'confirmed'
                    && $record_row['payment_status'] === 'paid'
                    && ! is_wp_error( BH_Marketplace_Settings_3::leader_enrolment_eligibility( (int) $record_row['id'] ) );
                ?>
                <?php if ( $can_enrol ) : ?>
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="bh3_leader_enrol_booking">
                    <input type="hidden" name="booking_id" value="<?php echo esc_attr( (int) $record_row['id'] ); ?>">
                    <?php wp_nonce_field( 'bh3_enrol_booking_' . (int) $record_row['id'] ); ?>
                    <button type="submit">Mark as Enrolled</button>
                  </form>
                <?php else : ?><span>—</span><?php endif; ?>
                </td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
              <p><small>Showing up to 25 records. No customer or child information is shown. Enrolment is available only when staging actions are enabled and the booking is eligible.</small></p>
            <?php endif; ?>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( remove_query_arg( array( 'bh_occurrence_session', 'bh_occurrence_date' ) ) ); ?>">Close session details</a></p>
          </section>
          <?php endif; ?>
          <form method="get" class="bh-bookings-filters" role="search" aria-label="Filter sessions">
            <?php if ( $admin_preview ) : ?><input type="hidden" name="bh_preview_organiser" value="<?php echo esc_attr( $organiser ); ?>"><?php endif; ?>
            <label>Search sessions <input type="search" name="session_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search sessions or listings"></label>
            <label>Listing <select name="listing_filter"><option value="0">All listings</option><?php foreach ( $activities as $activity ) : ?><option value="<?php echo esc_attr( $activity['id'] ); ?>" <?php selected( $listing_filter, (int) $activity['id'] ); ?>><?php echo esc_html( $activity['title'] ); ?></option><?php endforeach; ?></select></label>
            <label>Type <select name="session_mode"><option value="all" <?php selected( $mode_filter, 'all' ); ?>>All sessions</option><option value="booking" <?php selected( $mode_filter, 'booking' ); ?>>Bookings enabled</option><option value="enquiry" <?php selected( $mode_filter, 'enquiry' ); ?>>Enquiries enabled</option></select></label>
            <button type="submit">Filter</button>
            <a href="<?php echo esc_url( remove_query_arg( array( 'session_search', 'listing_filter', 'session_mode' ) ) ); ?>">Clear filters</a>
          </form>
          <div class="bh-bookings-panel bh-bookings-calendar" aria-label="Booking calendar preview">
            <div class="bh-bookings-calendar-heading">
              <h2><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp(), $tz ) ); ?></h2>
              <nav aria-label="Calendar month">
                <a href="<?php echo esc_url( add_query_arg( 'booking_month', $prev ) ); ?>" aria-label="Previous month">← Previous</a>
                <a href="<?php echo esc_url( remove_query_arg( 'booking_month' ) ); ?>">Today</a>
                <a href="<?php echo esc_url( add_query_arg( 'booking_month', $next ) ); ?>" aria-label="Next month">Next →</a>
              </nav>
            </div>
            <p class="bh-bookings-calendar-note">Weekly timetable sessions appear here. Capacity is a configured limit, not live availability. No reservations or payments are processed by this calendar. One-off dates and exceptions are coming later.</p>
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
                  <?php foreach ( $occurrences[$day] ?? array() as $session ) : ?>
                     <?php
                     $session_id = (int) $session['id'];
                     $booking_config = $booking_flags[$session_id] ?? array();
                     $booking_config = is_array( $booking_config ) ? $booking_config : array();
                     $booking_configured = ! empty( $booking_config['enabled'] );
                     $capacity = isset( $booking_config['capacity'] ) ? absint( $booking_config['capacity'] ) : 0;
                     ?>
                     <div class="bh-calendar-session">
                       <a href="<?php echo esc_url( add_query_arg( array( 'bh_occurrence_session'=>$session_id, 'bh_occurrence_date'=>$first->modify( '+' . ( $day - 1 ) . ' days' )->format( 'Y-m-d' ) ) ) ); ?>" aria-label="<?php echo esc_attr( 'View session details for ' . $day . ' ' . $first->format( 'F' ) ); ?>"><?php echo esc_html( substr( $session['start_time'], 0, 5 ) . ' ' . ( $session_titles[$session_id] ?? $session['title'] ) ); ?></a>
                       <?php if ( ! empty( $session['term_time_only'] ) ) : ?> · Term<?php endif; ?>
                       <small><a href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>(int) $session['activity_id'], 'edit_session'=>$session_id ), home_url( '/leader/timetable/' ) ) ); ?>">Edit timetable</a></small>
                       <?php if ( $booking_configured ) : ?>
                         <small class="bh-calendar-capacity"><?php echo $capacity ? esc_html( 'Capacity: ' . $capacity ) : 'Capacity not set'; ?> · Booking setup only</small>
                       <?php endif; ?>
                     </div>
                   <?php endforeach; ?>
                </div>
              <?php endfor; ?>
            </div>
          </div>
          <style>
.bh-bookings-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:16px 0}.bh-bookings-filters label{display:flex;flex-direction:column;gap:4px;flex:1 1 170px}.bh-bookings-filters input,.bh-bookings-filters select{width:100%;min-height:42px}.bh-bookings-filters button{min-height:42px}
          .bh-bookings-calendar{margin:20px 0;padding:20px;border:1px solid #dce9e0;border-radius:14px;background:#fff}
          .bh-bookings-calendar-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
          .bh-bookings-calendar-heading h2{margin:0}
          .bh-bookings-calendar-heading nav{display:flex;gap:12px;flex-wrap:wrap}
          .bh-bookings-calendar-note{color:#51675c;font-size:.9rem}
          .bh-bookings-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
          .bh-bookings-calendar-weekday{text-align:center;font-size:.8rem;font-weight:700;padding:8px 0;color:#245d48}
          .bh-bookings-calendar-day,.bh-bookings-calendar-empty{min-height:66px;border:1px solid #e3eae6;border-radius:6px;padding:7px;font-size:.9rem}
          .bh-bookings-calendar-day{background:#f8fbf9;overflow-wrap:anywhere}.bh-calendar-capacity{display:block;margin-top:3px;font-size:.7rem;line-height:1.3;color:#365a4a}.bh-calendar-add{display:block;font-size:.75rem;margin-top:5px}.bh-calendar-session{background:#dceee4;color:#245d48;border-radius:5px;padding:4px;margin-top:5px;font-size:.75rem}
          @media(max-width:600px){.bh-bookings-calendar{padding:12px}.bh-bookings-calendar-day,.bh-bookings-calendar-empty{min-height:45px;padding:4px;font-size:.8rem}.bh-bookings-calendar-grid{gap:2px}}
          </style>
          <div class="bh-bookings-panel">
            <h2>Manage your sessions</h2>
            <p>Create a session once, assign it to multiple listings and manage it in one place. Booking and payment collection are not active in this preview.</p>
            <?php if ( ! $activities ) : ?>
              <p>No linked activities found. <a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">Go to your dashboard</a>.</p>
            <?php else : ?>
              <p><label for="bh-calendar-new-activity"><strong>Add a session to a listing</strong></label></p>
              <form method="get" action="<?php echo esc_url( home_url( '/leader/timetable/' ) ); ?>">
                <select id="bh-calendar-new-activity" name="activity_id" required>
                  <?php foreach ( $activities as $activity ) : ?><option value="<?php echo esc_attr( $activity['id'] ); ?>"><?php echo esc_html( $activity['title'] ); ?></option><?php endforeach; ?>
                </select>
                <button type="submit">+ Add session</button>
              </form>
              <ul class="bh-bookings-activities">
                <?php foreach ( $activities as $activity ) : ?>
                  <li><div><strong><?php echo esc_html( $activity['title'] ); ?></strong><small><?php echo esc_html( ucfirst( (string) $activity['status'] ) ); ?></small></div>
                  <a href="<?php echo esc_url( add_query_arg( 'activity_id', (int) $activity['id'], home_url( '/leader/timetable/' ) ) ); ?>">Manage sessions</a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
