<?php
/** Read-only staging directory backed by the new WordPress activity tables. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Directory_3 {
    public static function register() {
        add_shortcode( 'bubbahub_directory', array( __CLASS__, 'render' ) );
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
        foreach ( array( $activities, $organisers, $links, $venues ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return '<p>Directory tables are not ready on staging.</p>';
            }
        }
        $search = isset( $_GET['bh_search'] ) && is_string( $_GET['bh_search'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ), 0, 100 ) : '';
        $category = isset( $_GET['bh_category'] ) && is_string( $_GET['bh_category'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_category'] ) ), 0, 150 ) : '';
        $town = isset( $_GET['bh_town'] ) && is_string( $_GET['bh_town'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh_town'] ) ), 0, 120 ) : '';
        $free = isset( $_GET['bh_free'] ) && (string) $_GET['bh_free'] === '1';
        $page = min( 100, max( 1, absint( $_GET['bh_page'] ?? 1 ) ) );
        $conditions = array( "a.status IN ('published','publish')" );
        $params = array();
        if ( $search !== '' ) { $conditions[] = 'a.title LIKE %s'; $params[] = '%' . $wpdb->esc_like( $search ) . '%'; }
        if ( $category !== '' ) { $conditions[] = 'a.category = %s'; $params[] = $category; }
        if ( $town !== '' ) { $conditions[] = 'EXISTS (SELECT 1 FROM `' . $links . '` av2 INNER JOIN `' . $venues . '` v2 ON v2.id = av2.venue_id WHERE av2.activity_id = a.id AND v2.town LIKE %s)'; $params[] = '%' . $wpdb->esc_like( $town ) . '%'; }
        if ( $free ) { $conditions[] = 'a.is_free = 1'; }
        $where = implode( ' AND ', $conditions );
        $sql = "SELECT a.id,a.title,a.slug,a.description,a.category,a.price_from,a.is_free,o.name AS organiser,
            (SELECT v.town FROM `{$links}` av INNER JOIN `{$venues}` v ON v.id=av.venue_id WHERE av.activity_id=a.id ORDER BY v.name LIMIT 1) AS town
            FROM `{$activities}` a LEFT JOIN `{$organisers}` o ON o.id=a.organiser_id
            WHERE {$where} ORDER BY a.title ASC LIMIT 13 OFFSET " . ( ( $page - 1 ) * 12 );
        $rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) { return '<p>Unable to load the directory.</p>'; }
        $more = count( $rows ) > 12;
        $rows = array_slice( $rows, 0, 12 );
        $builder = class_exists( 'BH_Directory_Builder_3' ) ? BH_Directory_Builder_3::settings() : array();
        $map_points = array();
        $row_ids = array_map( 'intval', array_column( $rows, 'id' ) );
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
                    'lat' => $lat, 'lng' => $lng,
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
        .bh3-real-directory h2{margin:0 0 8px}.bh3-real-directory form{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}
        .bh3-real-directory input{min-width:130px;flex:1;padding:12px;border:1px solid #cbd8d0;border-radius:9px}
        .bh3-real-directory button{background:#175c46;color:white;border:0;border-radius:9px;padding:12px 20px;cursor:pointer}
        .bh3-real-directory .bh3-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .bh3-real-directory article{background:#fff;border:1px solid #d7e3dc;border-radius:14px;padding:20px}
        .bh3-real-directory article h3{margin:8px 0}.bh3-real-directory .bh3-meta{color:#50675e;font-size:14px}
        .bh3-real-directory .bh3-pages{display:flex;gap:16px;margin-top:24px}
        .bh3-real-directory .bh3-map{border-radius:14px;overflow:hidden;border:1px solid #d7e3dc;margin:16px 0;z-index:0}
        .bh3-real-directory .bh3-map-message{font-size:14px;color:#50675e}
        @media(max-width:850px){.bh3-real-directory .bh3-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.bh3-real-directory .bh3-cards{grid-template-columns:1fr}}
        </style><div class="bh3-banner"><h2>Discover activities for your family</h2><p>Baby groups, toddler classes and family activities across Devon &amp; Cornwall.</p></div>';
        echo '<form method="get"><input type="search" name="bh_search" aria-label="Search activities" placeholder="Search activities" value="' . esc_attr( $search ) . '">';
        echo '<input type="search" name="bh_category" aria-label="Category" placeholder="Category" value="' . esc_attr( $category ) . '">';
        echo '<input type="search" name="bh_town" aria-label="Town" placeholder="Town" value="' . esc_attr( $town ) . '">';
        echo '<label><input type="checkbox" name="bh_free" value="1"' . checked( $free, true, false ) . '> Free only</label><button type="submit">Search</button></form>';
        echo '<section aria-label="Activity locations"><h3>Activities on the map</h3>';
        if ( $map_points ) {
            $map_id = 'bh3-map-' . wp_unique_id();
            $height = min( 750, max( 240, (int) ( $builder['map_height'] ?? 420 ) ) );
            echo '<div id="' . esc_attr( $map_id ) . '" class="bh3-map" style="height:' . esc_attr( $height ) . 'px" role="region" aria-label="Map of activity venues"></div>';
            wp_enqueue_style( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'bh3-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
            $payload = array(
                'id' => $map_id,
                'points' => $map_points,
                'center' => array( (float) ( $builder['map_lat'] ?? 50.53 ), (float) ( $builder['map_lng'] ?? -3.55 ) ),
                'zoom' => min( 18, max( 3, (int) ( $builder['map_zoom'] ?? 11 ) ) ),
            );
            wp_add_inline_script( 'bh3-leaflet', '(function(){const cfg=' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';function init(){const el=document.getElementById(cfg.id);if(!el||!window.L)return;const map=L.map(el,{scrollWheelZoom:false}).setView(cfg.center,cfg.zoom);L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);const bounds=[];cfg.points.forEach(p=>{const marker=L.marker([p.lat,p.lng]).addTo(map);const wrap=document.createElement("div");const title=document.createElement("strong");title.textContent=p.title;const desc=document.createElement("p");desc.textContent=[p.venue,p.town].filter(Boolean).join(", ");wrap.append(title,desc);marker.bindPopup(wrap);bounds.push([p.lat,p.lng])});if(bounds.length)map.fitBounds(bounds,{padding:[25,25],maxZoom:cfg.zoom});setTimeout(()=>map.invalidateSize(),100)}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init()})();', 'after' );
        } else { echo '<p class="bh3-map-message">No mapped venues for the activities on this page. Listings are available below.</p>'; }
        echo '<p class="bh3-map-message">Map data © OpenStreetMap contributors. Use the list below for full activity details.</p></section>';
        echo '<div class="bh3-cards">';
        foreach ( $rows as $row ) {
            $detail_page = get_page_by_path( 'activity-preview' );
            $detail_url = $detail_page && has_shortcode( (string) $detail_page->post_content, 'bubbahub_activity' )
                ? add_query_arg( 'bh_activity', absint( $row['id'] ), get_permalink( $detail_page ) ) : '';
            echo '<article><div class="bh3-meta">' . esc_html( (string) $row['category'] ) . '</div><h3>' . esc_html( $row['title'] ) . '</h3>';
            if ( $detail_url ) { echo '<p><a href="' . esc_url( $detail_url ) . '">View activity details</a></p>'; }
            echo '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $row['description'] ), 24 ) ) . '</p>';
            echo '<p class="bh3-meta">' . esc_html( (string) $row['town'] ) . ( $row['organiser'] ? ' · ' . esc_html( $row['organiser'] ) : '' ) . '</p>';
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
