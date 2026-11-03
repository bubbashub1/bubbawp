<?php
/** Staging-only new draft creation for an authenticated linked organiser. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Create_3 {
    private static function organiser() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ||
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return 0; }
        global $wpdb;
        $id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $id ) { return 0; }
        $table = $wpdb->prefix . 'organisers';
        return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id=%d LIMIT 1", $id ) ) ? $id : 0;
    }
    public static function register() {
        if ( strtolower( (string ) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_submenu_page( null, 'New Activity', 'New Activity', 'read', 'bh3-leader-create', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! self::organiser() ) { wp_die( 'Your account must be linked to an organiser.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><h1>Create a new class</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=bh3-my-listings' ) ) . '">← My Listings</a></p>';
        echo '<p><strong>Step 1 of 3 — Class details.</strong> Save your draft, then add venues and weekly sessions from its editor. Nothing is published automatically.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_leader_create_activity">';
        wp_nonce_field( 'bh3_leader_create_activity' );
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="bh3_title">Class title</label></th><td><input required maxlength="255" class="regular-text" id="bh3_title" name="title"></td></tr>';
        echo '<tr><th><label for="bh3_category">Primary category</label></th><td><input maxlength="150" class="regular-text" id="bh3_category" name="category"></td></tr>';
        echo '<tr><th><label for="bh3_description">Description</label></th><td><textarea class="large-text" id="bh3_description" name="description" rows="7"></textarea></td></tr>';
        echo '<tr><th>Age range (months)</th><td><input type="number" name="age_min_months" min="0" max="216" placeholder="From"> – <input type="number" name="age_max_months" min="0" max="216" placeholder="To"></td></tr>';
        echo '<tr><th>Price from (£)</th><td><input type="number" name="price_from" min="0" max="99999999" step="0.01"> <label><input type="checkbox" name="is_free" value="1"> Free</label></td></tr>';
        echo '<tr><th>External booking link</th><td><input class="regular-text" type="url" name="booking_url" maxlength="2048" placeholder="https://"></td></tr>';
        echo '</tbody></table>';
        submit_button( 'Save draft and continue' );
        echo '</form></div>';
    }
    public static function save() {
        $organiser = self::organiser();
        if ( ! $organiser ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_leader_create_activity' );
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
        if ( $title === '' || strlen( $title ) > 255 || strlen( $category ) > 150 ) { wp_die( 'Invalid title or category.', '', array( 'response' => 400 ) ); }
        $ages = array();
        foreach ( array( 'age_min_months', 'age_max_months' ) as $field ) {
            $raw = trim( (string) wp_unslash( $_POST[$field] ?? '' ) );
            $value = $raw === '' ? null : filter_var( $raw, FILTER_VALIDATE_INT );
            if ( $value !== null && ( $value === false || $value < 0 || $value > 216 ) ) { wp_die( 'Invalid age range.', '', array( 'response' => 400 ) ); }
            $ages[$field] = $value;
        }
        if ( $ages['age_min_months'] !== null && $ages['age_max_months'] !== null && $ages['age_min_months'] > $ages['age_max_months'] ) { wp_die( 'Invalid age range.', '', array( 'response' => 400 ) ); }
        $free = ! empty( $_POST['is_free'] ) ? 1 : 0;
        $price = trim( (string) wp_unslash( $_POST['price_from'] ?? '' ) );
        if ( $price !== '' && ! preg_match( '/^\\d{1,8}(?:\\.\\d{1,2})?$/', $price ) ) { wp_die( 'Invalid price.', '', array( 'response' => 400 ) ); }
        $raw_url = trim( (string) wp_unslash( $_POST['booking_url'] ?? '' ) );
        $url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
        if ( strlen( $raw_url ) > 2048 || ( $raw_url !== '' && ( ! $url || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) ) { wp_die( 'Invalid booking URL.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        $base = substr( sanitize_title( $title ) ?: 'activity', 0, 175 );
        $slug = $base;
        for ( $i = 2; $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug=%s LIMIT 1", $slug ) ); ++$i ) {
            if ( $i > 10000 ) { wp_die( 'Unable to create unique slug.', '', array( 'response' => 409 ) ); }
            $slug = $base . '-' . $i;
        }
        $now = current_time( 'mysql', true );
        $result = $wpdb->insert( $table, array(
            'organiser_id' => $organiser, 'title' => $title, 'slug' => $slug,
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'category' => $category, 'status' => 'draft',
            'age_min_months' => $ages['age_min_months'], 'age_max_months' => $ages['age_max_months'],
            'price_from' => $free ? 0 : ( $price === '' ? null : (float) $price ),
            'is_free' => $free, 'booking_url' => $url, 'created_at' => $now, 'updated_at' => $now
        ) );
        if ( $result === false ) { wp_die( 'Could not create draft.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => $wpdb->insert_id, 'created' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
