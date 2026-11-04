<?php
/** Staging-only front-end leader dashboard. No data mutations. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_3 {
    public static function register() {
        add_shortcode( 'bh_leader_dashboard', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        if ( ! is_user_logged_in() ) {
            return '<div class="bh-leader-login"><p>Please sign in to access your Leader Dashboard.</p><a class="bh-leader-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Sign in</a></div>';
        }
        if ( ! current_user_can( 'read' ) ) { return '<p>Access denied.</p>'; }
        global $wpdb;
        $organiser_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser_id ) { return '<div class="bh-leader-shell"><h1>Leader Dashboard</h1><p>Your account has not yet been linked to an organiser. Please contact Bubba Hub support.</p></div>'; }
        $organisers = $wpdb->prefix . 'organisers';
        $activities = $wpdb->prefix . 'activities';
        foreach ( array( $organisers, $activities ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return '<p>The Leader Portal is being set up. Please try again later.</p>'; }
        }
        $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$organisers}` WHERE id=%d LIMIT 1", $organiser_id ) );
        if ( ! $name ) { return '<p>We could not find your linked organiser profile.</p>'; }
        $counts = $wpdb->get_results( $wpdb->prepare( "SELECT status,COUNT(*) AS total FROM `{$activities}` WHERE organiser_id=%d GROUP BY status", $organiser_id ), ARRAY_A );
        $totals = array( 'all' => 0, 'draft' => 0, 'published' => 0 );
        foreach ( $counts as $row ) { $totals['all'] += (int) $row['total']; if ( isset( $totals[$row['status']] ) ) { $totals[$row['status']] = (int) $row['total']; } }
        $listings = $wpdb->get_results( $wpdb->prepare( "SELECT title,status,category FROM `{$activities}` WHERE organiser_id=%d ORDER BY id DESC LIMIT 10", $organiser_id ), ARRAY_A );
        ob_start();
        ?>
        <style>
        .bh-leader-shell{max-width:1180px;margin:32px auto;padding:0 20px;color:#203c31;font-family:inherit}
        .bh-leader-shell *{box-sizing:border-box}.bh-leader-head{margin-bottom:26px}
        .bh-leader-head h1{font-size:clamp(28px,4vw,42px);line-height:1.2;margin:0 0 10px}
        .bh-leader-muted{color:#53665e}.bh-leader-stats,.bh-leader-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:20px 0}
        .bh-leader-stat,.bh-leader-card,.bh-leader-list{background:#fff;border:1px solid #dbe5df;border-radius:16px;padding:22px;box-shadow:0 3px 12px rgba(25,58,40,.04)}
        .bh-leader-stat strong{display:block;font-size:30px;color:#245e4a}.bh-leader-card h2{font-size:20px;margin:0 0 10px}
        .bh-leader-card p{line-height:1.55}.bh-leader-button{display:inline-flex;padding:10px 16px;background:#245e4a;color:#fff!important;border-radius:9px;text-decoration:none!important;font-weight:600}
        .bh-leader-list{margin:22px 0}.bh-leader-list h2{font-size:22px}.bh-leader-list ul{list-style:none;padding:0;margin:0}
        .bh-leader-list li{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid #edf0ed}
        .bh-leader-status{text-transform:capitalize;color:#245e4a;font-weight:600}
        @media(max-width:760px){.bh-leader-stats,.bh-leader-grid{grid-template-columns:1fr}.bh-leader-shell{padding:0 14px;margin:20px auto}.bh-leader-stat,.bh-leader-card{padding:18px}}
        </style>
        <main class="bh-leader-shell">
            <header class="bh-leader-head"><h1>Leader Dashboard</h1><p class="bh-leader-muted">Welcome, <?php echo esc_html( $name ); ?>. Manage your Bubba Hub activities.</p></header>
            <section class="bh-leader-stats" aria-label="Listing summary">
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['all'] ) ); ?></strong>Total activities</div>
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['draft'] ) ); ?></strong>Drafts</div>
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['published'] ) ); ?></strong>Published</div>
            </section>
            <section class="bh-leader-grid" aria-label="Leader sections">
                <?php
                $sections = array(
                    array( 'My Activities', 'Review your linked listings and their status.' ),
                    array( 'My Venues', 'Venue management is being prepared for the front end.' ),
                    array( 'My Timetables', 'Session editing is being prepared for the front end.' ),
                    array( 'Bookings', 'Bookings are not yet enabled.' ),
                    array( 'My Business', 'Organiser profile settings are coming next.' ),
                    array( 'Help & Support', 'Leader resources and guidance are being prepared.' ),
                );
                foreach ( $sections as $section ) {
                    echo '<article class="bh-leader-card"><h2>' . esc_html( $section[0] ) . '</h2><p>' . esc_html( $section[1] ) . '</p></article>';
                }
                ?>
            </section>
            <section class="bh-leader-list"><h2>Recent activities</h2>
                <?php if ( ! $listings ) : ?><p>No activities linked to your organiser yet.</p><?php else : ?>
                <ul><?php foreach ( $listings as $listing ) : ?>
                    <li><span><?php echo esc_html( $listing['title'] ); ?></span><span class="bh-leader-status"><?php echo esc_html( $listing['status'] ); ?></span></li>
                <?php endforeach; ?></ul><?php endif; ?>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }
}
