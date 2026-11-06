<?php
/** Staging-only read-only activity detail shortcode, using the new WordPress tables. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Detail_3 {
    public static function register() {
        add_shortcode( 'bubbahub_activity', array( __CLASS__, 'render' ) );
        add_action( 'init', array( __CLASS__, 'register_activity_rewrite' ) );
        add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
        add_filter( 'redirect_canonical', array( __CLASS__, 'preserve_activity_permalink' ), 10, 2 );
        add_filter( 'bh_theme_activity_id_for_slug', array( __CLASS__, 'activity_id_for_slug' ), 10, 2 );
        add_filter( 'bh_theme_activity_data', array( __CLASS__, 'theme_activity_data' ), 10, 2 );
    }
    public static function register_activity_rewrite() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_rewrite_rule( '^activity/([^/]+)/?$', 'index.php?pagename=activity&bh_activity_slug=$matches[1]', 'top' );
        if ( get_option( 'bh3_activity_rewrite_version' ) !== '2' ) {
            flush_rewrite_rules( false );
            update_option( 'bh3_activity_rewrite_version', '2', false );
        }
    }
    public static function register_query_vars( $vars ) {
        $vars[] = 'bh_activity_slug';
        return $vars;
    }
    public static function activity_id_for_slug( $existing, $slug ) {
        if ( $existing ) { return absint( $existing ); }
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return 0; }
        $slug = sanitize_title( (string) $slug );
        if ( ! $slug ) { return 0; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE slug=%s AND status IN ('published','publish') LIMIT 1", $slug
        ) );
    }
    public static function preserve_activity_permalink( $redirect_url, $requested_url ) {
        if ( get_query_var( 'bh_activity_slug', '' ) ) { return false; }
        return $redirect_url;
    }
    /**
     * Read-only adapter for the BubbaTheme [bubba_activity] presentation.
     * Never expose payment links until a separately tested booking flow supplies them.
     */
    public static function theme_activity_data( $existing, $activity_id ) {
        if ( is_array( $existing ) ) { return $existing; }
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return $existing; }
        $activity_id = absint( $activity_id );
        if ( ! $activity_id ) { return $existing; }
        global $wpdb;
        $a = $wpdb->prefix . 'activities';
        $o = $wpdb->prefix . 'organisers';
        $s = $wpdb->prefix . 'sessions';
        $v = $wpdb->prefix . 'venues';
        $av = $wpdb->prefix . 'activity_venues';
        foreach ( array( $a, $o, $s, $v, $av ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return $existing; }
        }
        $activity = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,o.name AS organiser_name,o.website_url AS organiser_website FROM `{$a}` a LEFT JOIN `{$o}` o ON o.id=a.organiser_id WHERE a.id=%d AND a.status IN ('published','publish') LIMIT 1",
            $activity_id
        ), ARRAY_A );
        if ( ! $activity ) { return $existing; }
        $venues = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.name,v.town,v.address_line1,v.postcode FROM `{$av}` av INNER JOIN `{$v}` v ON v.id=av.venue_id WHERE av.activity_id=%d ORDER BY v.name ASC LIMIT 1",
            $activity_id
        ), ARRAY_A );
        // Sessions may carry the venue link even when the activity_venues
        // junction has not yet been populated by the listing wizard.
        if ( ! $venues ) {
            $venues = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT v.name,v.town,v.address_line1,v.postcode FROM `{$s}` s INNER JOIN `{$v}` v ON v.id=s.venue_id WHERE s.activity_id=%d AND s.venue_id IS NOT NULL ORDER BY v.name ASC LIMIT 1",
                $activity_id
            ), ARRAY_A );
        }
        $links = $wpdb->prefix . 'session_listing_links';
        $has_links = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $links ) ) ) === $links;
        if ( $has_links ) {
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT s.id,s.day_of_week,s.start_time,s.end_time,s.term_time_only FROM `{$s}` s JOIN `{$a}` owner ON owner.id=s.activity_id LEFT JOIN `{$links}` sl ON sl.session_id=s.id WHERE (s.activity_id=%d OR sl.activity_id=%d) AND owner.organiser_id=%d AND s.status NOT IN ('cancelled','canceled','inactive') ORDER BY s.day_of_week,s.start_time LIMIT 100",
                $activity_id, $activity_id, (int) $activity['organiser_id']
            ), ARRAY_A );
        } else {
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.id,s.day_of_week,s.start_time,s.end_time,s.term_time_only FROM `{$s}` s WHERE s.activity_id=%d AND s.status NOT IN ('cancelled','canceled','inactive') ORDER BY s.day_of_week,s.start_time LIMIT 100",
                $activity_id
            ), ARRAY_A );
        }
        $booking_configs = get_option( 'bh3_session_booking_config', array() );
        $booking_configs = is_array( $booking_configs ) ? $booking_configs : array();
        $enquiry_configs = get_option( 'bh3_session_enquiry_enabled', array() );
        $enquiry_configs = is_array( $enquiry_configs ) ? $enquiry_configs : array();
        $names = get_option( 'bh3_session_titles', array() );
        $names = is_array( $names ) ? $names : array();
        $days = array( 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday' );
        $schedule = array();
        foreach ( $sessions ?: array() as $session ) {
            $sid = (int) $session['id'];
            $config = $booking_configs[$sid] ?? array();
            $config = is_array( $config ) ? $config : array();
            $booking_enabled = ! empty( $config['enabled'] );
            $external_url = ! empty( $activity['booking_url'] ) && wp_http_validate_url( (string) $activity['booking_url'] )
                ? (string) $activity['booking_url'] : '';
            $enquiry_enabled = ! empty( $enquiry_configs[$sid] );
            $schedule[] = array(
                'booking_url' => $external_url ?: '',
                'enquiry_url' => $enquiry_enabled && shortcode_exists( 'fluentform' ) ? '#bh-activity-enquiry' : '',
                // Session booking is configured but the payment/reservation journey is not yet verified.
                // Expose a non-actionable state; never link directly to Form #7 as a confirmed booking.
                'booking_pending' => $booking_enabled && ! $external_url,
                'name' => (string) ( $names[$sid] ?? $activity['title'] ),
                'day' => $days[(int) $session['day_of_week']] ?? 'Other',
                'start' => substr( (string) $session['start_time'], 0, 5 ),
                'end' => substr( (string) $session['end_time'], 0, 5 ),
                'term_time' => ! empty( $session['term_time_only'] ) ? 'Term time' : '',
            );
        }
        $venue = $venues[0] ?? array();
        $duration = '';
        if ( ! empty( $sessions[0]['start_time'] ) && ! empty( $sessions[0]['end_time'] ) ) {
            $minutes = ( strtotime( $sessions[0]['end_time'] ) - strtotime( $sessions[0]['start_time'] ) ) / 60;
            if ( $minutes > 0 && $minutes <= 1440 ) { $duration = (string) round( $minutes ) . ' mins'; }
        }
        $image_url = (string) ( $activity['image_path'] ?? '' );
        if ( $image_url && ! wp_http_validate_url( $image_url ) ) { $image_url = ''; }
        $booking_url = (string) ( $activity['booking_url'] ?? '' );
        if ( $booking_url && ! wp_http_validate_url( $booking_url ) ) { $booking_url = ''; }
        return array(
            'image_url' => $image_url,
            'summary' => wp_trim_words( wp_strip_all_tags( (string) ( $activity['description'] ?? '' ) ), 24 ),
            'booking_url' => $booking_url,
            'enquiry_enabled' => (bool) array_filter( $schedule, static function ( $entry ) { return ! empty( $entry['enquiry_url'] ); } ),
            'term_time' => array_filter( $schedule, static function ( $entry ) { return ! empty( $entry['term_time'] ); } ) ? 'Yes' : 'No',
            'session_length' => $duration,
            'title' => (string) $activity['title'],
            'description' => (string) ( $activity['description'] ?? '' ),
            'organiser' => (string) ( $activity['organiser_name'] ?? '' ),
            'website_url' => (string) ( $activity['organiser_website'] ?? '' ),
            'price' => ! empty( $activity['is_free'] ) ? 'Free' : ( isset( $activity['price_from'] ) && $activity['price_from'] !== null ? 'From £' . number_format( (float) $activity['price_from'], 2 ) : 'Ask organiser' ),
            'age_range' => ( isset( $activity['age_min_months'], $activity['age_max_months'] ) && is_numeric( $activity['age_min_months'] ) && is_numeric( $activity['age_max_months'] ) && (int) $activity['age_max_months'] >= (int) $activity['age_min_months'] && ( (int) $activity['age_max_months'] > 0 || (int) $activity['age_min_months'] > 0 ) ) ? (string) (int) $activity['age_min_months'] . '–' . (string) (int) $activity['age_max_months'] . ' months' : '',
            'location' => (string) ( $venue['town'] ?? '' ),
            'venue' => (string) ( $venue['name'] ?? '' ),
            'address' => implode( ', ', array_filter( array( $venue['address_line1'] ?? '', $venue['town'] ?? '', $venue['postcode'] ?? '' ) ) ),
            'map_query' => implode( ', ', array_filter( array( $venue['name'] ?? '', $venue['address_line1'] ?? '', $venue['town'] ?? '', $venue['postcode'] ?? '' ) ) ),
            'directions_url' => ! empty( $venue['postcode'] ) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( implode( ', ', array_filter( array( $venue['name'] ?? '', $venue['address_line1'] ?? '', $venue['town'] ?? '', $venue['postcode'] ?? '' ) ) ) ) : '',
            'sessions_count' => count( $schedule ) . ' available',
            'schedule' => $schedule,
            'categories' => ! empty( $activity['category'] ) ? array( (string) $activity['category'] ) : array(),
        );
    }

    public static function render( $atts = array() ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return '<p>Activity detail is available on staging only.</p>';
        }
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bubbahub_activity' );
        $id = absint( $atts['id'] );
        if ( ! $id && isset( $_GET['bh_activity'] ) && is_scalar( $_GET['bh_activity'] ) ) {
            $id = absint( $_GET['bh_activity'] );
        }
        if ( ! $id ) { return '<p>Choose an activity from the directory.</p>'; }
        global $wpdb;
        $a = $wpdb->prefix . 'activities';
        $o = $wpdb->prefix . 'organisers';
        $v = $wpdb->prefix . 'venues';
        $av = $wpdb->prefix . 'activity_venues';
        $s = $wpdb->prefix . 'sessions';
        foreach ( array( $a, $o, $v, $av, $s ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return '<p>Activity tables are not ready on staging.</p>';
            }
        }
        $activity = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,o.name AS organiser_name,o.website_url AS organiser_website FROM `{$a}` a
             LEFT JOIN `{$o}` o ON o.id=a.organiser_id
             WHERE a.id=%d AND a.status IN ('published','publish') LIMIT 1", $id
        ), ARRAY_A );
        if ( ! $activity ) { return '<p>Activity not found or not published.</p>'; }
        $venues = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.id,v.name,v.address_line1,v.town,v.postcode,v.region,v.latitude,v.longitude FROM `{$av}` av
             INNER JOIN `{$v}` v ON v.id=av.venue_id WHERE av.activity_id=%d ORDER BY v.name ASC LIMIT 50", $id
        ), ARRAY_A );
        // Fall back to the venue assigned to a session if no activity-level
        // venue association exists yet.
        if ( ! $venues ) {
            $venues = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT v.id,v.name,v.address_line1,v.town,v.postcode,v.region,v.latitude,v.longitude FROM `{$s}` s
                 INNER JOIN `{$v}` v ON v.id=s.venue_id WHERE s.activity_id=%d AND s.venue_id IS NOT NULL ORDER BY v.name ASC LIMIT 50", $id
            ), ARRAY_A );
        }
        $link_table = $wpdb->prefix . 'session_listing_links';
        $links_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $link_table ) ) ) === $link_table;
        if ( $links_ready ) {
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT s.id,s.venue_id,s.day_of_week,s.start_time,s.end_time,s.frequency,s.term_time_only,s.start_date,s.end_date,s.status,v.name AS venue_name
                 FROM `{$s}` s JOIN `{$a}` owner ON owner.id=s.activity_id
                 LEFT JOIN `{$link_table}` sl ON sl.session_id=s.id
                 LEFT JOIN `{$v}` v ON v.id=s.venue_id
                 WHERE (s.activity_id=%d OR sl.activity_id=%d) AND owner.organiser_id=%d
                 AND s.status NOT IN ('cancelled','canceled','inactive')
                 ORDER BY s.day_of_week,s.start_time LIMIT 100",
                $id, $id, (int) $activity['organiser_id']
            ), ARRAY_A );
        } else {
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.id,s.venue_id,s.day_of_week,s.start_time,s.end_time,s.frequency,s.term_time_only,s.start_date,s.end_date,s.status,v.name AS venue_name
                 FROM `{$s}` s LEFT JOIN `{$v}` v ON v.id=s.venue_id
                 WHERE s.activity_id=%d AND s.status NOT IN ('cancelled','canceled','inactive')
                 ORDER BY s.day_of_week,s.start_time LIMIT 100", $id
            ), ARRAY_A );
        }
        $session_titles = get_option( 'bh3_session_titles', array() );
        $session_titles = is_array( $session_titles ) ? $session_titles : array();
        $enquiry_flags = get_option( 'bh3_session_enquiry_enabled', array() );
        $enquiry_flags = is_array( $enquiry_flags ) ? $enquiry_flags : array();
        $booking_flags = get_option( 'bh3_session_booking_config', array() );
        $booking_flags = is_array( $booking_flags ) ? $booking_flags : array();
        $days = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
        $builder = class_exists( 'BH_Directory_Builder_3' ) ? BH_Directory_Builder_3::settings() : array();
        $sections = isset( $builder['detail_sections'] ) && is_array( $builder['detail_sections'] ) ? $builder['detail_sections'] : array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser' );
        $has = static function( $section ) use ( $sections ) { return in_array( $section, $sections, true ); };
        $order = isset( $builder['detail_order'] ) && is_array( $builder['detail_order'] ) ? $builder['detail_order'] : array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser' );
        $position = static function( $key ) use ( $order ) { $index = array_search( $key, $order, true ); return $index === false ? 99 : $index + 1; };
        $order_style = static function( $key ) use ( $position ) { return ' style="order:' . esc_attr( (string) $position( $key ) ) . '"'; };
        $width_class = ( $builder['listing_layout'] ?? 'sidebar' ) === 'full_width' ? ' bh3-detail-full' : '';
        ob_start();
        echo '<article class="bh3-activity-detail' . esc_attr( $width_class ) . '"><style>
          .bh3-activity-detail{max-width:1050px;margin:0 auto;padding:28px 16px;color:#173c34;display:flex;flex-direction:column}.bh3-activity-detail>style{display:none}.bh3-activity-detail.bh3-detail-full{max-width:100%}
          .bh3-activity-detail *{box-sizing:border-box}.bh3-activity-detail header{padding:28px;border-radius:18px;background:#e7f3eb}
          .bh3-activity-detail h1{margin:8px 0}.bh3-activity-detail .bh3-chips{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}
          .bh3-activity-detail .bh3-chip{background:#f2f7f4;border:1px solid #d8e6dc;border-radius:24px;padding:8px 13px}
          .bh3-activity-detail section{margin:26px 0}.bh3-activity-detail .bh3-venues{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
          .bh3-activity-detail .bh3-venue{padding:18px;border:1px solid #d8e6dc;border-radius:12px}.bh3-activity-detail .bh3-location-map{height:390px;border-radius:12px;border:1px solid #d8e6dc;margin:16px 0;z-index:0}
          .bh3-activity-detail .bh3-table-wrap{overflow-x:auto}.bh3-activity-detail table{width:100%;border-collapse:collapse;min-width:550px}
          .bh3-activity-detail th,.bh3-activity-detail td{padding:12px;border-bottom:1px solid #d8e6dc;text-align:left}
          .bh3-activity-detail .bh3-book{display:inline-block;padding:12px 18px;border-radius:10px;background:#175c46;color:#fff;text-decoration:none}
        </style>';
        if ( $has( 'hero' ) ) { echo '<header' . $order_style( 'hero' ) . '><div>' . esc_html( (string) $activity['category'] ) . '</div><h1>' . esc_html( $activity['title'] ) . '</h1>';
        if ( $activity['organiser_name'] ) { echo '<p>By ' . esc_html( $activity['organiser_name'] ) . '</p>'; }
        echo '</header>'; } else { echo '<h1>' . esc_html( $activity['title'] ) . '</h1>'; }
        echo '<div class="bh3-chips" style="order:1.5">';
        echo '<span class="bh3-chip">' . ( (int) $activity['is_free'] ? 'Free' : ( $activity['price_from'] !== null ? 'From £' . esc_html( number_format( (float) $activity['price_from'], 2 ) ) : 'Price on enquiry' ) ) . '</span>';
        if ( $activity['age_min_months'] !== null || $activity['age_max_months'] !== null ) {
            echo '<span class="bh3-chip">Age: ' . esc_html( (string) ( $activity['age_min_months'] ?? '0' ) ) . '–' . esc_html( (string) ( $activity['age_max_months'] ?? 'any' ) ) . ' months</span>';
        }
        echo '</div>';
        if ( $has( 'overview' ) ) { echo '<section' . $order_style( 'overview' ) . '><h2>About this activity</h2><div>' . wp_kses_post( wpautop( (string) $activity['description'] ) ) . '</div></section>'; }
        if ( $venues && $has( 'map' ) ) { // Location cards until the Leaflet map is implemented.
            echo '<section' . $order_style( 'map' ) . '><h2>Locations</h2><div class="bh3-venues">';
            foreach ( $venues as $venue ) {
                echo '<div class="bh3-venue"><strong>' . esc_html( $venue['name'] ) . '</strong><p>' .
                    esc_html( implode( ', ', array_filter( array( $venue['address_line1'], $venue['town'], $venue['postcode'] ) ) ) ) . '</p></div>';
            }
            echo '</div>';
            $points = array();
            foreach ( $venues as $venue ) {
                if ( $venue['latitude'] === null || $venue['longitude'] === null ) { continue; }
                $lat = (float) $venue['latitude']; $lng = (float) $venue['longitude'];
                if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) { continue; }
                $points[] = array( 'lat' => $lat, 'lng' => $lng, 'name' => (string) $venue['name'], 'town' => (string) $venue['town'] );
            }
            if ( $points ) {
                $map_id = 'bh3-activity-map-' . wp_unique_id();
                $height = min( 750, max( 240, (int) ( $builder['map_height'] ?? 390 ) ) );
                echo '<div id="' . esc_attr( $map_id ) . '" class="bh3-location-map" style="height:' . esc_attr( $height ) . 'px" role="region" aria-label="Activity venue map"></div>';
                echo '<p><small>Map data © OpenStreetMap contributors</small></p>';
                wp_enqueue_style( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
                wp_enqueue_script( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
                $payload = array( 'id' => $map_id, 'points' => $points, 'zoom' => min( 18, max( 3, (int) ( $builder['map_zoom'] ?? 11 ) ) ) );
                wp_add_inline_script( 'bh3-leaflet', '(function(){const c=' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';function init(){const el=document.getElementById(c.id);if(!el||!window.L)return;const map=L.map(el,{scrollWheelZoom:false});L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);const bounds=[];c.points.forEach(p=>{const marker=L.marker([p.lat,p.lng]).addTo(map),box=document.createElement("div"),title=document.createElement("strong"),town=document.createElement("p");title.textContent=p.name;town.textContent=p.town;box.append(title,town);marker.bindPopup(box);bounds.push([p.lat,p.lng])});map.fitBounds(bounds,{padding:[30,30],maxZoom:c.zoom});setTimeout(()=>map.invalidateSize(),100)}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();', 'after' );
            }
            echo '</section>';
        }
        if ( $has( 'timetable' ) ) { echo '<section id="timetable"' . $order_style( 'timetable' ) . '><h2>Weekly timetable</h2>';
        if ( $sessions ) {
            echo '<div class="bh3-table-wrap"><table><thead><tr><th>Session</th><th>Day</th><th>Time</th><th>Venue</th><th>Details</th></tr></thead><tbody>';
            foreach ( $sessions as $session ) {
                $day = $days[(int) $session['day_of_week']] ?? 'Other';
                $time = substr( (string) $session['start_time'], 0, 5 ) . ( $session['end_time'] ? '–' . substr( (string) $session['end_time'], 0, 5 ) : '' );
                $details = array_filter( array( $session['frequency'], (int) $session['term_time_only'] ? 'Term time' : '', $session['status'] ) );
                $session_id = (int) $session['id'];
                $details[] = ! empty( $enquiry_flags[$session_id] ) ? 'Enquiries available' : '';
                $details[] = ! empty( $booking_flags[$session_id]['enabled'] ) ? 'Online booking coming soon — enquire for now' : '';
                $details = array_filter( $details );
                echo '<tr><td>' . esc_html( $session_titles[$session_id] ?? $activity['title'] ) . '</td><td>' . esc_html( $day ) . '</td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $session['venue_name'] ) . '</td><td>' . esc_html( implode( ' · ', $details ) ) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        } else { echo '<p>No timetable has been added yet.</p>'; }
        echo '</section>'; }
        $booking = (string) $activity['booking_url'];
        if ( $has( 'contact' ) && $booking && in_array( strtolower( (string) wp_parse_url( $booking, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
            echo '<p' . $order_style( 'contact' ) . '><a class="bh3-book" href="' . esc_url( $booking ) . '" rel="noopener noreferrer" target="_blank">Book with organiser</a></p>';
        }
        if ( $has( 'organiser' ) && $activity['organiser_website'] ) {
            echo '<p' . $order_style( 'organiser' ) . '><a href="' . esc_url( $activity['organiser_website'] ) . '" rel="noopener noreferrer" target="_blank">Organiser website</a></p>';
        }
        echo '</article>';
        return ob_get_clean();
    }
}
