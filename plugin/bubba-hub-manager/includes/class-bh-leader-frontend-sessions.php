<?php
/** Staging-only front-end weekly timetable editor for organiser-owned drafts. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_Sessions_3 {
    private static function enabled() {
        return is_user_logged_in() && current_user_can( 'read' ) &&
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function activity( $id ) {
        if ( ! self::enabled() || ! $id ) { return null; }
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $owner ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,title FROM `{$table}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $id, $owner ), ARRAY_A );
    }
    public static function register() {
        add_shortcode( 'bh_leader_timetable', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_frontend_session_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_frontend_session_delete', array( __CLASS__, 'delete' ) );
    }
    private static function back( $id, $saved = '' ) {
        $url = add_query_arg( array( 'activity_id' => $id ), home_url( '/leader/timetable/' ) );
        if ( $saved ) { $url = add_query_arg( 'notice', $saved, $url ); }
        wp_safe_redirect( $url ); exit;
    }
    public static function render() {
        $id = absint( $_GET['activity_id'] ?? 0 );
        $activity = self::activity( $id );
        if ( ! $activity ) { return '<p>Timetable unavailable. Sign in and choose one of your draft activities.</p>'; }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.id,s.day_of_week,s.start_time,s.end_time,s.venue_id,s.term_time_only,s.start_date,s.end_date,s.frequency,v.name AS venue_name FROM `{$sessions}` s LEFT JOIN `{$venues}` v ON v.id=s.venue_id WHERE s.activity_id=%d ORDER BY s.day_of_week,s.start_time,s.id LIMIT 200", $id ), ARRAY_A );
        $assigned = $wpdb->get_results( $wpdb->prepare( "SELECT v.id,v.name,v.town FROM `{$venues}` v INNER JOIN `{$links}` av ON av.venue_id=v.id WHERE av.activity_id=%d ORDER BY v.name LIMIT 200", $id ), ARRAY_A );
        $edit_id = absint( $_GET['edit_session'] ?? 0 );
        $editing = null;
        foreach ( $rows as $row ) { if ( (int) $row['id'] === $edit_id && $row['frequency'] === 'weekly' ) { $editing = $row; } }
        $days = array( 1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday' );
        ob_start(); ?>
        <section class="bh-leader-timetable" style="max-width:950px;margin:30px auto;padding:20px">
        <style>
        .bh-leader-timetable{font-family:inherit;color:#203c31;box-sizing:border-box}.bh-leader-timetable *{box-sizing:border-box}
        .bh-leader-timetable .bh-panel{background:#fff;border:1px solid #dbe5df;border-radius:18px;padding:24px;margin:20px 0;box-shadow:0 8px 24px rgba(24,65,44,.05)}
        .bh-leader-timetable .bh-panel h2{font-size:23px;margin:0 0 14px}
        .bh-leader-timetable .bh-thero{background:linear-gradient(120deg,#1e5a45,#397d61);color:#fff;padding:26px;border-radius:20px;margin:14px 0 24px}
        .bh-leader-timetable .bh-thero h1{font-size:clamp(25px,4vw,36px);color:#fff;margin:6px 0}.bh-leader-timetable .bh-thero p{color:#f0f8f3;margin:6px 0}
        .bh-leader-timetable .bh-tnav{display:flex;flex-wrap:wrap;gap:9px;margin:12px 0}
        .bh-leader-timetable .bh-tnav a{display:inline-block;padding:9px 13px;border:1px solid #c9ded0;border-radius:99px;color:#245e4a;text-decoration:none;font-weight:650;background:#fff}
        .bh-leader-timetable label{display:block;font-weight:650;margin:14px 0 6px}
        .bh-leader-timetable input:not([type=checkbox]),.bh-leader-timetable select{width:100%;max-width:100%;min-height:45px;padding:10px 12px;border:1px solid #b9cfc1;border-radius:10px;font:inherit}
        .bh-leader-timetable button,.bh-leader-timetable .bh-action{background:#245e4a;color:#fff;border:0;border-radius:10px;padding:10px 16px;cursor:pointer;text-decoration:none;display:inline-block;font:inherit;font-weight:650}
        .bh-leader-timetable .bh-delete{background:#fff;color:#853c3c;border:1px solid #d9bdbd}
        .bh-leader-timetable ul{list-style:none;padding:0;margin:0}.bh-leader-timetable li{padding:16px 0;border-bottom:1px solid #e5eae6}
        .bh-leader-timetable li strong{display:block;font-size:18px}.bh-leader-timetable li span{display:block;color:#53665e;margin-top:4px}
        .bh-leader-timetable .bh-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .bh-leader-timetable .bh-session-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}
        .bh-leader-timetable .bh-session-grid>div{min-width:0}.bh-leader-timetable .bh-session-grid .bh-wide{grid-column:1/-1}
        .bh-leader-timetable .bh-tnotice{padding:12px 16px;background:#edf7f0;border-left:4px solid #245e4a;border-radius:8px}
        .bh-leader-timetable .bh-thint{color:#53665e;font-size:14px}
        .bh-leader-timetable a:focus-visible,.bh-leader-timetable button:focus-visible,.bh-leader-timetable input:focus-visible,.bh-leader-timetable select:focus-visible{outline:3px solid #245e4a;outline-offset:2px}
        @media(max-width:650px){.bh-leader-timetable{padding:12px!important}.bh-leader-timetable .bh-panel{padding:17px}.bh-leader-timetable .bh-session-grid{grid-template-columns:1fr}.bh-leader-timetable .bh-thero{padding:20px}}
        </style>
        
        <header class="bh-thero"><p>MY TIMETABLE · DRAFT ACTIVITY</p><h1><?php echo esc_html( $activity['title'] ); ?></h1><p>Plan weekly sessions and keep your schedule accurate.</p></header>
        <p class="bh-thint">These are timetable entries, not bookings. Times follow your WordPress site's time format.</p>
        <?php if ( isset( $_GET['notice'] ) ) : ?><p class="bh-tnotice" role="status"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ); ?></p><?php endif; ?>
        <div class="bh-panel"><h2>Current sessions (<?php echo esc_html( count( $rows ) ); ?>)</h2>
        <?php if ( ! $rows ) : ?><p>No sessions yet.</p><?php else : ?><ul>
        <?php foreach ( $rows as $row ) : ?><li>
        <strong><?php echo esc_html( ( $days[(int) $row['day_of_week']] ?? 'Other' ) . ' ' . substr( $row['start_time'], 0, 5 ) . '–' . substr( $row['end_time'], 0, 5 ) ); ?></strong>
        <span><?php echo esc_html( $row['venue_name'] ?: 'No venue' ); ?></span>
        <?php if ( $row['frequency'] === 'weekly' ) : ?>
        <div class="bh-actions"><a class="bh-action" href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>$id,'edit_session'=>(int) $row['id'] ), home_url( '/leader/timetable/' ) ) ); ?>">Edit</a>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_session_delete"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="session_id" value="<?php echo esc_attr( $row['id'] ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_delete_' . $id . '_' . $row['id'] ); ?>
        <button class="bh-delete" type="submit">Remove</button></form></div>
        <?php else : ?><small>Read-only non-weekly session</small><?php endif; ?>
        </li><?php endforeach; ?></ul><?php endif; ?></div>
        <div class="bh-panel" id="bh-session-editor"><h2><?php echo $editing ? 'Edit weekly session' : 'Add weekly session'; ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_session_save"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="session_id" value="<?php echo esc_attr( $editing['id'] ?? 0 ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_session_' . $id ); ?>
        <div class="bh-session-grid"><div class="bh-wide"><label for="bh-day">Day</label><select id="bh-day" name="day_of_week" required><?php foreach ( $days as $num=>$day ) : ?><option value="<?php echo esc_attr( $num ); ?>" <?php selected( (int) ( $editing['day_of_week'] ?? 1 ), $num ); ?>><?php echo esc_html( $day ); ?></option><?php endforeach; ?></select></div>
        <div><label for="bh-start">Start time</label><input id="bh-start" type="time" name="start_time" value="<?php echo esc_attr( $editing ? substr( $editing['start_time'], 0, 5 ) : '' ); ?>" required></div>
        <div><label for="bh-end">End time</label><input id="bh-end" type="time" name="end_time" value="<?php echo esc_attr( $editing ? substr( $editing['end_time'], 0, 5 ) : '' ); ?>" required></div>
        <div class="bh-wide"><label for="bh-venue">Venue</label><select id="bh-venue" name="venue_id"><option value="0">No venue</option><?php foreach ( $assigned as $venue ) : ?><option value="<?php echo esc_attr( $venue['id'] ); ?>" <?php selected( (int) ( $editing['venue_id'] ?? 0 ), (int) $venue['id'] ); ?>><?php echo esc_html( $venue['name'] . ( $venue['town'] ? ' — ' . $venue['town'] : '' ) ); ?></option><?php endforeach; ?></select></div>
        <div><label for="bh-first">First date (optional)</label><input id="bh-first" type="date" name="start_date" value="<?php echo esc_attr( $editing['start_date'] ?? '' ); ?>">
        </div><div><label for="bh-last">Last date (optional)</label><input id="bh-last" type="date" name="end_date" value="<?php echo esc_attr( $editing['end_date'] ?? '' ); ?>">
        </div></div>
        <p><label><input type="checkbox" name="term_time_only" value="1" <?php checked( (int) ( $editing['term_time_only'] ?? 0 ), 1 ); ?>> Term time only</label></p>
        <?php if ( ! $assigned ) : ?><p class="bh-thint">No venues linked yet. <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/venues/' ) ) ); ?>">Add a venue</a> before assigning a location to sessions.</p><?php endif; ?>
        <button type="submit"><?php echo $editing ? 'Save session' : 'Add session'; ?></button>
        <?php if ( $editing ) : ?><a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/timetable/' ) ) ); ?>">Cancel editing</a><?php endif; ?>
        </form></div></section>
        <?php return ob_get_clean();
    }
    public static function save() {
        if ( ! self::enabled() ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_frontend_session_' . $id );
        if ( ! self::activity( $id ) ) { wp_die( 'Draft not found.', '', array( 'response'=>403 ) ); }
        $day = filter_var( wp_unslash( $_POST['day_of_week'] ?? '' ), FILTER_VALIDATE_INT );
        $start = (string) wp_unslash( $_POST['start_time'] ?? '' );
        $end = (string) wp_unslash( $_POST['end_time'] ?? '' );
        if ( $day === false || $day < 1 || $day > 7 || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end ) || $end <= $start ) { wp_die( 'Invalid day or times.', '', array( 'response'=>400 ) ); }
        $dates = array();
        foreach ( array( 'start_date', 'end_date' ) as $field ) {
            $value = (string) wp_unslash( $_POST[$field] ?? '' );
            $date = $value ? DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : null;
            if ( $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) { wp_die( 'Invalid date.', '', array( 'response'=>400 ) ); }
            $dates[$field] = $value ?: null;
        }
        if ( $dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date'] ) { wp_die( 'Invalid date range.', '', array( 'response'=>400 ) ); }
        global $wpdb;
        $venue = absint( $_POST['venue_id'] ?? 0 );
        if ( $venue ) {
            $links = $wpdb->prefix . 'activity_venues';
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$links}` WHERE activity_id=%d AND venue_id=%d LIMIT 1", $id, $venue ) ) ) { wp_die( 'Venue not assigned.', '', array( 'response'=>400 ) ); }
        }
        $table = $wpdb->prefix . 'sessions';
        $session = absint( $_POST['session_id'] ?? 0 );
        if ( $session && $wpdb->get_var( $wpdb->prepare( "SELECT frequency FROM `{$table}` WHERE id=%d AND activity_id=%d", $session, $id ) ) !== 'weekly' ) { wp_die( 'Session not editable.', '', array( 'response'=>403 ) ); }
        $now = current_time( 'mysql', true );
        $data = array( 'activity_id'=>$id, 'venue_id'=>$venue ?: null, 'day_of_week'=>$day, 'start_time'=>$start . ':00', 'end_time'=>$end . ':00', 'start_date'=>$dates['start_date'], 'end_date'=>$dates['end_date'], 'frequency'=>'weekly', 'term_time_only'=>empty( $_POST['term_time_only'] ) ? 0 : 1, 'updated_at'=>$now );
        if ( ! self::activity( $id ) ) { wp_die( 'Ownership changed.', '', array( 'response'=>403 ) ); }
        if ( $session ) { $result = $wpdb->update( $table, $data, array( 'id'=>$session, 'activity_id'=>$id ) ); }
        else { $data['status']='scheduled'; $data['created_at']=$now; $result=$wpdb->insert( $table, $data ); }
        if ( $result === false ) { wp_die( 'Could not save session.', '', array( 'response'=>500 ) ); }
        self::back( $id, 'Session saved.' );
    }
    public static function delete() {
        if ( ! self::enabled() ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $session = absint( $_POST['session_id'] ?? 0 );
        check_admin_referer( 'bh3_frontend_delete_' . $id . '_' . $session );
        if ( ! self::activity( $id ) || ! $session ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        $result = $wpdb->delete( $table, array( 'id'=>$session, 'activity_id'=>$id, 'frequency'=>'weekly' ) );
        if ( $result === false ) { wp_die( 'Could not remove session.', '', array( 'response'=>500 ) ); }
        self::back( $id, 'Session removed.' );
    }
}
