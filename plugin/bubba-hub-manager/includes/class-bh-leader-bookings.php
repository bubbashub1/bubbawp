<?php
/**
 * Leader booking foundation: read-only staging preview.
 * No reservations, schema mutations or emails are triggered.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Bookings_3 {
    public static function register() {
        add_shortcode( 'bh_leader_bookings', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to manage bookings.</p>';
        }
        $organiser_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser_id ) { return '<p>Your leader account must be linked to an organiser before bookings can be managed.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return '<p>Activity records are not available yet.</p>';
        }
        $activities = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,title,status FROM `{$table}` WHERE organiser_id=%d ORDER BY title ASC LIMIT 200",
            $organiser_id
        ), ARRAY_A );
        ob_start(); ?>
        <section class="bh-bookings" aria-labelledby="bh-bookings-title">
          <style>
            .bh-bookings{max-width:1160px;margin:24px auto;padding:24px;color:#243e33;font-family:inherit}
            .bh-bookings *{box-sizing:border-box}
            .bh-bookings h1{font-size:clamp(28px,4vw,40px);margin:0 0 8px}
            .bh-bookings .bh-bookings-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:24px 0}
            .bh-bookings .bh-bookings-card{border:1px solid #d9e6dc;border-radius:16px;background:#fff;padding:22px}
            .bh-bookings .bh-bookings-card strong{font-size:28px;display:block;color:#245e4a}
            .bh-bookings table{width:100%;border-collapse:collapse;background:#fff}
            .bh-bookings th,.bh-bookings td{padding:13px;text-align:left;border-bottom:1px solid #e5ede7}
            .bh-bookings .bh-bookings-note{padding:14px 18px;border-radius:10px;background:#f1f6f2}
            @media(max-width:680px){.bh-bookings{padding:12px}.bh-bookings .bh-bookings-grid{grid-template-columns:1fr}.bh-bookings table{display:block;overflow-x:auto}}
          </style>
          <p><a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">← Leader dashboard</a></p>
          <h1 id="bh-bookings-title">Bookings &amp; Enrolments</h1>
          <p>Manage your class places and enrolments in one place.</p>
          <p class="bh-bookings-note" role="status">Staging preview only. Booking creation, payments, reminders and enrolment changes are not yet enabled.</p>
          <div class="bh-bookings-grid" aria-label="Booking overview">
            <div class="bh-bookings-card"><strong>—</strong>Pending reservations</div>
            <div class="bh-bookings-card"><strong>—</strong>Confirmed places</div>
            <div class="bh-bookings-card"><strong>—</strong>Waiting list</div>
          </div>
          <div class="bh-bookings-card">
            <h2>Your activities</h2>
            <?php if ( ! $activities ) : ?>
              <p>No linked activities found.</p>
            <?php else : ?>
              <table><thead><tr><th scope="col">Activity</th><th scope="col">Status</th><th scope="col">Bookings</th></tr></thead><tbody>
              <?php foreach ( $activities as $activity ) : ?>
                <tr><td><?php echo esc_html( $activity['title'] ); ?></td><td><?php echo esc_html( ucfirst( $activity['status'] ) ); ?></td><td>Not yet enabled</td></tr>
              <?php endforeach; ?>
              </tbody></table>
            <?php endif; ?>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
