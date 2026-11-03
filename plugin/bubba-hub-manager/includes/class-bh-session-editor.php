<?php
/**
 * Guarded existing-session timetable editor.
 * Updates only an existing session belonging to the requested activity.
 * No creates, deletes, venue moves or schema changes.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Session_Editor_3 {
    public static function register() {
        add_action( 'admin_post_bh3_update_session', array( __CLASS__, 'save' ) );
    }
    public static function forms( $activity_id, $sessions ) {
        if ( ! BH_Activity_Editor_3::enabled() || ! current_user_can( 'manage_options' ) ) { return; }
        echo '<h2>Edit existing weekly sessions</h2><p>Session IDs and venues are fixed. No sessions will be added or deleted.</p>';
        foreach ( $sessions as $session ) {
            $id = absint( $session['id'] );
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="padding:12px 0;border-bottom:1px solid #ddd">';
            echo '<input type="hidden" name="action" value="bh3_update_session"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $activity_id ) . '"><input type="hidden" name="session_id" value="' . esc_attr( (string) $id ) . '">';
            wp_nonce_field( 'bh3_update_session_' . $id );
            echo '<strong>' . esc_html( $session['venue_name'] ) . ' — #' . esc_html( (string) $id ) . '</strong> ';
            echo '<label>Day <select name="day_of_week">';
            foreach ( array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' ) as $day => $label ) {
                echo '<option value="' . esc_attr( (string) $day ) . '"' . selected( (int) $session['day_of_week'], $day, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></label> ';
            foreach ( array( 'start_time' => 'Start', 'end_time' => 'End' ) as $field => $label ) {
                echo '<label>' . esc_html( $label ) . ' <input type="time" name="' . esc_attr( $field ) . '" value="' . esc_attr( substr( (string) ( $session[ $field ] ?? '' ), 0, 5 ) ) . '"' . ( 'start_time' === $field ? ' required' : '' ) . '></label> ';
            }
            echo '<label>Price (£) <input type="number" min="0" max="99999999.99" step="0.01" name="price" value="' . esc_attr( (string) ( $session['price'] ?? '' ) ) . '"></label> ';
            echo '<label><input type="checkbox" name="term_time_only" value="1"' . checked( ! empty( $session['term_time_only'] ), true, false ) . '> Term time only</label> ';
            submit_button( 'Save session', 'secondary', '', false );
            echo '</form>';
        }
    }
    public static function save() {
        if ( ! BH_Activity_Editor_3::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Editing disabled.', '', array( 'response' => 403 ) ); }
        $id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
        $activity_id = isset( $_POST['activity_id'] ) ? absint( wp_unslash( $_POST['activity_id'] ) ) : 0;
        if ( ! $id || ! $activity_id ) { wp_die( 'Invalid session.', '', array( 'response' => 400 ) ); }
        check_admin_referer( 'bh3_update_session_' . $id );
        $day = isset( $_POST['day_of_week'] ) ? absint( wp_unslash( $_POST['day_of_week'] ) ) : 0;
        $start = isset( $_POST['start_time'] ) && is_string( $_POST['start_time'] ) ? wp_unslash( $_POST['start_time'] ) : '';
        $end = isset( $_POST['end_time'] ) && is_string( $_POST['end_time'] ) ? wp_unslash( $_POST['end_time'] ) : '';
        $price = isset( $_POST['price'] ) && is_string( $_POST['price'] ) ? trim( wp_unslash( $_POST['price'] ) ) : '';
        $valid_time = static function ( $time ) {
            return (bool) preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
        };
        if ( $day < 1 || $day > 7 || ! $valid_time( $start ) || ( '' !== $end && ( ! $valid_time( $end ) || $end <= $start ) ) ) {
            wp_die( 'Invalid day or time range.', '', array( 'response' => 400 ) );
        }
        if ( '' !== $price && ! preg_match( '/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $price ) ) {
            wp_die( 'Invalid session price.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT s.id FROM bh_sessions s INNER JOIN bh_venues v ON v.id = s.venue_id INNER JOIN bh_activities a ON a.id = v.activity_id WHERE s.id = %d AND a.id = %d AND a.status <> %s LIMIT 1',
            $id, $activity_id, 'archived'
        ) );
        if ( ! $row ) { wp_die( 'Session not found or not editable.', '', array( 'response' => 404 ) ); }
        $updated = $wpdb->update( 'bh_sessions', array(
            'day_of_week' => $day,
            'start_time' => $start . ':00',
            'end_time' => '' === $end ? null : $end . ':00',
            'price' => '' === $price ? null : number_format( (float) $price, 2, '.', '' ),
            'term_time_only' => isset( $_POST['term_time_only'] ) && '1' === (string) wp_unslash( $_POST['term_time_only'] ) ? 1 : 0,
        ), array( 'id' => $id ), array( '%d', '%s', '%s', '%s', '%d' ), array( '%d' ) );
        if ( false === $updated ) { wp_die( 'Session save failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-activity-detail', 'activity_id' => $activity_id, 'bh3_session_saved' => '1' ), admin_url( 'tools.php' ) ) );
        exit;
    }
}
