<?php
/** Staging-only venue management for the fresh WordPress schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Venues_3 {
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'venues';
    }
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Venues', 'Venues', 'manage_options', 'bh3-venues', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_save_venue', array( __CLASS__, 'save' ) );
    }
    public static function render() {
        if ( ! self::allowed() ) { wp_die( 'Venues management is restricted to staging administrators.', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $table = self::table();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            echo '<div class="wrap"><h1>Venues</h1><p>Venue table not found.</p></div>';
            return;
        }
        $id = absint( $_GET['venue_id'] ?? 0 );
        $venue = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A ) : null;
        if ( $id && ! $venue ) { wp_die( 'Venue not found.', '', array( 'response' => 404 ) ); }
        echo '<div class="wrap"><h1>Venues</h1>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Venue saved.</p></div>'; }
        echo '<p>Manage venue addresses and coordinates. Existing activity links are not changed.</p>';
        echo '<h2>' . ( $id ? 'Edit venue' : 'Add venue' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_save_venue"><input type="hidden" name="venue_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_save_venue_' . $id );
        echo '<table class="form-table"><tbody>';
        $fields = array( 'name' => 'Venue name', 'address_line1' => 'Address', 'town' => 'Town', 'postcode' => 'Postcode', 'region' => 'Region', 'latitude' => 'Latitude', 'longitude' => 'Longitude' );
        foreach ( $fields as $key => $label ) {
            echo '<tr><th><label for="venue_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="venue_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $venue[$key] ?? '' ) ) . '"' . ( $key === 'name' ? ' required maxlength="255"' : '' ) . '></td></tr>';
        }
        echo '</tbody></table>';
        submit_button( $id ? 'Update venue' : 'Create venue' );
        echo '</form>';
        if ( $id ) { echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bh3-venues' ) ) . '">Add another venue</a></p>'; }
        $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
        $search = substr( $search, 0, 100 );
        echo '<h2>Existing venues</h2><form method="get"><input type="hidden" name="page" value="bh3-venues"><input type="search" name="search" value="' . esc_attr( $search ) . '" placeholder="Name or town"> ';
        submit_button( 'Search', 'secondary', '', false );
        echo '</form>';
        $where = $search !== '' ? $wpdb->prepare( 'WHERE name LIKE %s OR town LIKE %s', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' ) : '';
        $rows = $wpdb->get_results( "SELECT id, name, town, postcode, latitude, longitude FROM `{$table}` {$where} ORDER BY id DESC LIMIT 100", ARRAY_A );
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Town</th><th>Postcode</th><th>Coordinates</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $url = add_query_arg( array( 'page' => 'bh3-venues', 'venue_id' => absint( $row['id'] ) ), admin_url( 'admin.php' ) );
            echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( (string) $row['town'] ) . '</td><td>' . esc_html( (string) $row['postcode'] ) . '</td><td>' . esc_html( $row['latitude'] !== null && $row['longitude'] !== null ? $row['latitude'] . ', ' . $row['longitude'] : 'Not set' ) . '</td><td><a href="' . esc_url( $url ) . '">Edit</a></td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="5">No venues found.</td></tr>'; }
        echo '</tbody></table><p>Showing up to 100 venues. Deletion and merging are disabled pending relationship checks.</p></div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['venue_id'] ?? 0 );
        check_admin_referer( 'bh3_save_venue_' . $id );
        global $wpdb;
        $table = self::table();
        $limits = array( 'name' => 255, 'address_line1' => 255, 'town' => 150, 'postcode' => 20, 'region' => 100 );
        $data = array();
        foreach ( $limits as $key => $limit ) {
            $value = sanitize_text_field( wp_unslash( $_POST[$key] ?? '' ) );
            if ( strlen( $value ) > $limit || ( $key === 'name' && $value === '' ) ) { wp_die( 'Invalid venue field: ' . esc_html( $key ), '', array( 'response' => 400 ) ); }
            $data[$key] = $value === '' && $key !== 'name' ? null : $value;
        }
        foreach ( array( 'latitude' => 90, 'longitude' => 180 ) as $key => $bound ) {
            $raw = trim( (string) wp_unslash( $_POST[$key] ?? '' ) );
            if ( $raw !== '' && ( ! is_numeric( $raw ) || ! is_finite( (float) $raw ) || abs( (float) $raw ) > $bound ) ) { wp_die( 'Invalid coordinates.', '', array( 'response' => 400 ) ); }
            $data[$key] = $raw === '' ? null : round( (float) $raw, 7 );
        }
        if ( ( $data['latitude'] === null ) !== ( $data['longitude'] === null ) ) { wp_die( 'Enter both latitude and longitude.', '', array( 'response' => 400 ) ); }
        $now = current_time( 'mysql', true );
        $data['updated_at'] = $now;
        if ( $id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d", $id ) );
            if ( ! $exists ) { wp_die( 'Venue not found.', '', array( 'response' => 404 ) ); }
            $result = $wpdb->update( $table, $data, array( 'id' => $id ) );
        } else {
            $data['created_at'] = $now;
            $result = $wpdb->insert( $table, $data );
            $id = (int) $wpdb->insert_id;
        }
        if ( $result === false ) { wp_die( 'Unable to save venue.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-venues', 'venue_id' => $id, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
