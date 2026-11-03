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
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ? $table : null;
    }
    /**
     * Display related names only when a known table and column mapping exists.
     * Never guesses a relationship or writes to legacy records.
     */
    private static function related_label( $kind, $id ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) { return 'Not assigned'; }
        $suffix = $kind === 'venue' ? 'venues' : 'organisers';
        $table = null;
        foreach ( array( $wpdb->prefix . $suffix ) as $candidate ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) ) === $candidate ) {
                $table = $candidate;
                break;
            }
        }
        if ( ! $table ) { return 'ID ' . $id . ' — related table unavailable'; }
        $quoted = '`' . str_replace( '`', '``', $table ) . '`';
        $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $quoted, 0 );
        if ( ! is_array( $columns ) || ! in_array( 'id', $columns, true ) ) {
            return 'ID ' . $id . ' — schema not mapped';
        }
        $label_column = null;
        foreach ( $kind === 'venue' ? array( 'name', 'venue_name', 'title' ) : array( 'name', 'organiser_name', 'business_name', 'title' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) { $label_column = $candidate; break; }
        }
        if ( ! $label_column ) { return 'ID ' . $id . ' — name field not mapped'; }
        $label = $wpdb->get_var( $wpdb->prepare( 'SELECT `' . $label_column . '` FROM ' . $quoted . ' WHERE `id` = %d LIMIT 1', $id ) );
        return is_scalar( $label ) && (string) $label !== '' ? (string) $label . ' (ID ' . $id . ')' : 'ID ' . $id . ' — no matching record';
    }
    /** Bounded, read-only lookup to help identify existing venues/organisers. */
    private static function related_search( $kind ) {
        global $wpdb;
        $param = $kind === 'venue' ? 'bh3_venue_search' : 'bh3_organiser_search';
        $term = isset( $_GET[$param] ) && is_string( $_GET[$param] )
            ? sanitize_text_field( wp_unslash( $_GET[$param] ) ) : '';
        $term = substr( $term, 0, 100 );
        echo '<h4>' . esc_html( $kind === 'venue' ? 'Find existing venue' : 'Find existing organiser' ) . '</h4>';
        echo '<form method="get"><input type="hidden" name="page" value="bh3-activities"><input type="hidden" name="bh3_activity_id" value="' . esc_attr( (string) absint( $_GET['bh3_activity_id'] ?? 0 ) ) . '">';
        echo '<label><span class="screen-reader-text">Search ' . esc_html( $kind ) . '</span><input type="search" name="' . esc_attr( $param ) . '" value="' . esc_attr( $term ) . '" placeholder="Search by name"></label> ';
        submit_button( 'Find ' . $kind, 'secondary', '', false );
        echo '</form>';
        if ( strlen( trim( $term ) ) < 2 ) { echo '<p>Enter at least two characters to search existing records.</p>'; return; }
        $suffix = $kind === 'venue' ? 'venues' : 'organisers';
        $table = null;
        foreach ( array_unique( array( $suffix, $wpdb->prefix . $suffix ) ) as $candidate ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) ) === $candidate ) { $table = $candidate; break; }
        }
        if ( ! $table ) { echo '<p>Related table is not available on this database connection.</p>'; return; }
        $quoted = '`' . str_replace( '`', '``', $table ) . '`';
        $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $quoted, 0 );
        if ( ! is_array( $columns ) || ! in_array( 'id', $columns, true ) ) { echo '<p>Related table schema not mapped.</p>'; return; }
        $name = null;
        foreach ( $kind === 'venue' ? array( 'name', 'venue_name', 'title' ) : array( 'name', 'organiser_name', 'business_name', 'title' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) { $name = $candidate; break; }
        }
        if ( ! $name ) { echo '<p>Name field not mapped.</p>'; return; }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT `id` AS record_id, `' . $name . '` AS record_name FROM ' . $quoted . ' WHERE `' . $name . '` LIKE %s ORDER BY `' . $name . '` ASC LIMIT 20', '%' . $wpdb->esc_like( $term ) . '%' ), ARRAY_A );
        if ( ! $rows ) { echo '<p>No matches found.</p>'; return; }
        $selection_param = $kind === 'venue' ? 'bh3_preview_venue' : 'bh3_preview_organiser';
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>ID</th><th>Preview</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $url = add_query_arg(
                array(
                    'page' => 'bh3-activities',
                    'bh3_activity_id' => absint( $_GET['bh3_activity_id'] ?? 0 ),
                    $param => $term,
                    $selection_param => absint( $row['record_id'] ),
                ),
                admin_url( 'admin.php' )
            );
            echo '<tr><td>' . esc_html( (string) $row['record_name'] ) . '</td><td>' . esc_html( (string) $row['record_id'] ) . '</td><td><a href="' . esc_url( $url ) . '">Preview selection</a></td></tr>';
        }
        echo '</tbody></table><p>Preview only. This does not change the activity or its assigned records.</p>';
    }
    /** Inspect known timetable candidates without querying or changing session rows. */
    private static function timetable_readiness() {
        global $wpdb;
        $candidates = array(
            'bh_activity_sessions', 'bh_sessions', 'bh_activity_schedules',
            'bh_schedules', 'bh_timetables', 'bh_activity_timetable'
        );
        $found = 0;
        echo '<p>The legacy API uses bh_activity_schedules for activity-owned sessions and bh_sessions as a venue-owned fallback. Venue-owned rows must not be attributed to an activity unless the venue relationship is explicitly verified.</p>';
        foreach ( $candidates as $base ) {
            foreach ( array_unique( array( $base, $wpdb->prefix . $base ) ) as $candidate ) {
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) );
                if ( $exists !== $candidate ) { continue; }
                $found++;
                $quoted = '`' . str_replace( '`', '``', $candidate ) . '`';
                $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $quoted, 0 );
                echo '<h4>' . esc_html( $candidate ) . '</h4>';
                if ( ! is_array( $columns ) ) {
                    echo '<p>Unable to inspect columns.</p>';
                    continue;
                }
                $relation = array_values( array_intersect( array( 'activity_id', 'listing_id', 'class_id' ), $columns ) );
                echo '<p>Columns: ' . esc_html( implode( ', ', $columns ) ) . '</p>';
                echo '<p>' . ( $relation
                    ? 'Possible activity link field: ' . esc_html( implode( ', ', $relation ) ) . ' — requires verification.'
                    : 'No recognised activity link field. Manual mapping required.' ) . '</p>';
            }
        }
        if ( ! $found ) {
            echo '<p>No known timetable table was found on this WordPress connection. The legacy system may use another table name or database.</p>';
        }
        echo '<p><strong>Timetable editing remains disabled.</strong> Verify recurrence, date, venue, cancellation and term-time fields before enabling it.</p>';
    }
    /** Visual timetable editor prototype; deliberately no form submission or persistence. */
    private static function timetable_layout_preview() {
        echo '<h4>Session editor layout preview</h4>';
        echo '<p>This shows the planned fields only. It does not display or change real session records.</p>';
        echo '<div style="overflow-x:auto"><table class="widefat striped" style="min-width:760px">';
        echo '<thead><tr><th scope="col">Day</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Venue</th><th scope="col">Session status</th><th scope="col">Term time</th></tr></thead><tbody><tr>';
        echo '<td><select disabled aria-label="Session day"><option>Select day</option></select></td>';
        echo '<td><input type="time" disabled aria-label="Session start time"></td>';
        echo '<td><input type="time" disabled aria-label="Session end time"></td>';
        echo '<td><input type="text" disabled placeholder="Choose existing venue" aria-label="Session venue"></td>';
        echo '<td><select disabled aria-label="Session status"><option>Scheduled</option><option>Cancelled</option></select></td>';
        echo '<td><label><input type="checkbox" disabled> Term time only</label></td>';
        echo '</tr></tbody></table></div>';
        echo '<p><button type="button" class="button" disabled>+ Add session</button> ';
        echo '<button type="button" class="button" disabled>Remove session</button></p>';
    }
    /** Legacy fallback: only venues explicitly owned by this activity. */
    private static function legacy_venue_sessions( $activity_id ) {
        global $wpdb;
        $tables = array();
        foreach ( array( 'bh_sessions', 'bh_venues' ) as $base ) {
            $found = array();
            foreach ( array_unique( array( $base, $wpdb->prefix . $base ) ) as $candidate ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) ) === $candidate ) {
                    $found[] = '`' . str_replace( '`', '``', $candidate ) . '`';
                }
            }
            if ( count( $found ) !== 1 ) {
                echo '<p>Legacy sessions unavailable: ' . esc_html( $base ) . ' has ' . esc_html( (string) count( $found ) ) . ' matching tables; exactly one is required.</p>';
                return;
            }
            $tables[$base] = $found[0];
        }
        $venue_columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $tables['bh_venues'], 0 );
        $session_columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $tables['bh_sessions'], 0 );
        if ( ! is_array( $venue_columns ) || ! is_array( $session_columns ) ||
            array_diff( array( 'id', 'activity_id' ), $venue_columns ) ||
            array_diff( array( 'venue_id', 'day_of_week', 'start_time', 'end_time' ), $session_columns ) ) {
            echo '<p>Legacy session relationship not verified. Venue ownership and timetable columns must be checked.</p>';
            return;
        }
        $fields = array_merge( array( 'venue_id', 'day_of_week', 'start_time', 'end_time' ),
            array_values( array_intersect( array( 'class_name', 'term_time_only', 'frequency', 'start_date', 'end_date', 'duration_minutes', 'price' ), $session_columns ) ) );
        $select = implode( ', ', array_map( static function( $field ) { return 's.`' . $field . '`'; }, $fields ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT ' . $select . ' FROM ' . $tables['bh_sessions'] . ' s INNER JOIN ' . $tables['bh_venues'] .
            ' v ON v.`id` = s.`venue_id` WHERE v.`activity_id` = %d LIMIT 100',
            absint( $activity_id )
        ), ARRAY_A );
        echo '<h4>Legacy venue-owned sessions (read-only, maximum 100)</h4>';
        if ( ! $rows ) { echo '<p>No legacy sessions found for venues explicitly assigned to this activity.</p>'; return; }
        echo '<p>Fallback data is shown only because no direct activity schedules were found. Shared venues without explicit activity ownership are excluded.</p>';
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
        foreach ( $fields as $field ) { echo '<th scope="col">' . esc_html( str_replace( '_', ' ', ucfirst( $field ) ) ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            foreach ( $fields as $field ) { echo '<td>' . esc_html( isset( $row[$field] ) ? (string) $row[$field] : '' ) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    /** Read only the direct activity schedule table documented in the legacy API. */
    private static function verified_session_rows( $activity_id ) {
        global $wpdb;
        $matches = array();
        foreach ( array( 'bh_activity_schedules' ) as $base ) {
            foreach ( array_unique( array( $base, $wpdb->prefix . $base ) ) as $candidate ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) ) !== $candidate ) { continue; }
                $quoted = '`' . str_replace( '`', '``', $candidate ) . '`';
                $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $quoted, 0 );
                if ( ! is_array( $columns ) || ! in_array( 'activity_id', $columns, true ) ) { continue; }
                $day = in_array( 'day_of_week', $columns, true ) ? 'day_of_week' : ( in_array( 'day', $columns, true ) ? 'day' : null );
                if ( ! $day || ! in_array( 'start_time', $columns, true ) || ! in_array( 'end_time', $columns, true ) ) { continue; }
                $matches[] = array( $quoted, $day, $columns );
            }
        }
        if ( count( $matches ) !== 1 ) {
            if ( ! $matches ) {
                echo '<p>No direct activity-owned schedule mapping found; checking the explicitly owned venue fallback.</p>';
                self::legacy_venue_sessions( $activity_id );
            } else {
                echo '<p>Multiple possible direct schedule tables found. No fallback is attempted until their relationships are reviewed.</p>';
            }
            return;
        }
        list( $table, $day, $columns ) = $matches[0];
        $extras = array_values( array_intersect( array( 'venue_id', 'status', 'term_time_only', 'frequency', 'start_date', 'end_date', 'duration_minutes', 'price' ), $columns ) );
        $select = array_merge( array( $day, 'start_time', 'end_time' ), $extras );
        $quoted_fields = implode( ', ', array_map( static function ( $field ) { return '`' . $field . '`'; }, $select ) );
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $quoted_fields . ' FROM ' . $table . ' WHERE `activity_id` = %d LIMIT 100', absint( $activity_id ) ), ARRAY_A );
        echo '<h4>Existing sessions (read-only, maximum 100)</h4>';
        if ( ! $rows ) { self::legacy_venue_sessions( $activity_id ); return; }
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
        foreach ( $select as $field ) { echo '<th scope="col">' . esc_html( str_replace( '_', ' ', ucfirst( $field ) ) ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            foreach ( $select as $field ) { echo '<td>' . esc_html( isset( $row[$field] ) ? (string) $row[$field] : '' ) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div><p>These rows are displayed as stored, without assuming recurrence or cancellation semantics.</p>';
    }
    private static function detail( $table, $columns, $id ) {
        global $wpdb;
        $allowed = array(
            'title' => 'Title', 'activity_name' => 'Activity name', 'name' => 'Name',
            'description' => 'Description', 'short_description' => 'Short description',
            'category' => 'Category', 'age_min_months' => 'Minimum age (months)',
            'age_max_months' => 'Maximum age (months)', 'price' => 'Price', 'price_from' => 'Price from',
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
        echo '<h2>Activity #' . esc_html( (string) $id ) . ' — editor layout preview</h2>';
        echo '<p>Field editing, timetable relationships and publishing actions will be enabled only after mapping and permission checks.</p>';
        $sections = array(
            'Listing details' => array( 'title', 'activity_name', 'name', 'description', 'short_description', 'status' ),
            'Categories & ages' => array( 'category', 'age_min_months', 'age_max_months' ),
            'Pricing & booking' => array( 'price', 'price_from', 'is_free', 'website', 'booking_url' ),
            'Venues & organiser' => array( 'venue_id', 'organiser_id' ),
        );
        echo '<div style="max-width:1000px">';
        foreach ( $sections as $heading => $section_fields ) {
            echo '<h3>' . esc_html( $heading ) . '</h3><table class="form-table" role="presentation"><tbody>';
            $shown = 0;
            foreach ( $section_fields as $field ) {
                if ( ! isset( $fields[$field] ) ) { continue; }
                $shown++;
                $value = isset( $row[$field] ) ? (string) $row[$field] : '';
                if ( $field === 'venue_id' && $value !== '' ) { $value = self::related_label( 'venue', $value ); }
                if ( $field === 'organiser_id' && $value !== '' ) { $value = self::related_label( 'organiser', $value ); }
                echo '<tr><th scope="row">' . esc_html( $fields[$field] ) . '</th><td>';
                if ( in_array( $field, array( 'description', 'short_description' ), true ) ) {
                    echo '<textarea class="large-text" rows="5" readonly aria-label="' . esc_attr( $fields[$field] ) . '">' . esc_textarea( $value ) . '</textarea>';
                } else {
                    echo '<input class="regular-text" type="text" readonly value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $fields[$field] ) . '">';
                }
                echo '</td></tr>';
            }
            if ( ! $shown ) { echo '<tr><td colspan="2">No mapped fields in this section yet.</td></tr>'; }
            echo '</tbody></table>';
        }
        echo '<h3>Find existing records</h3>';
        foreach ( array( 'venue' => 'bh3_preview_venue', 'organiser' => 'bh3_preview_organiser' ) as $kind => $parameter ) {
            $preview_id = isset( $_GET[$parameter] ) ? absint( $_GET[$parameter] ) : 0;
            if ( $preview_id ) {
                echo '<div class="notice notice-info inline"><p><strong>' . esc_html( ucfirst( $kind ) ) . ' selection preview:</strong> ' .
                    esc_html( self::related_label( $kind, $preview_id ) ) .
                    '. This is not saved or assigned to the activity.</p></div>';
            }
        }
        self::related_search( 'venue' );
        self::related_search( 'organiser' );
        echo '<h3>Images</h3><p>Image library and upload controls will be connected after media field mapping.</p>';
        echo '<h3>Session timetable</h3>';
        $sessions_table = $wpdb->prefix . 'sessions';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) ) === $sessions_table ) {
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT day_of_week, start_time, end_time, venue_id, frequency, term_time_only, status FROM `{$sessions_table}` WHERE activity_id = %d ORDER BY day_of_week, start_time LIMIT 100",
                $id
            ), ARRAY_A );
            echo '<h4>Sessions (read-only)</h4>';
            if ( ! $sessions ) { echo '<p>No sessions yet.</p>'; }
            else {
                echo '<table class="widefat striped"><thead><tr><th>Day</th><th>Start</th><th>End</th><th>Venue ID</th><th>Frequency</th><th>Term time</th><th>Status</th></tr></thead><tbody>';
                foreach ( $sessions as $session ) {
                    echo '<tr>';
                    foreach ( $session as $value ) { echo '<td>' . esc_html( (string) $value ) . '</td>'; }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        } else { echo '<p>New sessions table not found.</p>'; }
        self::timetable_layout_preview();
        echo '<details><summary>Database mapping readiness</summary>';
        echo '<p>Fresh WordPress session mapping selected; legacy timetable queries are not used.</p>';
        echo '</details>';
        echo '<p><button type="button" class="button button-primary" disabled>Save changes (not available yet)</button></p></div>';
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ) ); }
        global $wpdb;
        echo '<div class="wrap"><h1>Activities</h1><p>Read-only listing browser for the new WordPress activities table. Editing, publishing and deletion are not enabled.</p>';
        $table = self::table();
        if ( ! $table ) {
            echo '<div class="notice notice-warning inline"><p>New activities table not found on this WordPress database connection.</p></div></div>';
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
                echo '<tr><td>' . esc_html( (string) $row['activity_id'] ) . '</td><td>' . esc_html( (string) $row['activity_title'] ) . '</td><td><a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh3_activity_id' => absint( $row['activity_id'] ) ), admin_url( 'admin.php' ) ) ) . '">View details</a> | <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-edit-activity', 'activity_id' => absint( $row['activity_id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit draft</a> | <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activity-venues', 'activity_id' => absint( $row['activity_id'] ) ), admin_url( 'admin.php' ) ) ) . '">Venues</a></td></tr>';
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
