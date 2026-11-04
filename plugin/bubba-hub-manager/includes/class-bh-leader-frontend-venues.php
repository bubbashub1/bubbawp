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
        <style>
        .bh-leader-venues{font-family:inherit;color:#203c31;box-sizing:border-box}.bh-leader-venues *{box-sizing:border-box}
        .bh-leader-venues .bh-vhero{background:linear-gradient(120deg,#1e5a45,#397d61);color:white;padding:26px;border-radius:20px;margin:14px 0 24px}
        .bh-leader-venues .bh-vhero h1{color:#fff;margin:6px 0;font-size:clamp(25px,4vw,36px)}.bh-leader-venues .bh-vhero p{margin:6px 0;color:#f0f8f3}
        .bh-leader-venues .bh-vnav{display:flex;flex-wrap:wrap;gap:9px;margin:12px 0}.bh-leader-venues .bh-vnav a{display:inline-block;padding:9px 13px;border:1px solid #c9ded0;border-radius:99px;color:#245e4a;text-decoration:none;font-weight:650;background:#fff}
        .bh-leader-venues .bh-vpanel{background:#fff;border:1px solid #dbe5df;border-radius:18px;padding:24px;margin:18px 0;box-shadow:0 8px 24px rgba(24,65,44,.05)}
        .bh-leader-venues .bh-vpanel h2{font-size:22px;margin:0 0 8px}.bh-leader-venues .bh-vrow{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;padding:17px 0;border-top:1px solid #edf1ed}
        .bh-leader-venues .bh-vrow strong{font-size:17px}.bh-leader-venues .bh-vbutton{background:#245e4a;color:#fff;border:0;border-radius:10px;padding:11px 17px;cursor:pointer;font:inherit;font-weight:650}
        .bh-leader-venues .bh-vremove{background:#fff;color:#853c3c;border:1px solid #d9bdbd}.bh-leader-venues .bh-vsearch{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin:16px 0}
        .bh-leader-venues .bh-vsearch input{padding:12px 14px;border:1px solid #b5c9bc;border-radius:10px;max-width:100%;width:380px;font:inherit}
        .bh-leader-venues small{display:block;color:#52695d;margin-top:4px}.bh-leader-venues .bh-vnotice{background:#edf7f0;border-left:4px solid #245e4a;padding:12px 16px;border-radius:8px}
        .bh-leader-venues a:focus-visible,.bh-leader-venues button:focus-visible,.bh-leader-venues input:focus-visible{outline:3px solid #245e4a;outline-offset:2px}
        @media(max-width:650px){.bh-leader-venues .bh-vpanel{padding:17px}.bh-leader-venues .bh-vhero{padding:20px}.bh-leader-venues .bh-vsearch input{width:100%}.bh-leader-venues .bh-vsearch button{width:100%}}
        </style>
        
        <header class="bh-vhero"><p>MY VENUES · DRAFT ACTIVITY</p><h1><?php echo esc_html( $activity['title'] ); ?></h1><p>Manage where your sessions take place.</p></header>
        <p>Search existing Bubba Hub venues to avoid duplicates. Venues used by sessions cannot be removed until those sessions are updated.</p>
        <?php if ( isset( $_GET['notice'] ) ) : ?><p class="bh-vnotice" role="status"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ); ?></p><?php endif; ?>
        <div class="bh-vpanel"><h2>Assigned venues <small><?php echo esc_html( count( $assigned ) ); ?> linked</small></h2>
        <?php if ( ! $assigned ) : ?><p>No venues assigned yet.</p><?php endif; ?>
        <?php foreach ( $assigned as $venue ) : ?><div class="bh-vrow"><div><strong><?php echo esc_html( $venue['name'] ); ?></strong><small><?php echo esc_html( $venue['town'] ); ?> · <?php echo esc_html( $venue['sessions_count'] ); ?> sessions</small></div>
        <?php if ( ! (int) $venue['sessions_count'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_venue_detach"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="venue_id" value="<?php echo esc_attr( $venue['id'] ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_venue_' . $id . '_' . $venue['id'] ); ?><button class="bh-vbutton bh-vremove" type="submit">Remove venue</button></form>
        <?php else : ?><small>Used by timetable — cannot remove</small><?php endif; ?></div><?php endforeach; ?></div>
        <div class="bh-vpanel"><h2>Find an existing venue</h2>
        <form method="get" action="<?php echo esc_url( get_permalink() ); ?>"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
        <label for="bh-venue-search">Search by venue name or town</label><div class="bh-vsearch"><input id="bh-venue-search" type="search" name="venue_search" value="<?php echo esc_attr( $term ); ?>" placeholder="e.g. Community Centre" required minlength="2"> <button class="bh-vbutton" type="submit">Find venues</button></div></form>
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
