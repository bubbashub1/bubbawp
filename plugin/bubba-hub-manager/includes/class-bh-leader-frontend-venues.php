<?php
/** Front-end venue assignment for organiser-owned draft activities (staging only). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_Venues_3 {
    private static function allowed() {
        return is_user_logged_in() && current_user_can( 'read' ) &&
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function activity( $id ) {
        if ( ! self::allowed() || ! $id ) { return null; }
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $owner ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,title FROM `{$table}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $id, $owner ), ARRAY_A );
    }
    public static function register() {
        add_shortcode( 'bh_leader_venues', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_frontend_venue_attach', array( __CLASS__, 'attach' ) );
        add_action( 'admin_post_bh3_frontend_venue_detach', array( __CLASS__, 'detach' ) );
    }
    private static function back( $id, $notice ) {
        wp_safe_redirect( add_query_arg( array( 'activity_id'=>$id, 'notice'=>$notice ), home_url( '/leader/venues/' ) ) );
        exit;
    }
    public static function render() {
        $id = absint( $_GET['activity_id'] ?? 0 );
        $activity = self::activity( $id );
        if ( ! $activity ) { return '<p>Please select one of your draft activities to manage its venues.</p>'; }
        global $wpdb;
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        $assigned = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.id,v.name,v.town,(SELECT COUNT(*) FROM `{$wpdb->prefix}sessions` s WHERE s.activity_id=%d AND s.venue_id=v.id) AS sessions_count FROM `{$venues}` v INNER JOIN `{$links}` av ON av.venue_id=v.id WHERE av.activity_id=%d ORDER BY v.name LIMIT 100",
            $id, $id
        ), ARRAY_A );
        $term = substr( sanitize_text_field( wp_unslash( $_GET['venue_search'] ?? '' ) ), 0, 100 );
        $matches = array();
        if ( strlen( trim( $term ) ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $term ) . '%';
            $matches = $wpdb->get_results( $wpdb->prepare(
                "SELECT v.id,v.name,v.town FROM `{$venues}` v WHERE (v.name LIKE %s OR v.town LIKE %s) AND NOT EXISTS (SELECT 1 FROM `{$links}` av WHERE av.activity_id=%d AND av.venue_id=v.id) ORDER BY v.name LIMIT 30",
                $like, $like, $id
            ), ARRAY_A );
        }
        ob_start(); ?>
        <section class="bh-leader-venues" style="max-width:1000px;margin:32px auto;padding:0 18px">
        <style>.bh-leader-venues{font-family:inherit;color:#203c31}.bh-leader-venues .bh-vpanel{background:#fff;border:1px solid #dbe5df;border-radius:15px;padding:22px;margin:18px 0}.bh-leader-venues .bh-vrow{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 0;border-top:1px solid #e5eae6}.bh-leader-venues .bh-vbutton{background:#245e4a;color:#fff;border:0;border-radius:8px;padding:10px 16px;cursor:pointer}.bh-leader-venues input[type=search]{padding:10px;border:1px solid #b5c9bc;border-radius:8px;max-width:100%;width:360px}.bh-leader-venues small{display:block;color:#52695d}</style>
        <p><a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">← Leader Dashboard</a> · <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/timetable/' ) ) ); ?>">Timetable</a></p>
        <h1>Venues for <?php echo esc_html( $activity['title'] ); ?></h1>
        <p>Choose an existing venue before creating another. Only venues for your own draft activity can be assigned.</p>
        <?php if ( isset( $_GET['notice'] ) ) : ?><p role="status"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ); ?></p><?php endif; ?>
        <div class="bh-vpanel"><h2>Assigned venues</h2>
        <?php if ( ! $assigned ) : ?><p>No venues assigned yet.</p><?php endif; ?>
        <?php foreach ( $assigned as $venue ) : ?><div class="bh-vrow"><div><strong><?php echo esc_html( $venue['name'] ); ?></strong><small><?php echo esc_html( $venue['town'] ); ?> · <?php echo esc_html( $venue['sessions_count'] ); ?> sessions</small></div>
        <?php if ( ! (int) $venue['sessions_count'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_venue_detach"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="venue_id" value="<?php echo esc_attr( $venue['id'] ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_venue_' . $id . '_' . $venue['id'] ); ?><button class="bh-vbutton" type="submit">Remove</button></form>
        <?php else : ?><small>Used by timetable — cannot remove</small><?php endif; ?></div><?php endforeach; ?></div>
        <div class="bh-vpanel"><h2>Find an existing venue</h2>
        <form method="get" action="<?php echo esc_url( get_permalink() ); ?>"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
        <label for="bh-venue-search">Search by venue name or town</label><p><input id="bh-venue-search" type="search" name="venue_search" value="<?php echo esc_attr( $term ); ?>" placeholder="e.g. Community Centre" required minlength="2"> <button class="bh-vbutton" type="submit">Search</button></p></form>
        <?php if ( strlen( trim( $term ) ) >= 2 && ! $matches ) : ?><p>No unassigned venues match your search.</p><?php endif; ?>
        <?php foreach ( $matches as $venue ) : ?><div class="bh-vrow"><div><strong><?php echo esc_html( $venue['name'] ); ?></strong><small><?php echo esc_html( $venue['town'] ); ?></small></div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_venue_attach"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="venue_id" value="<?php echo esc_attr( $venue['id'] ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_venue_' . $id . '_' . $venue['id'] ); ?><button class="bh-vbutton" type="submit">Assign venue</button></form></div><?php endforeach; ?>
        </div></section>
        <?php return ob_get_clean();
    }
    private static function change( $attach ) {
        if ( ! self::allowed() ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $venue = absint( $_POST['venue_id'] ?? 0 );
        check_admin_referer( 'bh3_frontend_venue_' . $id . '_' . $venue );
        if ( ! $venue || ! self::activity( $id ) ) { wp_die( 'Draft or venue unavailable.', '', array( 'response'=>403 ) ); }
        global $wpdb;
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$venues}` WHERE id=%d", $venue ) ) ) { wp_die( 'Venue not found.', '', array( 'response'=>404 ) ); }
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$links}` WHERE activity_id=%d AND venue_id=%d", $id, $venue ) );
        if ( $attach ) {
            if ( $existing ) { self::back( $id, 'Venue already assigned.' ); }
            $result = $wpdb->insert( $links, array( 'activity_id'=>$id, 'venue_id'=>$venue ), array( '%d','%d' ) );
        } else {
            $sessions = $wpdb->prefix . 'sessions';
            if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$sessions}` WHERE activity_id=%d AND venue_id=%d", $id, $venue ) ) ) { wp_die( 'Venue is used by a session.', '', array( 'response'=>409 ) ); }
            $result = $wpdb->delete( $links, array( 'activity_id'=>$id, 'venue_id'=>$venue ), array( '%d','%d' ) );
        }
        if ( $result === false ) { wp_die( 'Venue assignment failed.', '', array( 'response'=>500 ) ); }
        self::back( $id, $attach ? 'Venue assigned.' : 'Venue removed.' );
    }
    public static function attach() { self::change( true ); }
    public static function detach() { self::change( false ); }
}
