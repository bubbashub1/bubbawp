<?php
/** Safe CSV preview: never writes to the database. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_CSV_Preview_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'CSV Preview', 'CSV Import', 'manage_options', 'bh3-csv-preview', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator access required.' ); }
        echo '<div class="wrap"><h1>CSV Import Preview</h1><p>Read-only preview. No data will be saved.</p>';
        if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== 'staging.bubbahub.co.uk' ) {
            echo '<p>Available on staging only.</p></div>'; return;
        }
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'bh3_csv_preview' );
        echo '<p><label>Record type <select name="record_type">';
        foreach ( array( 'activities', 'venues', 'organisers', 'sessions' ) as $type ) {
            echo '<option value="' . esc_attr( $type ) . '">' . esc_html( ucfirst( $type ) ) . '</option>';
        }
        echo '</select></label></p><p><input type="file" name="csv_file" accept=".csv" required></p>';
        submit_button( 'Preview CSV' );
        echo '</form>';
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { echo '</div>'; return; }
        check_admin_referer( 'bh3_csv_preview' );
        $file = $_FILES['csv_file'] ?? array();
        $type = sanitize_key( wp_unslash( $_POST['record_type'] ?? '' ) );
        if ( ! in_array( $type, array( 'activities', 'venues', 'organisers', 'sessions' ), true )
            || (int) ( $file['error'] ?? -1 ) !== UPLOAD_ERR_OK
            || (int) ( $file['size'] ?? 0 ) < 1
            || (int) ( $file['size'] ?? 0 ) > 2097152
            || strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) ) !== 'csv'
            || ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) {
            echo '<p>Invalid CSV upload. Maximum file size is 2 MB.</p></div>'; return;
        }
        $handle = fopen( $file['tmp_name'], 'rb' );
        if ( ! $handle ) { echo '<p>Cannot open uploaded CSV.</p></div>'; return; }
        $headers = fgetcsv( $handle, 0, ',', '"', '' );
        if ( ! is_array( $headers ) || count( $headers ) > 60 ) {
            fclose( $handle ); echo '<p>Invalid CSV headers.</p></div>'; return;
        }
        $headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
        $headers = array_map( 'trim', $headers );
        if ( in_array( '', $headers, true ) || count( array_unique( $headers ) ) !== count( $headers ) ) {
            fclose( $handle ); echo '<p>CSV headers must be unique and nonempty.</p></div>'; return;
        }
        $allowed = array(
            'activities' => array( 'title', 'slug', 'description', 'status', 'category', 'organiser_id', 'age_min_months', 'age_max_months', 'price_from', 'is_free', 'booking_url', 'image_id' ),
            'venues' => array( 'name', 'address_line1', 'town', 'postcode', 'region', 'latitude', 'longitude' ),
            'organisers' => array( 'name', 'slug', 'description', 'website_url', 'contact_email' ),
            'sessions' => array( 'activity_id', 'venue_id', 'day_of_week', 'start_time', 'end_time', 'start_date', 'end_date', 'frequency', 'term_time_only', 'status' ),
        );
        $aliases = array( 'activity_name' => 'title', 'activity_title' => 'title', 'venue_name' => 'name', 'organiser_name' => 'name', 'email' => 'contact_email', 'website' => 'website_url', 'address' => 'address_line1', 'lat' => 'latitude', 'lng' => 'longitude', 'price' => 'price_from' );
        $mapped = array(); $unmapped = array();
        foreach ( $headers as $header ) {
            $key = sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( $header ) ) );
            $target = in_array( $key, $allowed[$type], true ) ? $key : ( $aliases[$key] ?? '' );
            if ( in_array( $target, $allowed[$type], true ) ) { $mapped[$header] = $target; } else { $unmapped[] = $header; }
        }
        echo '<h2>Suggested column mapping</h2><table class="widefat striped"><thead><tr><th>CSV column</th><th>BH3 field</th></tr></thead><tbody>';
        foreach ( $mapped as $source => $target ) { echo '<tr><td>' . esc_html( $source ) . '</td><td>' . esc_html( $target ) . '</td></tr>'; }
        echo '</tbody></table>';
        if ( $unmapped ) { echo '<p>Unmapped columns: ' . esc_html( implode( ', ', $unmapped ) ) . '</p>'; }
        if ( count( array_unique( array_values( $mapped ) ) ) !== count( $mapped ) ) { echo '<p><strong>Warning:</strong> duplicate field mappings detected.</p>'; }
        $required = $type === 'activities' ? 'title' : ( $type === 'sessions' ? 'activity_id' : 'name' );
        if ( ! in_array( $required, $mapped, true ) ) { echo '<p><strong>Warning:</strong> required field missing: ' . esc_html( $required ) . '.</p>'; }
        echo '<p>Suggested mappings only; no changes saved.</p>';
        echo '<h2>' . esc_html( ucfirst( $type ) ) . ' — first 10 rows</h2><div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
        foreach ( $headers as $header ) { echo '<th>' . esc_html( $header ) . '</th>'; }
        echo '</tr></thead><tbody>';
        $count = 0; $errors = 0;
        while ( $count < 500 && ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
            if ( $row === array( null ) ) { continue; }
            ++$count;
            if ( count( $row ) !== count( $headers ) ) { ++$errors; continue; }
            if ( $count <= 10 ) {
                echo '<tr>';
                foreach ( $row as $cell ) { echo '<td>' . esc_html( substr( (string) $cell, 0, 300 ) ) . '</td>'; }
                echo '</tr>';
            }
        }
        fclose( $handle );
        echo '</tbody></table></div><p>Scanned ' . esc_html( (string) $count ) . ' rows (maximum 500). Invalid column counts: ' . esc_html( (string) $errors ) . '.</p><p>No database changes made. Mapping, duplicate checks and confirmed import are future steps.</p></div>';
    }
}
