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
          <header class="bh-bookings-hero"><p>LEADER HUB</p><h1>Bookings</h1><p>Manage your class reservations from one place.</p></header>
          <div class="bh-bookings-notice" role="status">
            <strong>Booking records are not connected yet.</strong>
            <p>This preview shows your linked activities only. No reservations, attendee details, reminders or payments are being read or changed.</p>
          </div>
          <div class="bh-bookings-panel">
            <h2>Your activities</h2>
            <?php if ( ! $activities ) : ?>
              <p>No linked activities found. <a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">Go to your dashboard</a>.</p>
            <?php else : ?>
              <ul class="bh-bookings-activities">
                <?php foreach ( $activities as $activity ) : ?>
                  <li><div><strong><?php echo esc_html( $activity['title'] ); ?></strong><small><?php echo esc_html( ucfirst( (string) $activity['status'] ) ); ?></small></div>
                  <span>Reservation integration pending</span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
