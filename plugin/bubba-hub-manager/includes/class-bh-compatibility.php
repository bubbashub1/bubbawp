<?php
/**
 * Non-destructive compatibility inspection for the legacy bh_ schema.
 * Never runs migrations, exposes credentials or reads private family records.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Compatibility_3 {
    public static function register() {
        add_management_page( 'Bubba Hub Compatibility', 'BH Compatibility', 'manage_options', 'bh3-compatibility', array( __CLASS__, 'render' ) );
    }

    public static function inspect() {
        global $wpdb;
        $requirements = array(
            'bh_activities' => array( 'id', 'title', 'slug', 'description', 'category', 'age_range', 'price_from', 'status', 'organiser_id' ),
            'bh_venues' => array( 'id', 'activity_id', 'venue_name', 'town', 'region', 'postcode' ),
            'bh_organisers' => array( 'id', 'organisation_name', 'slug', 'status' ),
            'bh_sessions' => array( 'id', 'venue_id', 'day_of_week', 'start_time', 'end_time', 'price', 'term_time_only' ),
        );
        $report = array();
        foreach ( $requirements as $table => $needed ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
            $columns = array();
            if ( $exists ) {
                // Table identifiers are fixed constants from the requirements above.
                $columns = $wpdb->get_col( 'SHOW COLUMNS FROM `' . $table . '`', 0 );
            }
            $missing = array_values( array_diff( $needed, $columns ?: array() ) );
            $report[ $table ] = array(
                'exists' => $exists,
                'missing' => $missing,
                'compatible' => $exists && ! $missing,
            );
        }
        return $report;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); }
        $report = self::inspect();
        echo '<div class="wrap"><h1>Bubba Hub Compatibility</h1>';
        echo '<p>This diagnostic checks table and column availability in the current WordPress database. It does not create or change anything.</p>';
        echo '<p><strong>WordPress:</strong> ' . esc_html( get_bloginfo( 'version' ) ) . ' &nbsp; <strong>PHP:</strong> ' . esc_html( PHP_VERSION ) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th scope="col">Legacy table</th><th scope="col">Result</th><th scope="col">Missing fields</th></tr></thead><tbody>';
        foreach ( $report as $table => $result ) {
            $label = ! $result['exists'] ? 'Table missing' : ( $result['compatible'] ? 'Compatible for preview' : 'Schema mismatch' );
            echo '<tr><th scope="row">' . esc_html( $table ) . '</th><td>' . esc_html( $label ) . '</td><td>' . esc_html( implode( ', ', $result['missing'] ) ?: 'None' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        $all_ok = ! in_array( false, array_column( $report, 'compatible' ), true );
        echo '<p><strong>' . esc_html( $all_ok ? 'Basic schema checks passed.' : 'Compatibility checks require attention.' ) . '</strong> ';
        echo esc_html( 'Passing does not establish safe editing, user-account compatibility or full like-for-like parity.' ) . '</p>';
        echo '<p>Editing remains disabled unless explicitly enabled after staging QA.</p></div>';
    }
}
