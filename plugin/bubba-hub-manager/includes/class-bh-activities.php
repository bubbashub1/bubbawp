<?php
/**
 * Read-only Activities Manager scaffold. Requires schema verification before editing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activities_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Activities', 'Activities', 'manage_options', 'bh3-activities', array( __CLASS__, 'render' ) );
    }
    private static function table() {
        global $wpdb;
        foreach ( array_unique( array( 'bh_activities', $wpdb->prefix . 'bh_activities' ) ) as $candidate ) {
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) );
            if ( $found === $candidate ) { return $candidate; }
        }
        return null;
    }
    private static function detail( $table, $columns, $id ) {
        global $wpdb;
        $allowed = array(
            'title' => 'Title', 'activity_name' => 'Activity name', 'name' => 'Name',
            'description' => 'Description', 'short_description' => 'Short description',
            'category' => 'Category', 'age_min_months' => 'Minimum age (months)',
            'age_max_months' => 'Maximum age (months)', 'price' => 'Price',
            'is_free' => 'Free activity', 'status' => 'Status',
            'venue_id' => 'Venue ID', 'organiser_id' => 'Organiser ID',
            'website' => 'Website', 'booking_url' => 'Booking URL'
        );
        $fields = array_intersect_key( $allowed, array_fill_keys( $columns, true ) );
        if ( ! $fields ) { echo '<p>No approved display fields were found.</p>'; return; }
        $quoted = '`' . str_replace( '`', '``', $table ) . '`';
        $names = array_keys( $fields );
        $select = implode( ', ', array_map( static function( $field ) { return '`' . $field . '`'; }, $names ) );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . $select . ' FROM ' . $quoted . ' WHERE `id` = %d LIMIT 1', $id ), ARRAY_A );
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bh3-activities' ) ) . '">&larr; Back to activities</a></p>';
        if ( ! $row ) { echo '<div class="notice notice-warning inline"><p>Activity not found.</p></div>'; return; }
        echo '<h2>Activity #' . esc_html( (string) $id ) . ' — read-only detail</h2>';
        echo '<p>Field editing, timetable relationships and publishing actions will be enabled only after mapping and permission checks.</p>';
        echo '<table class="widefat striped"><tbody>';
        foreach ( $fields as $field => $label ) {
            $value = isset( $row[$field] ) ? (string) $row[$field] : '';
            echo '<tr><th scope="row" style="width:220px">' . esc_html( $label ) . '</th><td style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( $value ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ) ); }
        global $wpdb;
        echo '<div class="wrap"><h1>Activities</h1><p>Read-only listing browser for the 3.0 migration. Editing, publishing and deletion are not enabled.</p>';
        $table = self::table();
        if ( ! $table ) {
            echo '<div class="notice notice-warning inline"><p>No legacy activities table found on this WordPress database connection. Check Directory readiness before connecting content.</p></div></div>';
            return;
        }
        $quoted = '`' . str_replace( '`', '``', $table ) . '`';
        $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $quoted, 0 );
        if ( ! is_array( $columns ) ) { echo '<p>Unable to inspect table schema.</p></div>'; return; }
        $title = null;
        foreach ( array( 'title', 'activity_name', 'name' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) { $title = $candidate; break; }
        }
        $id = in_array( 'id', $columns, true ) ? 'id' : null;
        if ( ! $title || ! $id ) {
            echo '<div class="notice notice-warning inline"><p>Activity ID or title field not recognised. Confirm column mappings in Directory readiness.</p></div></div>';
            return;
        }
        $detail_id = isset( $_GET['bh3_activity_id'] ) ? absint( $_GET['bh3_activity_id'] ) : 0;
        if ( $detail_id > 0 ) {
            self::detail( $table, $columns, $detail_id );
            echo '</div>';
            return;
        }
        $search = isset( $_GET['bh3_search'] ) && is_string( $_GET['bh3_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bh3_search'] ) ) : '';
        $page = isset( $_GET['bh3_paged'] ) ? max( 1, absint( $_GET['bh3_paged'] ) ) : 1;
        $limit = 20;
        $offset = ( $page - 1 ) * $limit;
        $id_sql = '`' . $id . '`';
        $title_sql = '`' . $title . '`';
        $where = '';
        $args = array();
        if ( $search !== '' ) {
            $where = ' WHERE ' . $title_sql . ' LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        $count_sql = 'SELECT COUNT(*) FROM ' . $quoted . $where;
        $count = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql );
        $sql = 'SELECT ' . $id_sql . ' AS activity_id, ' . $title_sql . ' AS activity_title FROM ' . $quoted . $where . ' ORDER BY ' . $id_sql . ' DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A );
        echo '<form method="get"><input type="hidden" name="page" value="bh3-activities"><label for="bh3-search">Search activity title </label><input id="bh3-search" type="search" name="bh3_search" value="' . esc_attr( $search ) . '"> ';
        submit_button( 'Search', 'secondary', '', false );
        echo '</form><p>' . esc_html( number_format_i18n( $count ) ) . ' matching activities</p>';
        echo '<table class="widefat striped"><thead><tr><th scope="col">ID</th><th scope="col">Activity title</th><th scope="col">Actions</th></tr></thead><tbody>';
        if ( $rows ) {
            foreach ( $rows as $row ) {
                echo '<tr><td>' . esc_html( (string) $row['activity_id'] ) . '</td><td>' . esc_html( (string) $row['activity_title'] ) . '</td><td><a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh3_activity_id' => absint( $row['activity_id'] ) ), admin_url( 'admin.php' ) ) ) . '">View details</a></td></tr>';
            }
        } else { echo '<tr><td colspan="3">No activities found.</td></tr>'; }
        echo '</tbody></table>';
        $pages = (int) ceil( $count / $limit );
        if ( $pages > 1 ) {
            echo '<p class="tablenav-pages">';
            if ( $page > 1 ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh3_search' => $search, 'bh3_paged' => $page - 1 ), admin_url( 'admin.php' ) ) ) . '">Previous</a> '; }
            echo esc_html( 'Page ' . $page . ' of ' . $pages );
            if ( $page < $pages ) { echo ' <a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh3_search' => $search, 'bh3_paged' => $page + 1 ), admin_url( 'admin.php' ) ) ) . '">Next</a>'; }
            echo '</p>';
        }
        echo '<p><em>Existing records are read without modification. Publishing controls and field mapping will follow schema and permissions QA.</em></p></div>';
    }
}
