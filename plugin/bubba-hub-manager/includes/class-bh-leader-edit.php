<?php
/** Staging-only owner-scoped draft editing for linked organisers. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Edit_3 {
    private static function allowed() {
        return is_user_logged_in() && current_user_can( 'read' ) &&
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function owned_draft( $id ) {
        if ( ! self::allowed() || ! $id ) { return null; }
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT id,title,description,category,age_min_months,age_max_months,price_from,is_free,booking_url FROM `{$table}`
             WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $id, $organiser
        ), ARRAY_A );
    }
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_submenu_page( null, 'Edit My Activity', 'Edit Activity', 'read', 'bh3-leader-edit', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        $id = absint( $_GET['activity_id'] ?? 0 );
        $row = self::owned_draft( $id );
        if ( ! $row ) { wp_die( 'You cannot edit this activity.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap" style="max-width:900px"><h1>Edit ' . esc_html( $row['title'] ) . '</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=bh3-my-listings' ) ) . '">← My Listings</a></p>';
        if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success"><p>Draft saved.</p></div>'; }
        echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-sessions', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Manage timetable</a> <a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-venues', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Manage venues</a></p>';
        echo '<p>You can update your draft details. Organiser assignment, publishing and deletion remain administrator-controlled.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_leader_save_activity"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_leader_edit_' . $id );
        echo '<table class="form-table"><tbody>';
        foreach ( array( 'title' => 'Activity title', 'category' => 'Primary category', 'age_min_months' => 'Minimum age (months)', 'age_max_months' => 'Maximum age (months)', 'price_from' => 'Price from (£)', 'booking_url' => 'Booking URL' ) as $key => $label ) {
            $number = in_array( $key, array( 'age_min_months', 'age_max_months', 'price_from' ), true );
            $type = $number ? 'number' : ( $key === 'booking_url' ? 'url' : 'text' );
            $extra = $number ? ' min="0" step="' . ( $key === 'price_from' ? '0.01' : '1' ) . '"' : '';
            echo '<tr><th><label for="bh3_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="bh3_' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $row[$key] ?? '' ) ) . '"' . $extra . ( $key === 'title' ? ' required' : '' ) . '></td></tr>';
        }
        echo '<tr><th>Description</th><td><textarea class="large-text" rows="8" name="description">' . esc_textarea( (string) $row['description'] ) . '</textarea></td></tr>';
        echo '<tr><th>Free activity</th><td><label><input type="checkbox" name="is_free" value="1"' . checked( (int) $row['is_free'], 1, false ) . '> Yes, free</label></td></tr></tbody></table>';
        submit_button( 'Save draft' );
        echo '</form></div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_edit_' . $id );
        if ( ! self::owned_draft( $id ) ) { wp_die( 'You cannot edit this activity.', '', array( 'response' => 403 ) ); }
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
        if ( $title === '' || strlen( $title ) > 255 || strlen( $category ) > 150 ) {
            wp_die( 'Invalid title or category.', '', array( 'response' => 400 ) );
        }
        $ages = array();
        foreach ( array( 'age_min_months', 'age_max_months' ) as $key ) {
            $raw = trim( (string) wp_unslash( $_POST[$key] ?? '' ) );
            $value = $raw === '' ? null : filter_var( $raw, FILTER_VALIDATE_INT );
            if ( $value !== null && ( $value === false || $value < 0 || $value > 216 ) ) {
                wp_die( 'Invalid age range.', '', array( 'response' => 400 ) );
            }
            $ages[$key] = $value;
        }
        if ( $ages['age_min_months'] !== null && $ages['age_max_months'] !== null && $ages['age_min_months'] > $ages['age_max_months'] ) {
            wp_die( 'Minimum age exceeds maximum age.', '', array( 'response' => 400 ) );
        }
        $free = ! empty( $_POST['is_free'] ) ? 1 : 0;
        $price = trim( (string) wp_unslash( $_POST['price_from'] ?? '' ) );
        if ( $price !== '' && ( ! preg_match( '/^\\d{1,8}(?:\\.\\d{1,2})?$/', $price ) || (float) $price > 99999999.99 ) ) {
            wp_die( 'Invalid price.', '', array( 'response' => 400 ) );
        }
        $raw_url = trim( (string) wp_unslash( $_POST['booking_url'] ?? '' ) );
        $url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
        if ( strlen( $raw_url ) > 2048 || ( $raw_url !== '' && ( $url === '' || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) ) {
            wp_die( 'Invalid booking URL.', '', array( 'response' => 400 ) );
        }
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        global $wpdb;
        $result = $wpdb->update( $wpdb->prefix . 'activities', array(
            'title' => $title, 'category' => $category,
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'age_min_months' => $ages['age_min_months'], 'age_max_months' => $ages['age_max_months'],
            'price_from' => $free ? 0 : ( $price === '' ? null : (float) $price ),
            'is_free' => $free, 'booking_url' => $url, 'updated_at' => current_time( 'mysql', true )
        ), array( 'id' => $id, 'organiser_id' => $organiser, 'status' => 'draft' ) );
        if ( $result === false ) { wp_die( 'Save failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => $id, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
