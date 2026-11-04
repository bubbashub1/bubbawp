<?php
/** Read-only staging directory backed by the new WordPress activity tables. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Directory_3 {
    public static function register() {
        add_shortcode( 'bubbahub_directory', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_map_styles' ) );
    }
    public static function enqueue_map_styles() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        global $post;
        if ( ! $post instanceof WP_Post || ! has_shortcode( (string) $post->post_content, 'bubbahub_directory' ) ) { return; }
        wp_enqueue_style( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
        wp_enqueue_style( 'bh3-leaflet-cluster', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array( 'bh3-leaflet' ), '1.5.3' );
        wp_enqueue_style( 'bh3-leaflet-cluster-default', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array( 'bh3-leaflet-cluster' ), '1.5.3' );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return '<p>Directory integration is available on staging only.</p>';
        }
        global $wpdb;
        $activities = $wpdb->prefix . 'activities';
        $organisers = $wpdb->prefix . 'organisers';
        $links = $wpdb->prefix . 'activity_venues';
        $venues = $wpdb->prefix . 'venues';
        foreach ( array( $activities, $organisers, $links, $venues, $wpdb->prefix . 'sessions' ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return '<p>Directory tables are not ready on staging.</p>';
            }
        }
        $builder = class_exists( 'BH_Directory_Builder_3' ) ? BH_Directory_Builder_3::settings() : array();
        $filters = isset( $builder['search_fields'] ) && is_array( $builder['search_fields'] ) ? $builder['search_fields'] : array( 'keyword', 'category', 'town', 'free' );
        $show = static function( $key ) use ( $filters ) { return in_array( $key, $filters, true ); };
        $age = isset( $_GET['bh_age'] ) && is_scalar( $_GET['bh_age'] ) ? min( 216, max( 0, absint( $_GET['bh_age'] ) ) ) : null;
        $day = isset( $_GET['bh_day'] ) && is_scalar( $_GET['bh_day'] ) ? absint( $_GET['bh_day'] ) : 0;
        $day = $day >= 1 && $day <= 7 ? $day : 0;
        $region = isset( $_GET['bh_region'] ) && is_scalar( $_GET['bh_region'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_region'] ) ), 0, 100 ) : '';
        $term = isset( $_GET['bh_term'] ) && (string) $_GET['bh_term'] === '1';
        $postcode = isset( $_GET['bh_postcode'] ) && is_scalar( $_GET['bh_postcode'] ) ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['bh_postcode'] ) ) ), 0, 12 ) : '';
        $search = isset( $_GET['bh_search'] ) && is_string( $_GET['bh_search'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ), 0, 100 ) : '';
        $category = isset( $_GET['bh_category'] ) && is_string( $_GET['bh_category'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_category'] ) ), 0, 150 ) : '';
        $town = isset( $_GET['bh_town'] ) && is_string( $_GET['bh_town'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_town'] ) ), 0, 120 ) : '';
        $sort = isset( $_GET['bh_sort'] ) && is_scalar( $_GET['bh_sort'] ) ? sanitize_key( wp_unslash( $_GET['bh_sort'] ) ) : 'title';
        if ( ! in_array( $sort, array( 'title', 'town', 'newest' ), true ) ) { $sort = 'title'; }
        $geo_lat = isset( $_GET['bh_lat'] ) && is_scalar( $_GET['bh_lat'] ) && is_numeric( $_GET['bh_lat'] ) ? (float) $_GET['bh_lat'] : null;
        $geo_lng = isset( $_GET['bh_lng'] ) && is_scalar( $_GET['bh_lng'] ) && is_numeric( $_GET['bh_lng'] ) ? (float) $_GET['bh_lng'] : null;
        $geo_valid = $geo_lat !== null && $geo_lng !== null && is_finite( $geo_lat ) && is_finite( $geo_lng ) && abs( $geo_lat ) <= 90 && abs( $geo_lng ) <= 180;
        $radius = isset( $_GET['bh_radius'] ) && is_scalar( $_GET['bh_radius'] ) ? absint( $_GET['bh_radius'] ) : 10;
        if ( ! in_array( $radius, array( 5, 10, 20, 50 ), true ) ) { $radius = 10; }
        $free = isset( $_GET['bh_free'] ) && (string) $_GET['bh_free'] === '1';
        $max_price = isset( $_GET['bh_max_price'] ) && is_scalar( $_GET['bh_max_price'] ) && in_array( (string) $_GET['bh_max_price'], array( '5', '10', '20', '50' ), true ) ? absint( $_GET['bh_max_price'] ) : 0;
        $page = min( 100, max( 1, absint( $_GET['bh_page'] ?? 1 ) ) );
        $conditions = array( "a.status IN ('published','publish')" );
        $params = array();
        if ( $search !== '' ) { $conditions[] = '(a.title LIKE %s OR a.description LIKE %s OR EXISTS (SELECT 1 FROM `' . $organisers . '` os WHERE os.id = a.organiser_id AND os.name LIKE %s))'; $pattern = '%' . $wpdb->esc_like( $search ) . '%'; $params[] = $pattern; $params[] = $pattern; $params[] = $pattern; }
        if ( $category !== '' ) { $conditions[] = 'a.category = %s'; $params[] = $category; }
        if ( $town !== '' ) { $conditions[] = 'EXISTS (SELECT 1 FROM `' . $links . '` av2 INNER JOIN `' . $venues . '` v2 ON v2.id = av2.venue_id WHERE av2.activity_id = a.id AND v2.town LIKE %s)'; $params[] = '%' . $wpdb->esc_like( $town ) . '%'; }
        if ( $free ) { $conditions[] = 'a.is_free = 1'; }
        if ( $max_price && ! $free ) { $conditions[] = '(a.is_free = 1 OR (a.price_from IS NOT NULL AND a.price_from <= %d))'; $params[] = $max_price; }
        if ( $age !== null ) {
            $conditions[] = '(a.age_min_months IS NULL OR a.age_min_months <= %d) AND (a.age_max_months IS NULL OR a.age_max_months >= %d)';
            $params[] = $age; $params[] = $age;
        }
        if ( $day ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $wpdb->prefix . 'sessions` sd WHERE sd.activity_id=a.id AND sd.day_of_week=%d)';
            $params[] = $day;
        }
        if ( $term ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $wpdb->prefix . 'sessions` st WHERE st.activity_id=a.id AND st.term_time_only=1)';
        }
        if ( $region !== '' ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $links . '` ar INNER JOIN `' . $venues . '` vr ON vr.id=ar.venue_id WHERE ar.activity_id=a.id AND vr.region=%s)';
            $params[] = $region;
        }
        if ( $postcode !== '' ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $links . '` ap INNER JOIN `' . $venues . '` vp ON vp.id=ap.venue_id WHERE ap.activity_id=a.id AND vp.postcode LIKE %s)';
            $params[] = $wpdb->esc_like( $postcode ) . '%';
        }
        if ( $geo_valid ) {
            // Coarse bounding box first, then exact great-circle distance. Coordinates are request-only.
            $lat_delta = $radius / 69.0;
            $lng_delta = $radius / max( 0.01, 69.0 * cos( deg2rad( $geo_lat ) ) );
            $distance = '(3958.7613 * 2 * ASIN(SQRT(POW(SIN(RADIANS(vn.latitude - %f) / 2),2) + COS(RADIANS(%f)) * COS(RADIANS(vn.latitude)) * POW(SIN(RADIANS(vn.longitude - %f) / 2),2))))';
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $links . '` an INNER JOIN `' . $venues . '` vn ON vn.id=an.venue_id WHERE an.activity_id=a.id AND vn.latitude BETWEEN %f AND %f AND vn.longitude BETWEEN %f AND %f AND ' . $distance . ' <= %f)';
            array_push( $params, max( -90, $geo_lat - $lat_delta ), min( 90, $geo_lat + $lat_delta ), max( -180, $geo_lng - $lng_delta ), min( 180, $geo_lng + $lng_delta ), $geo_lat, $geo_lat, $geo_lng, $radius );
        }
        $where = implode( ' AND ', $conditions );
        $order = $sort === 'newest' ? 'a.id DESC' : ( $sort === 'town' ? 'town ASC, a.title ASC' : 'a.title ASC' );
        $sql = "SELECT a.id,a.title,a.slug,a.description,a.category,a.age_min_months,a.age_max_months,a.price_from,a.is_free,o.name AS organiser,
            (SELECT v.town FROM `{$links}` av INNER JOIN `{$venues}` v ON v.id=av.venue_id WHERE av.activity_id=a.id ORDER BY v.name LIMIT 1) AS town
            FROM `{$activities}` a LEFT JOIN `{$organisers}` o ON o.id=a.organiser_id
            WHERE {$where} ORDER BY {$order} LIMIT 13 OFFSET " . ( ( $page - 1 ) * 12 );
        $rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) { return '<p>Unable to load the directory.</p>'; }
        $more = count( $rows ) > 12;
        $rows = array_slice( $rows, 0, 12 );
        $map_points = array();
        $row_ids = array_map( 'intval', array_column( $rows, 'id' ) );
        $sessions_by_activity = array();
        if ( $row_ids ) {
            $session_ids = implode( ',', array_fill( 0, count( $row_ids ), '%d' ) );
            $session_table = $wpdb->prefix . 'sessions';
            $session_sql = "SELECT activity_id,day_of_week,LEFT(start_time,5) AS start_time FROM `{$session_table}`
                WHERE activity_id IN ({$session_ids}) AND frequency='weekly' AND status='scheduled'
                AND day_of_week BETWEEN 1 AND 7 AND start_time IS NOT NULL
                ORDER BY activity_id,day_of_week,start_time LIMIT 1000";
            foreach ( (array) $wpdb->get_results( $wpdb->prepare( $session_sql, $row_ids ), ARRAY_A ) as $session ) {
                $activity_id = (int) $session['activity_id'];
                $day_number = (int) $session['day_of_week'];
                $time = (string) $session['start_time'];
                if ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) { continue; }
                $day_names = array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' );
                $hour = (int) substr( $time, 0, 2 );
                $minute = substr( $time, 3, 2 );
                $display_time = ( $hour % 12 ?: 12 ) . ( $minute === '00' ? '' : ':' . $minute ) . ( $hour >= 12 ? 'pm' : 'am' );
                $label = $day_names[ $day_number ] . ' ' . $display_time;
                if ( ! isset( $sessions_by_activity[ $activity_id ] ) ) { $sessions_by_activity[ $activity_id ] = array(); }
                if ( ! in_array( $label, $sessions_by_activity[ $activity_id ], true ) ) { $sessions_by_activity[ $activity_id ][] = $label; }
            }
        }
        if ( $row_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $row_ids ), '%d' ) );
            $geo_sql = "SELECT av.activity_id,v.name,v.town,v.latitude,v.longitude FROM `{$links}` av
                INNER JOIN `{$venues}` v ON v.id=av.venue_id
                WHERE av.activity_id IN ({$placeholders}) AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL
                ORDER BY av.activity_id,v.name LIMIT 100";
            $geo_rows = $wpdb->get_results( $wpdb->prepare( $geo_sql, $row_ids ), ARRAY_A );
            $names = array();
            foreach ( $rows as $row ) { $names[ (int) $row['id'] ] = (string) $row['title']; }
            foreach ( (array) $geo_rows as $geo ) {
                $lat = (float) $geo['latitude']; $lng = (float) $geo['longitude'];
                if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) { continue; }
                $map_points[] = array(
                    'lat' => $lat, 'lng' => $lng, 'activity_id' => (int) $geo['activity_id'],
                    'title' => $names[ (int) $geo['activity_id'] ] ?? '',
                    'venue' => (string) $geo['name'],
                    'town' => (string) $geo['town'],
                );
            }
        }
        ob_start();
        echo '<section class="bh3-real-directory" aria-label="Family activities directory"><style>
        .bh3-real-directory{max-width:1240px;margin:0 auto;padding:24px 16px;color:#173c34}
        .bh3-real-directory *{box-sizing:border-box}.bh3-real-directory .bh3-banner{background:#e7f3eb;border-radius:18px;padding:28px;margin-bottom:22px}
        .bh3-real-directory h2{margin:0 0 8px}.bh3-real-directory .bh3-search-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:14px;margin:22px 0;padding:20px;background:#fff;border:1px solid #d7e3dc;border-radius:16px;box-shadow:0 8px 26px rgba(22,66,48,.05)}.bh3-real-directory .bh3-search-form label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:650}.bh3-real-directory .bh3-search-form .bh3-check{flex-direction:row;align-items:center;align-self:end;padding:13px 0}.bh3-real-directory .bh3-search-form .bh3-check input{min-width:auto;flex:none}.bh3-real-directory .bh3-search-form select{width:100%;min-height:45px;border:1px solid #cbd8d0;border-radius:9px}.bh3-real-directory .bh3-search-actions{display:flex;align-items:center;gap:14px;align-self:end}.bh3-real-directory .bh3-search-actions a{white-space:nowrap}
        .bh3-real-directory input{min-width:130px;flex:1;padding:12px;border:1px solid #cbd8d0;border-radius:9px}
        .bh3-real-directory button{background:#175c46;color:white;border:0;border-radius:9px;padding:12px 20px;cursor:pointer}
        .bh3-real-directory .bh3-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .bh3-real-directory article{background:#fff;border:1px solid #d7e3dc;border-radius:14px;padding:20px;scroll-margin-top:90px;transition:border-color .2s,box-shadow .2s,background .2s}
        .bh3-real-directory article.bh3-card-active{border-color:#176345;background:#f2f9f4;box-shadow:0 0 0 3px rgba(23,99,69,.16)}
        .bh3-real-directory .bh3-map-note{margin:8px 0 18px;color:#50675e;font-size:14px}
        .bh3-real-directory .bh3-view-toggle{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}
        .bh3-real-directory .bh3-view-toggle button{background:#fff;color:#175c46;border:1px solid #bcd9c5;min-height:44px;font-weight:650}
        .bh3-real-directory .bh3-view-toggle button[aria-pressed=true]{background:#175c46;color:#fff}
        .bh3-real-directory.bh3-view-list .bh3-map-section{display:none}
        .bh3-real-directory.bh3-view-map .bh3-cards,.bh3-real-directory.bh3-view-map .bh3-pages{display:none}
        .bh3-real-directory .bh3-map:focus-visible,.bh3-real-directory button:focus-visible{outline:3px solid #74b38b;outline-offset:3px}
        .bh3-real-directory .bh3-map-card-link{display:inline-block;margin-top:8px;padding:7px 10px;border-radius:7px;border:1px solid #bcd9c5;background:#f4faf6;color:#175c46;font-weight:700;cursor:pointer}
        .bh3-real-directory article h3{margin:8px 0}.bh3-real-directory .bh3-card-sessions{margin:12px 0;font-size:12px;color:#315b46}.bh3-real-directory .bh3-card-session-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}.bh3-real-directory .bh3-card-session-list span{border:1px solid #c8ded0;background:#f5faf6;padding:5px 8px;border-radius:7px;white-space:nowrap}
        .bh3-real-directory .bh3-card-facts{display:flex;flex-wrap:wrap;gap:7px;margin:12px 0}.bh3-real-directory .bh3-card-facts span{display:inline-flex;align-items:center;background:#f0f7f2;border:1px solid #dce9df;border-radius:999px;padding:5px 9px;font-size:12px;color:#315b46}.bh3-real-directory article{min-width:0;overflow-wrap:anywhere}.bh3-real-directory .bh3-meta{color:#50675e;font-size:14px}
        .bh3-real-directory .bh3-pages{display:flex;gap:16px;margin-top:24px}
        .bh3-real-directory .bh3-map{border-radius:14px;overflow:hidden;border:1px solid #d7e3dc;margin:16px 0;z-index:0}
        .bh3-real-directory .bh3-map-message{font-size:14px;color:#50675e}
        @media(max-width:850px){.bh3-real-directory .bh3-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.bh3-real-directory .bh3-cards{grid-template-columns:1fr}}
        </style><div class="bh3-banner"><h2>Discover activities for your family</h2><p>Baby groups, toddler classes and family activities across Devon &amp; Cornwall.</p></div>';
        echo '<form class="bh3-search-form" method="get" aria-label="Filter activities">';
        if ( $show( 'keyword' ) ) { echo '<label><span>Search</span><input type="search" name="bh_search" placeholder="Activity, organiser or keyword" value="' . esc_attr( $search ) . '"></label>'; }
        if ( $show( 'category' ) ) { echo '<label><span>Category</span><input type="search" name="bh_category" placeholder="e.g. Swimming" value="' . esc_attr( $category ) . '"></label>'; }
        if ( $show( 'price' ) ) { echo '<label><span>Maximum price</span><select name="bh_max_price"><option value="">Any price</option>'; foreach ( array( 5, 10, 20, 50 ) as $limit ) { echo '<option value="' . esc_attr( (string) $limit ) . '" ' . selected( $max_price, $limit, false ) . '>Up to £' . esc_html( (string) $limit ) . '</option>'; } echo '</select></label>'; }
        if ( $show( 'town' ) ) { echo '<label><span>Town</span><input type="search" name="bh_town" placeholder="Town" value="' . esc_attr( $town ) . '"></label>'; }
        if ( $show( 'region' ) ) { echo '<label><span>Region</span><select name="bh_region"><option value="">All regions</option>';
            foreach ( array( 'Devon', 'Cornwall' ) as $choice ) { echo '<option value="' . esc_attr( $choice ) . '" ' . selected( $region, $choice, false ) . '>' . esc_html( $choice ) . '</option>'; }
            echo '</select></label>'; }
        if ( $show( 'age' ) ) { echo '<label><span>Child age (months)</span><input type="number" name="bh_age" min="0" max="216" placeholder="Any age" value="' . esc_attr( $age === null ? '' : (string) $age ) . '"></label>'; }
        if ( $show( 'day' ) ) { echo '<label><span>Day</span><select name="bh_day"><option value="">Any day</option>';
            foreach ( array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' ) as $num => $label ) { echo '<option value="' . esc_attr( $num ) . '" ' . selected( $day, $num, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select></label>'; }
        if ( $show( 'near_me' ) ) { echo '<label><span>Postcode</span><input type="search" name="bh_postcode" placeholder="Postcode prefix" value="' . esc_attr( $postcode ) . '"></label>'; }
        if ( $show( 'free' ) ) { echo '<label class="bh3-check"><input type="checkbox" name="bh_free" value="1" ' . checked( $free, true, false ) . '> Free only</label>'; }
        if ( $show( 'term_time' ) ) { echo '<label class="bh3-check"><input type="checkbox" name="bh_term" value="1" ' . checked( $term, true, false ) . '> Term time</label>'; }
        echo '<label><span>Sort activities</span><select name="bh_sort"><option value="title" ' . selected( $sort, 'title', false ) . '>Name A–Z</option><option value="town" ' . selected( $sort, 'town', false ) . '>Town A–Z</option><option value="newest" ' . selected( $sort, 'newest', false ) . '>Recently added</option></select></label>';
        if ( $show( 'near_me' ) ) {
            echo '<label><span>Within</span><select name="bh_radius">';
            foreach ( array( 5, 10, 20, 50 ) as $miles ) { echo '<option value="' . esc_attr( $miles ) . '" ' . selected( $radius, $miles, false ) . '>' . esc_html( $miles ) . ' miles</option>'; }
            echo '</select></label><div class="bh3-search-actions"><button type="button" class="bh3-locate" data-bh3-locate>Use my location</button><span class="bh3-location-status" role="status" aria-live="polite"></span></div>';
            if ( $geo_valid ) { echo '<input type="hidden" name="bh_lat" value="' . esc_attr( (string) $geo_lat ) . '"><input type="hidden" name="bh_lng" value="' . esc_attr( (string) $geo_lng ) . '">'; }
        }
        echo '<div class="bh3-search-actions"><button type="submit">Find activities</button><a href="' . esc_url( remove_query_arg( array( 'bh_search', 'bh_category', 'bh_town', 'bh_region', 'bh_age', 'bh_day', 'bh_postcode', 'bh_free', 'bh_max_price', 'bh_term', 'bh_sort', 'bh_radius', 'bh_lat', 'bh_lng', 'bh_page' ) ) ) . '">Clear filters</a></div></form>';
        if ( $map_points ) { echo '<div class="bh3-view-toggle" role="group" aria-label="Directory view"><button type="button" data-bh3-view="both" aria-pressed="true">Map &amp; list</button><button type="button" data-bh3-view="map" aria-pressed="false">Map only</button><button type="button" data-bh3-view="list" aria-pressed="false">List only</button></div>'; }
        echo '<section class="bh3-map-section" aria-label="Activity locations"><h3>Activities on the map</h3>';
        if ( $map_points ) {
            $map_id = 'bh3-map-' . wp_unique_id();
            $height = min( 750, max( 240, (int) ( $builder['map_height'] ?? 420 ) ) );
            echo '<div id="' . esc_attr( $map_id ) . '" class="bh3-map" style="height:' . esc_attr( $height ) . 'px" role="region" aria-label="Map of activity venues"></div>';
            wp_enqueue_style( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
            wp_enqueue_style( 'bh3-leaflet-cluster', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array( 'bh3-leaflet' ), '1.5.3' );
            wp_enqueue_style( 'bh3-leaflet-cluster-default', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array( 'bh3-leaflet-cluster' ), '1.5.3' );
            wp_enqueue_script( 'bh3-leaflet-cluster', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array( 'bh3-leaflet' ), '1.5.3', true );
            $payload = array(
                'id' => $map_id,
                'points' => $map_points,
                'center' => array( (float) ( $builder['map_lat'] ?? 50.53 ), (float) ( $builder['map_lng'] ?? -3.55 ) ),
                'zoom' => min( 18, max( 3, (int) ( $builder['map_zoom'] ?? 11 ) ) ),
            );
            wp_add_inline_script( 'bh3-leaflet-cluster', '(function(){const cfg=' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';function init(){const el=document.getElementById(cfg.id);if(!el||!window.L)return;const map=L.map(el,{scrollWheelZoom:false}).setView(cfg.center,cfg.zoom);const scope=el.closest(".bh3-real-directory");if(scope){const controls=[...scope.querySelectorAll("[data-bh3-view]")];controls.forEach(button=>button.addEventListener("click",()=>{const view=button.dataset.bh3View;scope.classList.toggle("bh3-view-map",view==="map");scope.classList.toggle("bh3-view-list",view==="list");controls.forEach(b=>b.setAttribute("aria-pressed",String(b===button)));if(view!=="list")setTimeout(()=>map.invalidateSize(),60)}))}L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);const bounds=[];const clusters=L.markerClusterGroup?L.markerClusterGroup({showCoverageOnHover:false,maxClusterRadius:48}):null;if(clusters)map.addLayer(clusters);cfg.points.forEach(p=>{const marker=L.marker([p.lat,p.lng]);if(clusters)clusters.addLayer(marker);else marker.addTo(map);const wrap=document.createElement("div");const title=document.createElement("strong");title.textContent=p.title;const desc=document.createElement("p");desc.textContent=[p.venue,p.town].filter(Boolean).join(", ");const action=document.createElement("button");action.type="button";action.className="bh3-map-card-link";action.textContent="Show listing";action.addEventListener("click",()=>{const scope=el.closest(".bh3-real-directory"),card=[...scope.querySelectorAll("[data-activity-id]")].find(x=>x.dataset.activityId===String(p.activity_id));if(!card)return;scope.querySelectorAll(".bh3-card-active").forEach(x=>x.classList.remove("bh3-card-active"));card.classList.add("bh3-card-active");if(scope.classList.contains("bh3-view-map")){scope.classList.remove("bh3-view-map");const both=[...scope.querySelectorAll("[data-bh3-view]")].find(x=>x.dataset.bh3View==="both");scope.querySelectorAll("[data-bh3-view]").forEach(b=>b.setAttribute("aria-pressed",String(b===both)))}card.scrollIntoView({behavior:"smooth",block:"center"});card.setAttribute("tabindex","-1");card.focus({preventScroll:true})});wrap.append(title,desc,action);marker.bindPopup(wrap);bounds.push([p.lat,p.lng])});if(bounds.length)map.fitBounds(bounds,{padding:[25,25],maxZoom:cfg.zoom});setTimeout(()=>map.invalidateSize(),100)}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();', 'after' );
        } else { echo '<p class="bh3-map-message">No mapped venues for the activities on this page. Listings are available below.</p>'; }
        echo '<p class="bh3-map-message">Map data © OpenStreetMap contributors. Use the list below for full activity details.</p></section>';
        echo '<p class="bh3-map-note">Select a map pin, then choose “Show listing” to highlight its activity card below.</p><div class="bh3-cards">';
        foreach ( $rows as $row ) {
            $detail_page = get_page_by_path( 'activity-preview' );
            $detail_url = $detail_page && has_shortcode( (string) $detail_page->post_content, 'bubbahub_activity' )
                ? add_query_arg( 'bh_activity', absint( $row['id'] ), get_permalink( $detail_page ) ) : '';
            echo '<article data-activity-id="' . esc_attr( (string) absint( $row['id'] ) ) . '"><div class="bh3-meta">' . esc_html( (string) $row['category'] ) . '</div><h3>' . esc_html( $row['title'] ) . '</h3>';
            if ( $detail_url ) { echo '<p><a href="' . esc_url( $detail_url ) . '">View activity details</a></p>'; }
            echo '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $row['description'] ), 24 ) ) . '</p>';
            echo '<div class="bh3-card-facts">';
            if ( $row['town'] ) { echo '<span>📍 ' . esc_html( (string) $row['town'] ) . '</span>'; }
            if ( $row['age_min_months'] !== null || $row['age_max_months'] !== null ) {
                $age_min = $row['age_min_months'] !== null ? (int) $row['age_min_months'] : null;
                $age_max = $row['age_max_months'] !== null ? (int) $row['age_max_months'] : null;
                $age_label = $age_min !== null && $age_max !== null ? $age_min . '–' . $age_max . ' months' : ( $age_min !== null ? $age_min . '+ months' : 'Up to ' . $age_max . ' months' );
                echo '<span>Age: ' . esc_html( $age_label ) . '</span>';
            }
            echo '</div>';
            if ( $row['organiser'] ) { echo '<p class="bh3-meta">By ' . esc_html( $row['organiser'] ) . '</p>'; }
            $session_labels = $sessions_by_activity[ (int) $row['id'] ] ?? array();
            if ( $session_labels ) {
                echo '<div class="bh3-card-sessions" aria-label="Weekly session times"><strong>Weekly sessions</strong><div class="bh3-card-session-list">';
                foreach ( array_slice( $session_labels, 0, 3 ) as $session_label ) { echo '<span>' . esc_html( $session_label ) . '</span>'; }
                if ( count( $session_labels ) > 3 ) { echo '<span aria-label="' . esc_attr( (string) ( count( $session_labels ) - 3 ) . ' additional weekly session times' ) . '">+' . esc_html( (string) ( count( $session_labels ) - 3 ) ) . ' more</span>'; }
                echo '</div></div>';
            }
            echo '<strong>' . ( (int) $row['is_free'] ? 'Free' : ( $row['price_from'] !== null ? 'From £' . esc_html( number_format( (float) $row['price_from'], 2 ) ) : 'Price on enquiry' ) ) . '</strong></article>';
        }
        if ( ! $rows ) { echo '<p>No published activities match these filters. Draft listings are not public.</p>'; }
        echo '</div><nav class="bh3-pages" aria-label="Directory pages">';
        $base = remove_query_arg( 'bh_page' );
        if ( $page > 1 ) { echo '<a href="' . esc_url( add_query_arg( 'bh_page', $page - 1, $base ) ) . '">Previous</a>'; }
        if ( $more ) { echo '<a href="' . esc_url( add_query_arg( 'bh_page', $page + 1, $base ) ) . '">Next</a>'; }
        echo '</nav></section>';
        return ob_get_clean();
    }
}
