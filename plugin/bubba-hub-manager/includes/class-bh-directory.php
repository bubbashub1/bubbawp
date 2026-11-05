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
        $sql = "SELECT a.id,a.title,a.slug,a.description,a.category,a.image_path,a.age_min_months,a.age_max_months,a.price_from,a.is_free,o.name AS organiser,
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
            $session_sql = "SELECT activity_id,day_of_week,LEFT(start_time,5) AS start_time,term_time_only FROM `{$session_table}`
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
                $label = $day_names[ $day_number ] . ' ' . $display_time . ( ! empty( $session['term_time_only'] ) ? ' · Term time' : '' );
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
        .bh3-real-directory .bh3-view-toggle button[aria-pressed=true]{background:#175c46;color:#fff}@media(max-width:560px){.bh3-real-directory .bh3-view-toggle{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:5px;background:#f1f7f3;border:1px solid #d5e5da;border-radius:12px}.bh3-real-directory .bh3-view-toggle button{width:100%;padding:10px 5px;min-height:46px;font-size:13px;border-radius:9px}.bh3-real-directory .bh3-map{max-height:55vh}}
        .bh3-real-directory .bh3-map-section,.bh3-real-directory .bh3-calendar-section{display:none}.bh3-real-directory.bh3-view-map .bh3-map-section,.bh3-real-directory.bh3-view-calendar .bh3-calendar-section{display:block}.bh3-real-directory.bh3-view-calendar .bh3-cards,.bh3-real-directory.bh3-view-calendar .bh3-pages,.bh3-real-directory.bh3-view-calendar .bh3-map-note,.bh3-real-directory.bh3-view-calendar .bh3-results-summary{display:none}.bh3-real-directory .bh3-planner-tab{display:inline-flex;align-items:center;padding:12px 18px;border:1px solid #bcd9c5;border-radius:9px;color:#175c46;text-decoration:none;font-weight:650}.bh3-real-directory .bh3-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}.bh3-real-directory .bh3-calendar-toolbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}.bh3-real-directory .bh3-calendar-toolbar>div{display:flex;align-items:center;gap:10px}.bh3-real-directory .bh3-calendar-toolbar button{padding:8px 12px}.bh3-real-directory .bh3-calendar-toolbar button[aria-pressed=true]{background:#175c46}.bh3-real-directory .bh3-calendar-limit{margin:10px 0;color:#73521b;font-weight:650}.bh3-real-directory .bh3-calendar-outside{opacity:.45}.bh3-real-directory .bh3-calendar-day{min-width:0;border:1px solid #d7e3dc;border-radius:12px;padding:10px;background:#fff}.bh3-real-directory .bh3-calendar-event{display:block;padding:7px;margin-top:7px;background:#e7f3eb;border-radius:7px;color:#175c46;font-size:13px;overflow-wrap:anywhere}@media(max-width:720px){.bh3-real-directory .bh3-calendar-grid{grid-template-columns:1fr}.bh3-real-directory .bh3-view-toggle{display:flex;flex-wrap:wrap}}
        .bh3-real-directory.bh3-view-map .bh3-cards,.bh3-real-directory.bh3-view-map .bh3-pages,.bh3-real-directory.bh3-view-map .bh3-results-summary,.bh3-real-directory.bh3-view-map .bh3-map-note{display:none}.bh3-real-directory.bh3-view-list .bh3-map-note{display:none}
        .bh3-real-directory .bh3-map:focus-visible,.bh3-real-directory button:focus-visible{outline:3px solid #74b38b;outline-offset:3px}
        .bh3-real-directory .bh3-map-card-link{display:inline-block;margin-top:8px;padding:7px 10px;border-radius:7px;border:1px solid #bcd9c5;background:#f4faf6;color:#175c46;font-weight:700;cursor:pointer}
        .bh3-real-directory article h3{margin:8px 0}.bh3-real-directory .bh3-card-image{width:100%;aspect-ratio:16/10;overflow:hidden;background:#e9f2ec;border-radius:10px;margin-bottom:14px}.bh3-real-directory .bh3-card-image img{display:block;width:100%;height:100%;object-fit:cover}.bh3-real-directory .bh3-card-image-empty{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f4e9,#cfe6d8);color:#47765e}.bh3-real-directory .bh3-card-image-empty span{font-size:28px;font-weight:800;letter-spacing:-2px;opacity:.55}.bh3-real-directory .bh3-card-title-link{color:inherit;text-decoration:none}.bh3-real-directory .bh3-card-title-link:hover{text-decoration:underline}.bh3-real-directory .bh3-card-title-link:focus-visible,.bh3-real-directory .bh3-card-cta:focus-visible{outline:3px solid #d59b38;outline-offset:3px}.bh3-real-directory .bh3-card-facts{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.bh3-real-directory .bh3-fact{display:inline-flex;align-items:center;gap:3px;border-radius:8px;background:#eef6f0;padding:6px 9px;color:#254c3b;font-size:12px;font-weight:650;line-height:1.35}.bh3-real-directory .bh3-card-price{font-size:17px;color:#175c46;white-space:nowrap}@media(max-width:560px){.bh3-real-directory article{padding:14px}.bh3-real-directory article h3{font-size:18px;line-height:1.3}.bh3-real-directory .bh3-card-facts{gap:6px}.bh3-real-directory .bh3-card-sessions{margin:10px 0}.bh3-real-directory .bh3-card-footer{border-top:1px solid #e4eee7;padding-top:12px}.bh3-real-directory .bh3-card-cta{min-height:44px;justify-content:center}}.bh3-real-directory .bh3-card-footer{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-top:14px}.bh3-real-directory .bh3-card-cta{display:inline-flex;align-items:center;gap:5px;background:#175c46;color:#fff;text-decoration:none;border-radius:8px;padding:9px 12px;font-size:13px;font-weight:650}.bh3-real-directory .bh3-card-cta:hover{background:#104632}.bh3-real-directory .bh3-card-sessions{margin:12px 0;font-size:12px;color:#315b46}.bh3-real-directory .bh3-card-session-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}.bh3-real-directory .bh3-card-session-list span{border:1px solid #c8ded0;background:#f5faf6;padding:5px 8px;border-radius:7px;white-space:nowrap}
        .bh3-real-directory .bh3-card-facts{display:flex;flex-wrap:wrap;gap:7px;margin:12px 0}.bh3-real-directory .bh3-card-facts span{display:inline-flex;align-items:center;background:#f0f7f2;border:1px solid #dce9df;border-radius:999px;padding:5px 9px;font-size:12px;color:#315b46}.bh3-real-directory article{min-width:0;overflow-wrap:anywhere}.bh3-real-directory .bh3-cards>article{display:flex;flex-direction:column;min-height:100%;box-shadow:0 3px 12px rgba(23,60,52,.045)}.bh3-real-directory .bh3-cards>article:hover{border-color:#a8cdb5;box-shadow:0 9px 24px rgba(23,60,52,.10)}.bh3-real-directory .bh3-cards>article h3{font-size:18px;line-height:1.3;letter-spacing:-.01em}.bh3-real-directory .bh3-cards>article>p:not(.bh3-meta){font-size:14px;line-height:1.55;color:#39544a}.bh3-real-directory .bh3-cards>article .bh3-card-footer{margin-top:auto;padding-top:16px;border-top:1px solid #e4eee8}.bh3-real-directory .bh3-card-footer>strong{font-size:16px;color:#174d3a}.bh3-real-directory .bh3-card-cta{min-height:42px;justify-content:center}.bh3-real-directory .bh3-cards>article .bh3-card-image{flex-shrink:0}.bh3-real-directory .bh3-meta{color:#50675e;font-size:14px}
        .bh3-real-directory .bh3-pages{display:flex;gap:16px;margin-top:24px}.bh3-real-directory .bh3-quick-search-action{display:flex;align-items:end}.bh3-real-directory .bh3-quick-search-action button{width:100%;min-height:45px;border:0;border-radius:9px;background:#175c46;color:#fff;font-weight:750;cursor:pointer}.bh3-real-directory .bh3-quick-search-action button:hover{background:#104632}.bh3-real-directory .bh3-quick-search-action button:focus-visible{outline:3px solid #d59b38;outline-offset:3px}.bh3-real-directory .bh3-filter-details{grid-column:1/-1}.bh3-real-directory .bh3-filter-details>summary{display:none;cursor:pointer;font-weight:750;color:#175c46}.bh3-real-directory .bh3-filter-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:14px}.bh3-real-directory .bh3-filter-details .bh3-search-actions{align-self:end}@media(max-width:560px){.bh3-real-directory .bh3-search-form{grid-template-columns:minmax(0,1fr);gap:12px;padding:14px}.bh3-real-directory .bh3-filter-details>summary{display:list-item;padding:12px 14px;border:1px solid #bcd9c5;background:#f1f8f3;border-radius:10px}.bh3-real-directory .bh3-filter-fields{grid-template-columns:minmax(0,1fr);padding-top:14px}.bh3-real-directory .bh3-filter-details:not([open]) .bh3-filter-fields{display:none}.bh3-real-directory .bh3-search-form input,.bh3-real-directory .bh3-search-form select{width:100%;min-height:44px}.bh3-real-directory .bh3-search-form .bh3-check input{width:auto}}.bh3-real-directory .bh3-results-summary{font-size:14px;color:#456357;margin:12px 0}.bh3-real-directory .bh3-empty-state{grid-column:1/-1;text-align:center;padding:36px 22px;border:1px dashed #bdd8c7;border-radius:14px;background:#f6faf7}.bh3-real-directory .bh3-empty-state h3{margin:0 0 8px;color:#194b3b}.bh3-real-directory .bh3-empty-state p{margin:0 0 16px;color:#496257}.bh3-real-directory .bh3-empty-state a{display:inline-block;background:#175c46;color:#fff;border-radius:9px;padding:11px 18px;text-decoration:none;font-weight:700}.bh3-real-directory .bh3-empty-state a:focus-visible{outline:3px solid #d59b38;outline-offset:3px}
        .bh3-real-directory .bh3-map{border-radius:14px;overflow:hidden;border:1px solid #d7e3dc;margin:16px 0;z-index:0}
        .bh3-real-directory .bh3-map-message{font-size:14px;color:#50675e}
        @media(max-width:850px){.bh3-real-directory .bh3-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.bh3-real-directory .bh3-cards{grid-template-columns:1fr}}
        </style><div class="bh3-banner"><h2>Discover activities for your family</h2><p>Baby groups, toddler classes and family activities across Devon &amp; Cornwall.</p></div>';
        echo '<form class="bh3-search-form" method="get" aria-label="Filter activities">';
        if ( $show( 'keyword' ) ) { echo '<label><span>Search</span><input type="search" name="bh_search" placeholder="Activity, organiser or keyword" value="' . esc_attr( $search ) . '"></label>'; }
        echo '<div class="bh3-quick-search-action"><button type="submit">Search activities</button></div>';
        $has_active_filters = $category !== '' || $town !== '' || $region !== '' || $age !== null || $day > 0 || $postcode !== '' || $free || $term || $max_price > 0 || $sort !== 'title' || $geo_valid;
        echo '<details class="bh3-filter-details" open><summary>More filters' . ( $has_active_filters ? ' · Active' : '' ) . '</summary><div class="bh3-filter-fields">';
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
        echo '<div class="bh3-search-actions"><button type="submit">Find activities</button><a href="' . esc_url( remove_query_arg( array( 'bh_search', 'bh_category', 'bh_town', 'bh_region', 'bh_age', 'bh_day', 'bh_postcode', 'bh_free', 'bh_max_price', 'bh_term', 'bh_sort', 'bh_radius', 'bh_lat', 'bh_lng', 'bh_page' ) ) ) . '">Clear filters</a></div></div></details></form>';
        // Automatically apply filters, with a short debounce for text and numeric input.
        // Keep native GET submission for keyboard access, browser history and non-JS visitors.
        echo '<script>(function(){function init(){document.querySelectorAll(".bh3-real-directory .bh3-search-form").forEach(form=>{if(form.dataset.bh3AutoSearch)return;form.dataset.bh3AutoSearch="1";let timer;const submit=()=>{clearTimeout(timer);const page=form.querySelector("[name=bh_page]");if(page)page.value="1";if(form.requestSubmit)form.requestSubmit();else form.submit()};form.addEventListener("input",event=>{const field=event.target;if(!field.matches("input[type=search],input[type=number]"))return;clearTimeout(timer);timer=setTimeout(submit,550)});form.addEventListener("change",event=>{const field=event.target;if(field.matches("select,input[type=checkbox]"))submit()});form.addEventListener("submit",()=>clearTimeout(timer))})}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();</script>';
        echo '<script>(function(){
function init(){
document.querySelectorAll(".bh3-real-directory").forEach(scope=>{
const form=scope.querySelector(".bh3-search-form");if(!form||scope.dataset.bh3Ajax)return;
scope.dataset.bh3Ajax="1";
let controller=null,latestUrl=location.href,ajaxUpdated=false;
const status=document.createElement("span");status.className="bh3-ajax-status";status.setAttribute("role","status");status.setAttribute("aria-live","polite");form.append(status);
const update=async(url,push)=>{
if(controller)controller.abort();controller=new AbortController();const current=controller;
scope.setAttribute("aria-busy","true");status.textContent="Updating activities…";
try{
const response=await fetch(url,{signal:current.signal,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}});
if(!response.ok)throw Error("HTTP "+response.status);
const doc=new DOMParser().parseFromString(await response.text(),"text/html");
const incoming=doc.querySelector(".bh3-real-directory");
if(!incoming)throw Error("Directory response missing");
for(const selector of [".bh3-cards",".bh3-results-summary",".bh3-pages"]){
const old=scope.querySelector(selector),fresh=incoming.querySelector(selector);
if(old&&fresh)old.replaceWith(fresh);
else if(old&&!fresh)old.remove();
else if(fresh){const cards=scope.querySelector(".bh3-cards");if(cards)cards.insertAdjacentElement(selector===".bh3-pages"?"afterend":"beforebegin",fresh)}
}
latestUrl=url;ajaxUpdated=true;if(push)history.pushState({bh3Directory:true},"",url);
status.textContent="Results updated";
}catch(error){if(error.name==="AbortError")return;status.textContent="Loading full results…";location.assign(url)}
finally{if(current===controller)scope.removeAttribute("aria-busy")}
};
form.addEventListener("submit",event=>{
if(!scope.classList.contains("bh3-view-list"))return;
event.preventDefault();const url=new URL(form.action||location.href,location.href);
url.search=new URLSearchParams(new FormData(form)).toString();
url.searchParams.delete("bh_page");update(url.href,true)
});
scope.addEventListener("click",event=>{
const page=event.target.closest(".bh3-pages a");
if(page){event.preventDefault();update(page.href,true);return}
const view=event.target.closest("[data-bh3-view]");
if(view&&ajaxUpdated&&view.dataset.bh3View!=="list"){event.preventDefault();event.stopImmediatePropagation();location.assign(latestUrl)}
},true);
window.addEventListener("popstate",()=>{if(scope.isConnected&&location.href!==latestUrl)location.reload()});
});
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
})();</script>';
        echo '<div class="bh3-view-toggle" role="group" aria-label="Directory view">'; if ( $map_points ) { echo '<button type="button" data-bh3-view="map" aria-pressed="false">Map</button>'; } echo '<button type="button" data-bh3-view="list" aria-pressed="true">List</button><button type="button" data-bh3-view="calendar" aria-pressed="false">Calendar</button><a class="bh3-planner-tab" href="' . esc_url( home_url( '/my-hub/planner/' ) ) . '">My Planner</a></div>';
        echo '<script>(function(){function ready(){document.querySelectorAll(".bh3-real-directory").forEach(scope=>{scope.classList.add("bh3-view-list");scope.querySelectorAll("[data-bh3-view]").forEach(button=>button.addEventListener("click",()=>{const view=button.dataset.bh3View;scope.classList.toggle("bh3-view-map",view==="map");scope.classList.toggle("bh3-view-list",view==="list");scope.classList.toggle("bh3-view-calendar",view==="calendar");scope.querySelectorAll("[data-bh3-view]").forEach(b=>b.setAttribute("aria-pressed",String(b===button)));}));});}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",ready);else ready();})();</script>';
        // Calendar data uses only already-filtered published listings; it never claims booking availability.
        // Calendar queries the complete filtered result set independently of card pagination.
        // Bound the payload to protect page performance on large directories.
        // A day or term-time filter must also apply to the displayed session, not only its parent activity.
        $calendar_session_conditions = ( $day ? ' AND s.day_of_week=' . (int) $day : '' ) . ( $term ? ' AND s.term_time_only=1' : '' );
        $calendar_sql = "SELECT a.id,a.title,a.slug,s.day_of_week,LEFT(s.start_time,5) AS start_time,s.term_time_only
            FROM `{$activities}` a
            INNER JOIN `{$wpdb->prefix}sessions` s ON s.activity_id=a.id
            WHERE {$where} AND s.frequency='weekly' AND s.status='scheduled'
              AND s.day_of_week BETWEEN 1 AND 7 AND s.start_time IS NOT NULL {$calendar_session_conditions}
            ORDER BY s.day_of_week,s.start_time,a.title LIMIT 501";
        $calendar_rows = $wpdb->get_results( $params ? $wpdb->prepare( $calendar_sql, $params ) : $calendar_sql, ARRAY_A );
        $calendar_limited = is_array( $calendar_rows ) && count( $calendar_rows ) > 500;
        $calendar_events = array();
        foreach ( array_slice( (array) $calendar_rows, 0, 500 ) as $calendar_row ) {
            $calendar_slug = sanitize_title( (string) ( $calendar_row['slug'] ?? '' ) );
            $calendar_day = (int) $calendar_row['day_of_week'];
            $calendar_time = (string) $calendar_row['start_time'];
            if ( ! $calendar_slug || $calendar_day < 1 || $calendar_day > 7 || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $calendar_time ) ) { continue; }
            $calendar_events[] = array(
                'day' => $calendar_day,
                'title' => (string) $calendar_row['title'],
                'time' => $calendar_time . ( ! empty( $calendar_row['term_time_only'] ) ? ' · Term time' : '' ),
                'url' => home_url( user_trailingslashit( 'activity/' . $calendar_slug ) ) . '#timetable',
            );
        }
        $calendar_id = 'bh3-calendar-' . wp_unique_id();
        echo '<section class="bh3-calendar-section" aria-label="Activity calendar"><div class="bh3-calendar-toolbar"><h3>Activity calendar</h3><div><button type="button" data-calendar-prev aria-label="Previous period">‹</button><strong data-calendar-heading>' . esc_html( wp_date( 'j M', strtotime( 'monday this week' ) ) . ' – ' . wp_date( 'j M Y', strtotime( 'sunday this week' ) ) ) . '</strong><button type="button" data-calendar-next aria-label="Next period">›</button></div><div role="group" aria-label="Calendar display"><button type="button" data-calendar-mode="week" aria-pressed="true">Week</button><button type="button" data-calendar-mode="month" aria-pressed="false">Month</button></div></div><p>Recurring weekly sessions from this directory results page. Recurring sessions from matching directory activities, independent of list pagination. These are not confirmed dates or booking spaces; check the activity timetable for term dates and changes.</p><div id="' . esc_attr( $calendar_id ) . '" class="bh3-calendar-grid" aria-live="polite">';
        // Render a usable current-week calendar even when JavaScript fails to initialise.
        $calendar_monday = new DateTimeImmutable( 'monday this week', wp_timezone() );
        for ( $calendar_offset = 0; $calendar_offset < 7; $calendar_offset++ ) {
            $calendar_date = $calendar_monday->modify( '+' . $calendar_offset . ' days' );
            echo '<div class="bh3-calendar-day"><strong>' . esc_html( wp_date( 'D j M', $calendar_date->getTimestamp() ) ) . '</strong>';
            foreach ( $calendar_events as $calendar_event ) {
                if ( (int) $calendar_event['day'] !== $calendar_offset + 1 ) { continue; }
                echo '<a class="bh3-calendar-event" href="' . esc_url( $calendar_event['url'] ) . '">' . esc_html( $calendar_event['time'] . ' · ' . $calendar_event['title'] ) . '</a>';
            }
            echo '</div>';
        }
        echo '</div></section>';
        if ( $calendar_limited ) { echo '<p role="status" class="bh3-calendar-limit">Showing the first 500 matching weekly sessions. Narrow your filters to see more.</p>'; }
        echo '<script>(function(){const id=' . wp_json_encode( $calendar_id ) . ',events=' . wp_json_encode( $calendar_events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';function init(){const grid=document.getElementById(id);if(!grid)return;const section=grid.closest(".bh3-calendar-section"),heading=section.querySelector("[data-calendar-heading]");let mode="week",cursor=new Date();cursor.setHours(12,0,0,0);const fmt=new Intl.DateTimeFormat("en-GB",{day:"numeric",month:"short",year:"numeric"});function render(){grid.replaceChildren();const first=new Date(cursor);first.setDate(first.getDate()-((first.getDay()+6)%7));let start=new Date(first),count=7;if(mode==="month"){start=new Date(cursor.getFullYear(),cursor.getMonth(),1,12);start.setDate(start.getDate()-((start.getDay()+6)%7));const last=new Date(cursor.getFullYear(),cursor.getMonth()+1,0,12);count=Math.ceil((Math.round((last-start)/86400000)+1)/7)*7;}heading.textContent=mode==="week"?fmt.format(first)+" – "+fmt.format(new Date(first.getFullYear(),first.getMonth(),first.getDate()+6,12)):new Intl.DateTimeFormat("en-GB",{month:"long",year:"numeric"}).format(cursor);for(let i=0;i<count;i++){const date=new Date(start.getFullYear(),start.getMonth(),start.getDate()+i,12),day=((date.getDay()+6)%7)+1,cell=document.createElement("div");cell.className="bh3-calendar-day";if(mode==="month"&&date.getMonth()!==cursor.getMonth())cell.classList.add("bh3-calendar-outside");const label=document.createElement("strong");label.textContent=new Intl.DateTimeFormat("en-GB",{weekday:"short",day:"numeric",month:mode==="week"?"short":undefined}).format(date);cell.append(label);for(const item of events.filter(e=>e.day===day)){const link=document.createElement("a");link.className="bh3-calendar-event";link.href=item.url;link.textContent=item.time+" · "+item.title;cell.append(link)}grid.append(cell)}}section.querySelector("[data-calendar-prev]").addEventListener("click",()=>{if(mode==="week")cursor.setDate(cursor.getDate()-7);else{cursor.setDate(1);cursor.setMonth(cursor.getMonth()-1)}render()});section.querySelector("[data-calendar-next]").addEventListener("click",()=>{if(mode==="week")cursor.setDate(cursor.getDate()+7);else{cursor.setDate(1);cursor.setMonth(cursor.getMonth()+1)}render()});section.querySelectorAll("[data-calendar-mode]").forEach(button=>button.addEventListener("click",()=>{mode=button.dataset.calendarMode;section.querySelectorAll("[data-calendar-mode]").forEach(b=>b.setAttribute("aria-pressed",String(b===button)));render()}));render()}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();</script>';
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
            wp_add_inline_script( 'bh3-leaflet-cluster', '(function(){const cfg=' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';function init(){const el=document.getElementById(cfg.id);if(!el||!window.L)return;const map=L.map(el,{scrollWheelZoom:false}).setView(cfg.center,cfg.zoom);const scope=el.closest(".bh3-real-directory");if(scope){scope.querySelectorAll("[data-bh3-view=map]").forEach(button=>button.addEventListener("click",()=>setTimeout(()=>map.invalidateSize(),60)))}L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);const bounds=[];const clusters=L.markerClusterGroup?L.markerClusterGroup({showCoverageOnHover:false,maxClusterRadius:48}):null;if(clusters)map.addLayer(clusters);cfg.points.forEach(p=>{const marker=L.marker([p.lat,p.lng]);if(clusters)clusters.addLayer(marker);else marker.addTo(map);const wrap=document.createElement("div");const title=document.createElement("strong");title.textContent=p.title;const desc=document.createElement("p");desc.textContent=[p.venue,p.town].filter(Boolean).join(", ");const action=document.createElement("button");action.type="button";action.className="bh3-map-card-link";action.textContent="Show listing";action.addEventListener("click",()=>{const scope=el.closest(".bh3-real-directory"),card=[...scope.querySelectorAll("[data-activity-id]")].find(x=>x.dataset.activityId===String(p.activity_id));if(!card)return;scope.querySelectorAll(".bh3-card-active").forEach(x=>x.classList.remove("bh3-card-active"));card.classList.add("bh3-card-active");if(scope.classList.contains("bh3-view-map")){scope.classList.remove("bh3-view-map");scope.classList.add("bh3-view-list");const both=[...scope.querySelectorAll("[data-bh3-view]")].find(x=>x.dataset.bh3View==="list");scope.querySelectorAll("[data-bh3-view]").forEach(b=>b.setAttribute("aria-pressed",String(b===both)))}card.scrollIntoView({behavior:"smooth",block:"center"});card.setAttribute("tabindex","-1");card.focus({preventScroll:true})});wrap.append(title,desc,action);marker.bindPopup(wrap);bounds.push([p.lat,p.lng])});if(bounds.length)map.fitBounds(bounds,{padding:[25,25],maxZoom:cfg.zoom});setTimeout(()=>map.invalidateSize(),100)}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();', 'after' );
        } else { echo '<p class="bh3-map-message">No mapped venues for the activities on this page. Listings are available below.</p>'; }
        echo '<p class="bh3-map-message">Map data © OpenStreetMap contributors. Switch to List for full activity details.</p></section>';
        if ( $rows ) {
            $first_result = ( ( $page - 1 ) * 12 ) + 1;
            $last_result = $first_result + count( $rows ) - 1;
            echo '<p class="bh3-results-summary" role="status">Showing activities ' . esc_html( (string) $first_result ) . '–' . esc_html( (string) $last_result ) . ( $more ? ' · More results available' : '' ) . '</p>';
        }
        echo '<p class="bh3-map-note">Select a map pin, then choose “Show listing” to open its activity in List view.</p><div class="bh3-cards">';
        // The shared /activity/ page renders the selected listing via bh_activity.
        $detail_page = get_page_by_path( 'activity' );
        $detail_base_url = $detail_page ? get_permalink( $detail_page ) : '';
        if ( ! $detail_base_url ) {
            $preview_page = get_page_by_path( 'activity-preview' );
            $detail_base_url = $preview_page && has_shortcode( (string) $preview_page->post_content, 'bubbahub_activity' )
                ? get_permalink( $preview_page ) : '';
        }
        foreach ( $rows as $row ) {
            $slug = isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : '';
            $detail_url = $slug ? home_url( user_trailingslashit( 'activity/' . $slug ) ) : ( $detail_base_url ? add_query_arg( 'bh_activity', absint( $row['id'] ), $detail_base_url ) : '' );
            echo '<article data-activity-id="' . esc_attr( (string) absint( $row['id'] ) ) . '">';
            $image_url = isset( $row['image_path'] ) ? trim( (string) $row['image_path'] ) : '';
            $image_scheme = strtolower( (string) wp_parse_url( $image_url, PHP_URL_SCHEME ) );
            if ( $image_url && in_array( $image_scheme, array( 'http', 'https' ), true ) ) {
                echo '<div class="bh3-card-image">';
                if ( $detail_url ) { echo '<a href="' . esc_url( $detail_url ) . '" aria-label="' . esc_attr( 'View ' . $row['title'] ) . '">'; }
                echo '<img src="' . esc_url( $image_url ) . '" alt="" loading="lazy" decoding="async">';
                if ( $detail_url ) { echo '</a>'; }
                echo '</div>';
            } else {
                echo '<div class="bh3-card-image bh3-card-image-empty" aria-hidden="true"><span>BH</span></div>';
            }
            echo '<div class="bh3-meta">' . esc_html( (string) $row['category'] ) . '</div><h3>';
            if ( $detail_url ) { echo '<a class="bh3-card-title-link" href="' . esc_url( $detail_url ) . '">' . esc_html( $row['title'] ) . '</a>'; }
            else { echo esc_html( $row['title'] ); }
            echo '</h3>';
            echo '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $row['description'] ), 24 ) ) . '</p>';
            echo '<div class="bh3-card-facts">';
            if ( $row['town'] ) { echo '<span class="bh3-fact bh3-fact-town"><span aria-hidden="true">📍</span> ' . esc_html( (string) $row['town'] ) . '</span>'; }
            if ( $row['age_min_months'] !== null || $row['age_max_months'] !== null ) {
                $age_min = $row['age_min_months'] !== null ? (int) $row['age_min_months'] : null;
                $age_max = $row['age_max_months'] !== null ? (int) $row['age_max_months'] : null;
                $age_label = $age_min !== null && $age_max !== null ? $age_min . '–' . $age_max . ' months' : ( $age_min !== null ? $age_min . '+ months' : 'Up to ' . $age_max . ' months' );
                echo '<span class="bh3-fact bh3-fact-age">Age: ' . esc_html( $age_label ) . '</span>';
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
            if ( $detail_url && $session_labels ) { echo '<p class="bh3-card-schedule-link"><a href="' . esc_url( $detail_url . '#timetable' ) . '">View timetable &amp; booking options</a></p>'; }
            echo '<div class="bh3-card-footer"><strong class="bh3-card-price">' . ( (int) $row['is_free'] ? 'Free' : ( $row['price_from'] !== null ? 'From £' . esc_html( number_format( (float) $row['price_from'], 2 ) ) : 'Price on enquiry' ) ) . '</strong>';
            if ( $detail_url ) { echo '<a class="bh3-card-cta" href="' . esc_url( $detail_url ) . '">View details <span aria-hidden="true">→</span></a>'; }
            echo '</div></article>';
        }
        if ( ! $rows ) {
            $clear_url = remove_query_arg( array( 'bh_page', 'bh_search', 'bh_category', 'bh_age', 'bh_day', 'bh_max_price', 'bh_region', 'bh_town', 'bh_postcode', 'bh_free', 'bh_term', 'bh_radius', 'bh_sort', 'bh_lat', 'bh_lng' ) );
            echo '<div class="bh3-empty-state" role="status"><h3>No activities found</h3><p>Try a different town, age or day, or remove your filters to see more family activities.</p><a href="' . esc_url( $clear_url ) . '">Clear filters</a></div>';
        }
        echo '</div><nav class="bh3-pages" aria-label="Directory pages">';
        $base = remove_query_arg( 'bh_page' );
        if ( $page > 1 ) { echo '<a href="' . esc_url( add_query_arg( 'bh_page', $page - 1, $base ) ) . '">Previous</a>'; }
        if ( $more ) { echo '<a href="' . esc_url( add_query_arg( 'bh_page', $page + 1, $base ) ) . '">Next</a>'; }
        echo '</nav></section>';
        return ob_get_clean();
    }
}
