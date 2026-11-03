<?php
/** Staging-only session editor for draft activities. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Sessions_3 {
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function register() {
        add_submenu_page( null, 'Activity Sessions', 'Activity Sessions', 'manage_options', 'bh3-sessions', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_save_session', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_delete_session', array( __CLASS__, 'remove' ) );
    }
    private static function activity( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id, title, status FROM `{$table}` WHERE id = %d LIMIT 1", $id ), ARRAY_A );
    }
    private static function redirect( $id, $notice ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-sessions', 'activity_id' => $id, 'notice' => $notice ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $id = absint( $_GET['activity_id'] ?? 0 );
        $activity = self::activity( $id );
        if ( ! $activity ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        $sessions = $wpdb->prefix . 'sessions';
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.*, v.name AS venue_name FROM `{$sessions}` s LEFT JOIN `{$venues}` v ON v.id=s.venue_id WHERE s.activity_id=%d ORDER BY s.day_of_week, s.start_time, s.id LIMIT 200", $id ), ARRAY_A );
        echo '<div class="wrap"><h1>Sessions: ' . esc_html( $activity['title'] ) . '</h1>';
        if ( isset( $_GET['notice'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ) . '</p></div>'; }
        echo '<p>Weekly sessions use day and time. Date range is optional. No bookings are created.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Day</th><th>Time</th><th>Venue</th><th>Dates</th><th>Term time</th><th>Action</th></tr></thead><tbody>';
        $days = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $days[(int) $row['day_of_week']] ?? 'Unspecified' ) . '</td><td>' . esc_html( substr( (string) $row['start_time'], 0, 5 ) . '–' . substr( (string) $row['end_time'], 0, 5 ) ) . '</td><td>' . esc_html( (string) ( $row['venue_name'] ?: 'Not set' ) ) . '</td><td>' . esc_html( (string) $row['start_date'] . ' / ' . (string) $row['end_date'] ) . '</td><td>' . ( $row['term_time_only'] ? 'Yes' : 'No' ) . '</td><td>';
            if ( $activity['status'] === 'draft' ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_delete_session"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="session_id" value="' . esc_attr( (string) $row['id'] ) . '">';
                wp_nonce_field( 'bh3_delete_session_' . $id . '_' . $row['id'] );
                submit_button( 'Remove', 'secondary small', '', false );
                echo '</form>';
            } else { echo 'Read-only'; }
            echo '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="6">No sessions yet.</td></tr>'; }
        echo '</tbody></table>';
        if ( $activity['status'] !== 'draft' ) { echo '<p>Only draft activity sessions can be changed.</p></div>'; return; }
        $assigned = $wpdb->get_results( $wpdb->prepare( "SELECT v.id, v.name, v.town FROM `{$venues}` v INNER JOIN `{$links}` av ON av.venue_id=v.id WHERE av.activity_id=%d ORDER BY v.name LIMIT 200", $id ), ARRAY_A );
        echo '<h2>Add weekly session</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_save_session"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_save_session_' . $id );
        echo '<table class="form-table"><tbody><tr><th>Day</th><td><select name="day_of_week" required>';
        foreach ( $days as $num => $name ) { echo '<option value="' . esc_attr( (string) $num ) . '">' . esc_html( $name ) . '</option>'; }
        echo '</select></td></tr><tr><th>Start</th><td><input type="time" name="start_time" required></td></tr><tr><th>End</th><td><input type="time" name="end_time" required></td></tr>';
        echo '<tr><th>Venue</th><td><select name="venue_id"><option value="0">No venue</option>';
        foreach ( $assigned as $venue ) { echo '<option value="' . esc_attr( (string) $venue['id'] ) . '">' . esc_html( $venue['name'] . ( $venue['town'] ? ' — ' . $venue['town'] : '' ) ) . '</option>'; }
        echo '</select> <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activity-venues', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Assign venues</a></td></tr>';
        echo '<tr><th>First date (optional)</th><td><input type="date" name="start_date"></td></tr><tr><th>Last date (optional)</th><td><input type="date" name="end_date"></td></tr>';
        echo '<tr><th>Term time only</th><td><label><input type="checkbox" name="term_time_only" value="1"> Yes</label></td></tr></tbody></table>';
        submit_button( 'Add session' );
        echo '</form></div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_save_session_' . $id );
        $activity = self::activity( $id );
        if ( ! $activity || $activity['status'] !== 'draft' ) { wp_die( 'Draft activity required.', '', array( 'response' => 409 ) ); }
        $day = filter_var( wp_unslash( $_POST['day_of_week'] ?? '' ), FILTER_VALIDATE_INT );
        $start = (string) wp_unslash( $_POST['start_time'] ?? '' );
        $end = (string) wp_unslash( $_POST['end_time'] ?? '' );
        if ( $day === false || $day < 1 || $day > 7 || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end ) || $end <= $start ) {
            wp_die( 'Invalid day or session times.', '', array( 'response' => 400 ) );
        }
        $dates = array();
        foreach ( array( 'start_date', 'end_date' ) as $field ) {
            $value = (string) wp_unslash( $_POST[$field] ?? '' );
            $date = $value ? DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : null;
            if ( $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) { wp_die( 'Invalid session date.', '', array( 'response' => 400 ) ); }
            $dates[$field] = $value ?: null;
        }
        if ( $dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date'] ) { wp_die( 'End date precedes start date.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $venue_id = absint( $_POST['venue_id'] ?? 0 );
        if ( $venue_id ) {
            $links = $wpdb->prefix . 'activity_venues';
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$links}` WHERE activity_id=%d AND venue_id=%d", $id, $venue_id ) ) ) {
                wp_die( 'Venue must be assigned to this activity first.', '', array( 'response' => 400 ) );
            }
        }
        $table = $wpdb->prefix . 'sessions';
        $now = current_time( 'mysql', true );
        $result = $wpdb->insert( $table, array(
            'activity_id' => $id, 'venue_id' => $venue_id ?: null, 'day_of_week' => $day,
            'start_time' => $start . ':00', 'end_time' => $end . ':00',
            'start_date' => $dates['start_date'], 'end_date' => $dates['end_date'],
            'frequency' => 'weekly', 'term_time_only' => empty( $_POST['term_time_only'] ) ? 0 : 1,
            'status' => 'scheduled', 'created_at' => $now, 'updated_at' => $now
        ) );
        if ( $result === false ) { wp_die( 'Unable to save session.', '', array( 'response' => 500 ) ); }
        self::redirect( $id, 'Session added.' );
    }
    public static function remove() {
        if ( ! self::allowed() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $session_id = absint( $_POST['session_id'] ?? 0 );
        check_admin_referer( 'bh3_delete_session_' . $id . '_' . $session_id );
        $activity = self::activity( $id );
        if ( ! $activity || $activity['status'] !== 'draft' ) { wp_die( 'Draft activity required.', '', array( 'response' => 409 ) ); }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        $deleted = $wpdb->delete( $table, array( 'id' => $session_id, 'activity_id' => $id ), array( '%d', '%d' ) );
        if ( $deleted === false ) { wp_die( 'Unable to remove session.', '', array( 'response' => 500 ) ); }
        self::redirect( $id, 'Session removed.' );
    }
}
