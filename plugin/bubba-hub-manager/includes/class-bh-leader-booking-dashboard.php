<?php
/**
 * Unified leader booking dashboard. Staging-only front-end composition.
 * Components are read-only or configuration-only; no public reservations.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Booking_Dashboard_3 {
    public static function register() {
        add_shortcode( 'bh_leader_booking_dashboard', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return '<p>Please <a href="' . esc_url( wp_login_url( home_url( '/leader/bookings/' ) ) ) . '">sign in</a> to manage bookings.</p>';
        }
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $owner ) { return '<p>Your account must be linked to an organiser to manage bookings.</p>'; }
        $activity_id = absint( $_GET['activity_id'] ?? 0 );
        global $wpdb;
        if ( $activity_id ) {
            $owned = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}activities WHERE id=%d AND organiser_id=%d LIMIT 1", $activity_id, $owner
            ) );
            if ( ! $owned ) { return '<p>Activity not found in your organiser account.</p>'; }
        }
        $tab = sanitize_key( wp_unslash( $_GET['booking_tab'] ?? 'overview' ) );
        $allowed = array( 'overview', 'calendar', 'reservations', 'setup' );
        if ( ! in_array( $tab, $allowed, true ) ) { $tab = 'overview'; }
        $base = home_url( '/leader/bookings/' );
        if ( $activity_id ) { $base = add_query_arg( 'activity_id', $activity_id, $base ); }
        $counts = class_exists( 'BH_Leader_Booking_Storage_3' )
            ? BH_Leader_Booking_Storage_3::attention_counts( $owner )
            : array( 'pending' => 0, 'waitlisted' => 0 );
        $installed = class_exists( 'BH_Leader_Booking_Storage_3' ) && BH_Leader_Booking_Storage_3::installed();
        $labels = array( 'overview' => 'Overview', 'calendar' => 'Calendar', 'reservations' => 'Reservations', 'setup' => 'Booking settings' );
        ob_start(); ?>
        <section class="bh3-booking-dashboard" aria-labelledby="bh3-booking-dashboard-title">
        <style>
        .bh3-booking-dashboard{max-width:1200px;margin:24px auto;padding:clamp(12px,3vw,26px);color:#1e3c30;font-family:inherit}
        .bh3-booking-dashboard *{box-sizing:border-box}
        .bh3-booking-dashboard .bh3-db-header{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
        .bh3-booking-dashboard h1{font-size:clamp(28px,4vw,40px);margin:5px 0}
        .bh3-booking-dashboard .bh3-db-subtitle{color:#586d60;margin:6px 0 20px}
        .bh3-booking-dashboard .bh3-db-tabs{display:flex;gap:7px;flex-wrap:wrap;margin:20px 0}
        .bh3-booking-dashboard .bh3-db-tabs a{display:inline-flex;padding:11px 16px;border:1px solid #d5e4d9;border-radius:11px;text-decoration:none;color:#225a44;background:#fff;font-weight:600}
        .bh3-booking-dashboard .bh3-db-tabs a[aria-current=page]{background:#195940;color:#fff;border-color:#195940}
        .bh3-booking-dashboard .bh3-db-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}
        .bh3-booking-dashboard .bh3-db-card{padding:19px;border:1px solid #dce8df;border-radius:14px;background:#fff}
        .bh3-booking-dashboard .bh3-db-card strong{display:block;font-size:29px;color:#205d46}
        .bh3-booking-dashboard .bh3-db-note{background:#f0f6f1;border-radius:12px;padding:13px 17px;margin:15px 0}
        .bh3-booking-dashboard .bh3-db-coming{border:1px dashed #bdd1c2;border-radius:14px;padding:18px;margin:16px 0}
        @media(max-width:640px){.bh3-booking-dashboard .bh3-db-cards{grid-template-columns:1fr}.bh3-booking-dashboard .bh3-db-tabs a{flex:1 1 45%;justify-content:center}}
        </style>
        <div class="bh3-db-header">
          <div><a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">← Leader portal</a>
          <h1 id="bh3-booking-dashboard-title">Bookings &amp; Enrolments</h1>
          <p class="bh3-db-subtitle">Your classes, places and booking enquiries in one place.</p></div>
        </div>
        <p class="bh3-db-note">Staging preview: public reservations, payments and automatic reminders are not enabled.<?php if ( ! $installed ) : ?> Booking database installation is still pending.<?php endif; ?></p>
        <?php if ( ! $installed && current_user_can( 'manage_options' ) ) : ?>
          <div class="bh3-db-card" role="region" aria-label="Booking database setup">
            <h2>Connect booking records</h2>
            <p>Administrator setup on staging only. Back up the staging database first. This creates two empty booking tables; it does not import existing bookings or enable public reservations.</p>
            <?php if ( sanitize_key( wp_unslash( $_GET['booking_schema_notice'] ?? '' ) ) === 'failed' ) : ?>
              <p role="alert">The table installation could not be verified. Check the database logs before retrying.</p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <input type="hidden" name="action" value="bh3_booking_install_schema">
              <?php wp_nonce_field( 'bh3_booking_install_schema' ); ?>
              <label for="bh3-booking-install-confirm">Type INSTALL to create the booking tables</label>
              <input id="bh3-booking-install-confirm" name="booking_confirm" required pattern="INSTALL" autocomplete="off">
              <button type="submit">Install booking tables on staging</button>
            </form>
          </div>
        <?php elseif ( $installed && current_user_can( 'manage_options' ) ) : ?>
          <p role="status" class="bh3-db-note">Booking tables are connected. Public booking and payments remain disabled.</p>
        <?php endif; ?>
        <nav class="bh3-db-tabs" aria-label="Booking dashboard sections">
          <?php foreach ( $labels as $key => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'booking_tab', $key, $base ) ); ?>" <?php if ( $tab === $key ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
          <?php endforeach; ?>
        </nav>
        <?php if ( $tab === 'overview' ) : ?>
          <div class="bh3-db-cards">
            <div class="bh3-db-card"><strong><?php echo $installed ? esc_html( (string) $counts['pending'] ) : '—'; ?></strong>Awaiting confirmation</div>
            <div class="bh3-db-card"><strong><?php echo $installed ? esc_html( (string) $counts['waitlisted'] ) : '—'; ?></strong>Waiting list</div>
            <div class="bh3-db-card"><strong>—</strong>Places available (coming soon)</div>
          </div>
          <?php echo BH_Leader_Bookings_3::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component HTML. ?>
          <div class="bh3-db-coming"><strong>Coming next:</strong> attendance, course enrolments, automatic reminders and payment status.</div>
        <?php elseif ( $tab === 'calendar' ) : ?>
          <?php echo BH_Leader_Booking_Calendar_3::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component HTML. ?>
        <?php elseif ( $tab === 'reservations' ) : ?>
          <?php echo BH_Leader_Booking_Queue_3::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component HTML. ?>
        <?php elseif ( $tab === 'setup' ) : ?>
          <?php echo BH_Leader_Booking_Setup_3::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component HTML. ?>
        <?php endif; ?>
        </section>
        <?php return ob_get_clean();
    }
}
