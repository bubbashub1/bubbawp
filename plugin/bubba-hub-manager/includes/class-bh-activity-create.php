<?php
/** Staging-only create activity form for the fresh WordPress schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Create_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Add Activity', 'Add Activity', 'manage_options', 'bh3-add-activity', array( __CLASS__, 'render' ) );

    }
    public static function register_save() {
        add_action( 'admin_post_bh3_create_activity', array( __CLASS__, 'save' ) );
    }
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
        echo '<div class="wrap"><h1>Add Activity</h1>';
        if ( ! self::allowed() ) { echo '<p>Activity creation is restricted to staging administrators.</p></div>'; return; }
        if ( isset( $_GET['created'] ) ) {
            echo '<div class="notice notice-success"><p>Activity created as a draft. ID: ' . esc_html( (string) absint( $_GET['created'] ) ) . '</p></div>';
        }
        echo '<p>Creates a draft only. Publishing, images, organisers, venues and timetables will be added in later stages.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_create_activity">';
        wp_nonce_field( 'bh3_create_activity' );
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="bh3_title">Activity title</label></th><td><input required maxlength="255" class="regular-text" id="bh3_title" name="title"></td></tr>';
        echo '<tr><th><label for="bh3_description">Description</label></th><td><textarea class="large-text" rows="7" id="bh3_description" name="description"></textarea></td></tr>';
        echo '<tr><th><label for="bh3_category">Primary category</label></th><td><input maxlength="150" class="regular-text" id="bh3_category" name="category"></td></tr>';
        echo '<tr><th><label for="bh3_age_min">Minimum age (months)</label></th><td><input type="number" min="0" max="216" id="bh3_age_min" name="age_min_months"></td></tr>';
        echo '<tr><th><label for="bh3_age_max">Maximum age (months)</label></th><td><input type="number" min="0" max="216" id="bh3_age_max" name="age_max_months"></td></tr>';
        echo '<tr><th><label for="bh3_price">Price from (£)</label></th><td><input type="number" step="0.01" min="0" max="99999999" id="bh3_price" name="price_from"> <label><input type="checkbox" name="is_free" value="1"> Free activity</label></td></tr>';
        echo '<tr><th><label for="bh3_booking">Booking URL</label></th><td><input type="url" class="large-text" id="bh3_booking" name="booking_url"></td></tr>';
        echo '</table>';
        submit_button( 'Save draft activity' );
        echo '</form></div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_create_activity' );
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            wp_die( 'Activities table is missing.', '', array( 'response' => 409 ) );
        }
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( $title === '' || strlen( $title ) > 255 ) { wp_die( 'Enter a valid activity title.', '', array( 'response' => 400 ) ); }
        $min = isset( $_POST['age_min_months'] ) && $_POST['age_min_months'] !== '' ? filter_var( wp_unslash( $_POST['age_min_months'] ), FILTER_VALIDATE_INT ) : null;
        $max = isset( $_POST['age_max_months'] ) && $_POST['age_max_months'] !== '' ? filter_var( wp_unslash( $_POST['age_max_months'] ), FILTER_VALIDATE_INT ) : null;
        foreach ( array( $min, $max ) as $age ) {
            if ( $age !== null && ( $age === false || $age < 0 || $age > 216 ) ) { wp_die( 'Invalid age range.', '', array( 'response' => 400 ) ); }
        }
        if ( $min !== null && $max !== null && $min > $max ) { wp_die( 'Minimum age cannot exceed maximum age.', '', array( 'response' => 400 ) ); }
        $free = ! empty( $_POST['is_free'] ) ? 1 : 0;
        $raw_price = trim( (string) wp_unslash( $_POST['price_from'] ?? '' ) );
        if ( ! $free && $raw_price !== '' && ( ! is_numeric( $raw_price ) || (float) $raw_price < 0 || (float) $raw_price > 99999999 ) ) {
            wp_die( 'Invalid price.', '', array( 'response' => 400 ) );
        }
        $booking_url = esc_url_raw( wp_unslash( $_POST['booking_url'] ?? '' ) );
        if ( strlen( $booking_url ) > 2048 || ( $booking_url && ! in_array( strtolower( (string) wp_parse_url( $booking_url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) {
            wp_die( 'Invalid booking URL.', '', array( 'response' => 400 ) );
        }
        $slug = sanitize_title( $title );
        $base = substr( $slug ?: 'activity', 0, 175 );
        $slug = $base;
        for ( $suffix = 2; $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s LIMIT 1", $slug ) ); ++$suffix ) {
            $slug = $base . '-' . $suffix;
            if ( $suffix > 10000 ) { wp_die( 'Unable to allocate a unique slug.', '', array( 'response' => 409 ) ); }
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'title' => $title, 'slug' => $slug,
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'status' => 'draft',
            'category' => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
            'age_min_months' => $min, 'age_max_months' => $max,
            'price_from' => $free ? 0 : ( $raw_price === '' ? null : round( (float) $raw_price, 2 ) ),
            'is_free' => $free, 'booking_url' => $booking_url,
            'created_at' => $now, 'updated_at' => $now
        );
        if ( false === $wpdb->insert( $table, $data ) ) { wp_die( 'Unable to save activity. Check database logs.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( 'created', $wpdb->insert_id, admin_url( 'admin.php?page=bh3-add-activity' ) ) );
        exit;
    }
}
