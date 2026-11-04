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
        if ( ! $organiser_id ) { return '<div class="bh-leader-shell"><h1>Your Leader Hub</h1><p>Your account has not yet been linked to an organiser. Please contact Bubba Hub support.</p></div>'; }
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
        $search = sanitize_text_field( wp_unslash( $_GET['bh_leader_search'] ?? '' ) );
        $filter = sanitize_key( wp_unslash( $_GET['bh_leader_status'] ?? '' ) );
        if ( ! in_array( $filter, array( '', 'draft', 'published', 'pending', 'archived' ), true ) ) { $filter = ''; }
        $where = 'organiser_id=%d';
        $args = array( $organiser_id );
        if ( $search !== '' ) { $where .= ' AND title LIKE %s'; $args[] = '%' . $wpdb->esc_like( $search ) . '%'; }
        if ( $filter !== '' ) { $where .= ' AND status=%s'; $args[] = $filter; }
        $page_num = max( 1, absint( $_GET['bh_leader_page'] ?? 1 ) );
        $total_results = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$activities}` WHERE {$where}", $args ) );
        $page_count = max( 1, (int) ceil( $total_results / 15 ) );
        $page_num = min( $page_num, $page_count );
        $listings = $wpdb->get_results( $wpdb->prepare( "SELECT id,title,status,category FROM `{$activities}` WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( 15, ( $page_num - 1 ) * 15 ) ) ), ARRAY_A );
        // Only use verified organiser-owned records. Do not invent booking/message counts.
        $draft_attention = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,title FROM `{$activities}` WHERE organiser_id=%d AND status='draft' ORDER BY id DESC LIMIT 3",
            $organiser_id
        ), ARRAY_A );
        $sessions_table = $wpdb->prefix . 'sessions';
        $session_summary = null;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) ) === $sessions_table ) {
            $session_summary = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` s INNER JOIN `{$activities}` a ON a.id=s.activity_id WHERE a.organiser_id=%d AND a.status='published' AND s.frequency='weekly'",
                $organiser_id
            ) );
        }
        $upcoming = array();
        if ( $session_summary !== null ) {
            $today = new DateTimeImmutable( 'today', wp_timezone() );
            $rules = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.day_of_week,s.start_time,s.start_date,s.end_date,s.term_time_only,a.title FROM `{$sessions_table}` s JOIN `{$activities}` a ON a.id=s.activity_id WHERE a.organiser_id=%d AND a.status='published' AND s.frequency='weekly' LIMIT 250",
                $organiser_id
            ), ARRAY_A );
            foreach ( (array) $rules as $rule ) {
                $day = (int) $rule['day_of_week'];
                if ( $day < 1 || $day > 7 ) continue;
                $date = $today->modify( '+' . ( ( $day - (int) $today->format( 'N' ) + 7 ) % 7 ) . ' days' );
                $ymd = $date->format( 'Y-m-d' );
                if ( ( $rule['start_date'] && $rule['start_date'] > $ymd ) || ( $rule['end_date'] && $rule['end_date'] < $ymd ) ) continue;
                $upcoming[] = array( 'date' => $date, 'time' => $rule['start_time'], 'title' => $rule['title'], 'term' => $rule['term_time_only'] );
            }
            usort( $upcoming, static function ( $a, $b ) { return strcmp( $a['date']->format( 'Ymd' ) . $a['time'], $b['date']->format( 'Ymd' ) . $b['time'] ); } );
            $upcoming = array_slice( $upcoming, 0, 5 );
        }
        ob_start();
        ?>

        <main class="bh-leader-shell">
            <header class="bh-leader-head"><h1>Leader Dashboard</h1><p class="bh-leader-muted">Welcome, <?php echo esc_html( $name ); ?>. Manage your Bubba Hub activities.</p></header>
            <section class="bh-leader-stats" aria-label="Listing summary">
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['all'] ) ); ?></strong>Total activities</div>
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['draft'] ) ); ?></strong>Drafts</div>
                <div class="bh-leader-stat"><strong><?php echo esc_html( number_format_i18n( $totals['published'] ) ); ?></strong>Published</div>
            </section>
            <section class="bh-leader-overview" aria-label="Your next steps">
                <header><h2>Your next steps</h2><p class="bh-leader-muted">Everything important, in one place.</p></header>
                <div class="bh-leader-overview-grid">
                    <article class="bh-leader-overview-card">
                        <h3>Activities to finish</h3>
                        <?php if ( ! $draft_attention ) : ?><p>No drafts waiting for attention.</p>
                        <?php else : ?>
                            <p><?php echo esc_html( number_format_i18n( $totals['draft'] ) ); ?> draft activities</p>
                            <ul><?php foreach ( $draft_attention as $draft ) : ?><li>
                                <a href="<?php echo esc_url( add_query_arg( 'activity_id', absint( $draft['id'] ), home_url( '/leader/edit-activity/' ) ) ); ?>"><?php echo esc_html( $draft['title'] ); ?></a>
                            </li><?php endforeach; ?></ul>
                        <?php endif; ?>
                        <a class="bh-leader-overview-action" href="<?php echo esc_url( home_url( '/leader/statistics/' ) ); ?>">Review listing improvements</a>
                    </article>
                    <article class="bh-leader-overview-card">
                        <h3>Your timetable</h3>
                        <?php if ( $session_summary === null ) : ?>
                            <p>Session information is not available yet.</p>
                        <?php else : ?>
                            <p><?php echo esc_html( number_format_i18n( $session_summary ) ); ?> weekly session records on published activities.</p>
                            <p class="bh-leader-muted">This is not a live count of upcoming classes or cancellations.</p>
                            <h4>Next seven days (planned)</h4>
                            <?php if ( ! $upcoming ) : ?><p>No weekly sessions in the next seven days.</p><?php else : ?>
                            <ul class="bh-leader-upcoming"><?php foreach ( $upcoming as $slot ) : ?>
                              <li><strong><?php echo esc_html( wp_date( 'D j M', $slot['date']->getTimestamp() ) . ' ' . substr( $slot['time'], 0, 5 ) ); ?></strong>
                              <span><?php echo esc_html( $slot['title'] ); ?><?php if ( $slot['term'] ) : ?> (term-time rule)<?php endif; ?></span></li>
                            <?php endforeach; ?></ul><?php endif; ?>
                            <p class="bh-leader-muted">Based on weekly rules only. School holidays and cancellations are not checked.</p>
                        <?php endif; ?>
                        <a class="bh-leader-overview-action" href="<?php echo esc_url( home_url( '/leader/' ) . '#my-activities' ); ?>">Manage activity timetables</a>
                    </article>
                    <article class="bh-leader-overview-card">
                        <h3>Bookings</h3>
                        <p>Booking totals and alerts will appear when reservation records are connected.</p>
                        <a class="bh-leader-overview-action" href="<?php echo esc_url( home_url( '/leader/bookings/' ) ); ?>">Open bookings</a>
                    </article>
                    <article class="bh-leader-overview-card">
                        <h3>Family questions</h3>
                        <p>Specialist messages are managed in Help &amp; Support. No unread count is available yet.</p>
                        <a class="bh-leader-overview-action" href="<?php echo esc_url( home_url( '/leader/support/' ) ); ?>">Open support inbox</a>
                    </article>
                </div>
            </section>
            <section class="bh-leader-list" id="my-activities"><h2>My Activities</h2>
                <p class="bh-leader-muted">Find an activity below to update its details, venues and timetable. Published listings remain read-only until the team enables editing.</p>
                <form class="bh-leader-filters" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
                    <label class="screen-reader-text" for="bh-leader-search">Search activities</label>
                    <input id="bh-leader-search" type="search" name="bh_leader_search" placeholder="Search activities" value="<?php echo esc_attr( $search ); ?>">
                    <label class="screen-reader-text" for="bh-leader-status-filter">Status</label>
                    <select id="bh-leader-status-filter" name="bh_leader_status">
                    <?php foreach ( array( '' => 'All statuses', 'draft' => 'Draft', 'published' => 'Published', 'pending' => 'Pending', 'archived' => 'Archived' ) as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                    </select>
                    <button class="bh-leader-button" type="submit">Search</button>
                    <a href="<?php echo esc_url( get_permalink() . '#my-activities' ); ?>">Clear</a>
                </form>
                <p><?php echo esc_html( number_format_i18n( $total_results ) ); ?> matching activities</p>
                <?php if ( ! $listings ) : ?><p>No matching activities found.</p><?php else : ?>
                <ul><?php foreach ( $listings as $listing ) : ?>
                    <li>
                      <div class="bh-leader-activity-title">
                        <strong><?php echo esc_html( $listing['title'] ); ?></strong>
                        <small class="bh-leader-activity-category"><?php echo esc_html( $listing['category'] ); ?></small>
                      </div>
                      <div class="bh-leader-activity-actions">
                        <span class="bh-leader-state bh-leader-state--<?php echo esc_attr( sanitize_html_class( $listing['status'] ) ); ?>"><?php echo esc_html( $listing['status'] ); ?></span>
                        <?php if ( $listing['status'] === 'draft' ) : ?>
                          <a class="bh-leader-primary-action" href="<?php echo esc_url( add_query_arg( 'activity_id', absint( $listing['id'] ), home_url( '/leader/edit-activity/' ) ) ); ?>">Manage activity</a>
                        <?php else : ?>
                          <span class="bh-leader-muted">Contact the team to request changes</span>
                        <?php endif; ?>
                      </div>
                    </li>
                <?php endforeach; ?></ul><?php endif; ?>
                <?php if ( $page_count > 1 ) : ?>
                    <nav class="bh-leader-pagination" aria-label="Activity pages">
                    <?php if ( $page_num > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( 'bh_leader_page', $page_num - 1 ) . '#my-activities' ); ?>">← Previous</a><?php endif; ?>
                    <span>Page <?php echo esc_html( $page_num ); ?> of <?php echo esc_html( $page_count ); ?></span>
                    <?php if ( $page_num < $page_count ) : ?><a href="<?php echo esc_url( add_query_arg( 'bh_leader_page', $page_num + 1 ) . '#my-activities' ); ?>">Next →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
            <section class="bh-leader-support-overview" id="leader-ask-inbox" aria-label="Ask Support">
                <h2>Family questions &amp; support</h2>
            <?php echo do_shortcode('[bh_leader_ask_inbox]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo do_shortcode('[bh_leader_ask_support]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }
}
