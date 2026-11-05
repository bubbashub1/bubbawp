<?php
/** Staging-only editing and administrator-controlled publishing of activities. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Edit_3 {
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function load( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND status IN ('draft', 'published') LIMIT 1", $id ), ARRAY_A );
    }
    public static function register() {
        add_submenu_page( null, 'Edit Activity', 'Edit Activity', 'manage_options', 'bh3-edit-activity', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_update_activity', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_change_activity_status', array( __CLASS__, 'change_status' ) );
    }
    public static function render() {
        if ( ! self::allowed() ) { wp_die( 'Editing is restricted to staging administrators.', '', array( 'response' => 403 ) ); }
        $id = absint( $_GET['activity_id'] ?? 0 );
        $row = $id ? self::load( $id ) : null;
        if ( ! $row ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        echo '<div class="wrap"><h1>Edit activity #' . esc_html( (string) $id ) . '</h1>';
        if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success"><p>Activity updated.</p></div>'; }
        echo '<p>Publishing is available to staging administrators only.</p>';
        echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activity-venues', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Manage venues</a> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-sessions', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Manage timetable</a></p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_update_activity"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_update_activity_' . $id );
        echo '<table class="form-table"><tbody>';
        foreach ( array( 'title' => 'Title', 'category' => 'Primary category', 'age_min_months' => 'Minimum age in months', 'age_max_months' => 'Maximum age in months', 'price_from' => 'Price from (£)', 'booking_url' => 'Booking URL' ) as $key => $label ) {
            $type = in_array( $key, array( 'age_min_months', 'age_max_months', 'price_from' ), true ) ? 'number' : ( $key === 'booking_url' ? 'url' : 'text' );
            $extra = $type === 'number' ? ' min="0" step="' . ( $key === 'price_from' ? '0.01' : '1' ) . '"' : '';
            echo '<tr><th><label for="edit_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="edit_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) ( $row[$key] ?? '' ) ) . '"' . $extra . ( $key === 'title' ? ' required' : '' ) . '></td></tr>';
        }
        echo '<tr><th>Description</th><td><textarea class="large-text" rows="7" name="description">' . esc_textarea( (string) $row['description'] ) . '</textarea></td></tr>';
        echo '<tr><th>Free activity</th><td><label><input type="checkbox" name="is_free" value="1"' . checked( (int) $row['is_free'], 1, false ) . '> Free</label></td></tr>';
        global $wpdb;
        $org_table = $wpdb->prefix . 'organisers';
        $orgs = $wpdb->get_results( "SELECT id, name FROM `{$org_table}` ORDER BY name ASC LIMIT 500", ARRAY_A );
        echo '<tr><th><label for="edit_organiser">Organiser</label></th><td><select id="edit_organiser" name="organiser_id"><option value="0">Not assigned</option>';
        $current = absint( $row['organiser_id'] ?? 0 );
        $found_current = ! $current;
        foreach ( $orgs as $org ) {
            $selected = (int) $org['id'] === $current;
            if ( $selected ) { $found_current = true; }
            echo '<option value="' . esc_attr( (string) $org['id'] ) . '"' . selected( $selected, true, false ) . '>' . esc_html( $org['name'] ) . '</option>';
        }
        if ( ! $found_current ) {
            $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$org_table}` WHERE id = %d", $current ) );
            if ( $name ) { echo '<option selected value="' . esc_attr( (string) $current ) . '">' . esc_html( $name ) . '</option>'; }
        }
        echo '</select> <a href="' . esc_url( admin_url( 'admin.php?page=bh3-organisers' ) ) . '">Manage organisers</a></td></tr>';
        echo '</tbody></table>';
        submit_button( 'Save activity' );
        echo '</form>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_change_activity_status"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_change_activity_status_' . $id );
        $published = $row['status'] === 'published';
        submit_button( $published ? 'Return to draft' : 'Publish on staging', $published ? 'secondary' : 'primary', 'submit', false );
        echo '</form>';
        if ( $published && ! empty( $row['slug'] ) ) { echo '<p><a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( home_url( '/activity/' . $row['slug'] . '/' ) ) . '">View public activity</a></p>'; }
        echo '</div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_update_activity_' . $id );
        if ( ! $id || ! self::load( $id ) ) { wp_die( 'Draft activity not found.', '', array( 'response' => 404 ) ); }
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
        if ( $title === '' || strlen( $title ) > 255 || strlen( $category ) > 150 ) { wp_die( 'Invalid title or category.', '', array( 'response' => 400 ) ); }
        $ages = array();
        foreach ( array( 'age_min_months', 'age_max_months' ) as $key ) {
            $raw = (string) wp_unslash( $_POST[$key] ?? '' );
            $age = $raw === '' ? null : filter_var( $raw, FILTER_VALIDATE_INT );
            if ( $age !== null && ( $age === false || $age < 0 || $age > 216 ) ) { wp_die( 'Invalid age range.', '', array( 'response' => 400 ) ); }
            $ages[$key] = $age;
        }
        if ( $ages['age_min_months'] !== null && $ages['age_max_months'] !== null && $ages['age_min_months'] > $ages['age_max_months'] ) { wp_die( 'Minimum age exceeds maximum age.', '', array( 'response' => 400 ) ); }
        $free = ! empty( $_POST['is_free'] ) ? 1 : 0;
        $raw_price = trim( (string) wp_unslash( $_POST['price_from'] ?? '' ) );
        if ( $raw_price !== '' && ( ! is_numeric( $raw_price ) || (float) $raw_price < 0 || (float) $raw_price > 99999999 ) ) { wp_die( 'Invalid price.', '', array( 'response' => 400 ) ); }
        $url = esc_url_raw( wp_unslash( $_POST['booking_url'] ?? '' ) );
        if ( strlen( $url ) > 2048 || ( $url && ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) { wp_die( 'Invalid URL.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $org_id = absint( $_POST['organiser_id'] ?? 0 );
        if ( $org_id ) {
            $org_table = $wpdb->prefix . 'organisers';
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$org_table}` WHERE id = %d", $org_id ) ) ) {
                wp_die( 'Selected organiser does not exist.', '', array( 'response' => 400 ) );
            }
        }
        $data = array(
            'organiser_id' => $org_id ?: null,
            'title' => $title, 'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'category' => $category, 'age_min_months' => $ages['age_min_months'],
            'age_max_months' => $ages['age_max_months'], 'price_from' => $free ? 0 : ( $raw_price === '' ? null : round( (float) $raw_price, 2 ) ),
            'is_free' => $free, 'booking_url' => $url, 'updated_at' => current_time( 'mysql', true )
        );
        $table = $wpdb->prefix . 'activities';
        $result = $wpdb->update( $table, $data, array( 'id' => $id ) );
        if ( $result === false ) { wp_die( 'Database update failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-edit-activity', 'activity_id' => $id, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function change_status() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_change_activity_status_' . $id );
        $row = $id ? self::load( $id ) : null;
        if ( ! $row ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        $next = $row['status'] === 'published' ? 'draft' : 'published';
        if ( $next === 'published' && ( trim( (string) $row['title'] ) === '' || trim( (string) $row['slug'] ) === '' ) ) {
            wp_die( 'Add a title and slug before publishing.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $data = array( 'status' => $next, 'updated_at' => current_time( 'mysql', true ) );
        // published_at is an optional parity column, absent from the base activities schema.
        if ( $next === 'published' ) {
            $table_name = $wpdb->prefix . 'activities';
            $column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'published_at' ) );
            if ( $column === 'published_at' ) { $data['published_at'] = current_time( 'mysql', true ); }
        }
        $result = $wpdb->update( $wpdb->prefix . 'activities', $data, array( 'id' => $id, 'status' => $row['status'] ) );
        if ( $result === false || $result === 0 ) { wp_die( 'Could not update activity status.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-edit-activity', 'activity_id' => $id, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
