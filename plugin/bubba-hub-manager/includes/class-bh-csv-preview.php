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
