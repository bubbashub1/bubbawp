<?php
/** Staging-only, read-only leader statistics sourced from owned activities. */
defined( 'ABSPATH' ) || exit;
final class BH_Leader_Statistics_3 {
    public static function register() {
        add_shortcode( 'bh_leader_statistics', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) return '';
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) return '<p>Please sign in to view your statistics.</p>';
        $organiser_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser_id ) return '<p>Your account is not linked to an organiser.</p>';
        global $wpdb;
        $organisers = $wpdb->prefix . 'organisers';
        $activities = $wpdb->prefix . 'activities';
        foreach ( array( $organisers, $activities ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return '<p>Statistics are being set up. Please try again later.</p>';
            }
        }
        $valid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$organisers}` WHERE id=%d LIMIT 1", $organiser_id ) );
        if ( ! $valid ) return '<p>Your linked organiser profile could not be verified.</p>';
        $counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) AS total FROM `{$activities}` WHERE organiser_id=%d GROUP BY status", $organiser_id
        ), ARRAY_A );
        $total = 0; $published = 0; $drafts = 0;
        foreach ( (array) $counts as $row ) {
            $n = (int) $row['total']; $total += $n;
            if ( $row['status'] === 'published' ) $published += $n;
            if ( $row['status'] === 'draft' ) $drafts += $n;
        }
        $recent = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,title,status,category FROM `{$activities}` WHERE organiser_id=%d ORDER BY id DESC LIMIT 8", $organiser_id
        ), ARRAY_A );
        ob_start(); ?>
        <section class="bh-leader-statistics">
          <header class="bh-leader-statistics-hero"><p>LEADER HUB</p><h1>Your statistics</h1><p>A quick overview of your listings, without extra admin.</p></header>
          <div class="bh-leader-statistics-grid" aria-label="Listing totals">
            <div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span>Total activities</span></div>
            <div><strong><?php echo esc_html( number_format_i18n( $published ) ); ?></strong><span>Published</span></div>
            <div><strong><?php echo esc_html( number_format_i18n( $drafts ) ); ?></strong><span>Drafts</span></div>
          </div>
          <div class="bh-leader-statistics-panel">
            <h2>Recent activities</h2>
            <?php if ( ! $recent ) : ?><p>No activities are linked to your organiser yet.</p>
            <?php else : ?><ul>
              <?php foreach ( $recent as $item ) : ?><li>
                <div><strong><?php echo esc_html( $item['title'] ); ?></strong><small><?php echo esc_html( ucfirst( (string) $item['status'] ) ); ?><?php if ( $item['category'] ) : ?> · <?php echo esc_html( $item['category'] ); ?><?php endif; ?></small></div>
                <?php if ( $item['status'] === 'draft' ) : ?>
                  <a href="<?php echo esc_url( add_query_arg( 'activity_id', absint( $item['id'] ), home_url( '/leader/edit-activity/' ) ) ); ?>">Manage</a>
                <?php endif; ?>
              </li><?php endforeach; ?>
            </ul><?php endif; ?>
            <a class="bh-leader-statistics-action" href="<?php echo esc_url( home_url( '/leader/' ) . '#my-activities' ); ?>">View all activities</a>
          </div>
          <p class="bh-leader-statistics-note">Activity views, saves, bookings and revenue are not shown until their data sources have been verified. No tracking is enabled by this page.</p>
        </section>
        <?php return (string) ob_get_clean();
    }
}
