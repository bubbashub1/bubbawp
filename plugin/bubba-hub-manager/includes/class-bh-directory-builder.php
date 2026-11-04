<?php
/**
 * Staging-only directory builder: configurable form, detail and search layout.
 * Leaflet + OpenStreetMap is the sole map provider. No external code is bundled.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Directory_Builder_3 {
    private const OPTION = 'bh3_directory_builder';
    public static function enabled() {
        return strtolower( (string ) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function defaults() {
        return array(
            'map_provider' => 'leaflet_osm',
            'map_zoom' => 11,
            'map_lat' => 50.53,
            'map_lng' => -3.55,
            'map_height' => 420,
            'listing_layout' => 'sidebar',
            'detail_sections' => array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser' ),
            'search_fields' => array( 'keyword', 'category', 'age', 'day', 'price', 'region', 'town' ),
        );
    }
    public static function settings() {
        $saved = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
    }
    public static function register() {
        if ( ! self::enabled() ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_directory_builder_save', array( __CLASS__, 'save' ) );
    }
    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Directory Builder', 'Directory Builder', 'manage_options', 'bh3-directory-builder', array( __CLASS__, 'render' ) );
    }
    public static function save() {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_directory_builder_save' );
        $defaults = self::defaults();
        $detail = array_values( array_intersect( array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser', 'gallery', 'accessibility' ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['detail_sections'] ?? array() ) ) ) );
        $search = array_values( array_intersect( array( 'keyword', 'category', 'age', 'day', 'price', 'region', 'town', 'free', 'term_time', 'near_me' ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['search_fields'] ?? array() ) ) ) );
        $lat = filter_var( wp_unslash( $_POST['map_lat'] ?? '' ), FILTER_VALIDATE_FLOAT );
        $lng = filter_var( wp_unslash( $_POST['map_lng'] ?? '' ), FILTER_VALIDATE_FLOAT );
        if ( $lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
            wp_die( 'Map coordinates must be valid latitude and longitude.', '', array( 'response' => 400 ) );
        }
        $layout = sanitize_key( wp_unslash( $_POST['listing_layout'] ?? '' ) );
        $settings = array(
            'map_provider' => 'leaflet_osm',
            'map_zoom' => min( 18, max( 3, absint( $_POST['map_zoom'] ?? $defaults['map_zoom'] ) ) ),
            'map_lat' => $lat,
            'map_lng' => $lng,
            'map_height' => min( 750, max( 240, absint( $_POST['map_height'] ?? $defaults['map_height'] ) ) ),
            'listing_layout' => in_array( $layout, array( 'sidebar', 'full_width' ), true ) ? $layout : 'sidebar',
            'detail_sections' => $detail,
            'search_fields' => $search,
        );
        update_option( self::OPTION, $settings, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-directory-builder', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render() {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $s = self::settings();
        echo '<div class="wrap bh3-builder"><style>
        .bh3-builder{max-width:1180px}.bh3-builder .intro{color:#596b60;max-width:850px}
        .bh3-builder .layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:20px;margin-top:22px}
        .bh3-builder .nav{display:flex;flex-direction:column;gap:8px}.bh3-builder .nav button{text-align:left;padding:14px;border:1px solid #dce6df;background:white;border-radius:9px;cursor:pointer}
        .bh3-builder .nav button[aria-selected=true]{border-color:#36754d;background:#edf6ef;font-weight:700}
        .bh3-builder .panel{background:#fff;border:1px solid #dce6df;border-radius:12px;padding:24px;margin-bottom:18px}
        .bh3-builder .panel[hidden]{display:none}.bh3-builder .checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .bh3-builder .checks label{border:1px solid #e2e8e3;border-radius:8px;padding:12px;display:flex;gap:10px;align-items:center}
        .bh3-builder .checks input{margin:0}.bh3-builder .settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .bh3-builder .settings-grid label{display:flex;flex-direction:column;gap:7px}.bh3-builder .settings-grid input,.bh3-builder .settings-grid select{width:100%;max-width:100%}
        .bh3-builder .preview{border:1px dashed #a8bbae;border-radius:12px;background:#f5f9f6;padding:20px;margin-top:18px}
        @media(max-width:760px){.bh3-builder .layout{grid-template-columns:1fr}.bh3-builder .nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.bh3-builder .checks,.bh3-builder .settings-grid{grid-template-columns:1fr}}
        </style><h1>Bubba Hub Directory Builder</h1><p class="intro">Configure the listing workflow, activity detail layout, search controls and OpenStreetMap settings. Inspired by Directorist and GeoDirectory, designed for Bubba Hub activities and recurring sessions.</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Builder settings saved on staging.</p></div>'; }
        echo '<div class="layout"><nav class="nav" aria-label="Builder sections">';
        foreach ( array( 'form' => 'Listing form', 'detail' => 'Single listing', 'search' => 'Search & filters', 'maps' => 'Maps & location' ) as $id => $name ) {
            echo '<button type="button" data-builder-tab="' . esc_attr( $id ) . '" aria-selected="' . ( $id === 'form' ? 'true' : 'false' ) . '">' . esc_html( $name ) . '</button>';
        }
        echo '</nav><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_directory_builder_save">';
        wp_nonce_field( 'bh3_directory_builder_save' );
        echo '<section class="panel" data-builder-panel="form"><h2>Listing form builder</h2><p>Seven-step activity submission with configurable custom fields. Use Field Manager to add fields, choose their wizard step and reorder them.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bh3-field-manager' ) ) . '">Manage fields</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=bh3-add-activity' ) ) . '">Preview activity wizard</a></p><div class="preview"><strong>Form sections</strong><p>Basics → About & categories → Age & pricing → Contact & booking → Images & accessibility → Locations & timetable → Review</p></div></section>';
        echo '<section class="panel" data-builder-panel="detail" hidden><h2>Single listing layout</h2><p>Choose the sections to include when the configurable activity template is connected. This does not change existing public pages yet.</p><div class="checks">';
        foreach ( array( 'hero' => 'Hero and images', 'overview' => 'Overview', 'timetable' => 'Weekly timetable', 'map' => 'Map and directions', 'contact' => 'Contact and booking', 'organiser' => 'Organiser profile', 'gallery' => 'Photo gallery', 'accessibility' => 'Accessibility' ) as $key => $label ) {
            echo '<label><input type="checkbox" name="detail_sections[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $s['detail_sections'], true ), true, false ) . '>' . esc_html( $label ) . '</label>';
        }
        echo '</div><div class="settings-grid"><label>Page width<select name="listing_layout"><option value="sidebar" ' . selected( $s['listing_layout'], 'sidebar', false ) . '>Content with sidebar</option><option value="full_width" ' . selected( $s['listing_layout'], 'full_width', false ) . '>Full width</option></select></label></div></section>';
        echo '<section class="panel" data-builder-panel="search" hidden><h2>Directory search builder</h2><p>Select the search controls for the future configurable directory template. The current directory search is unchanged.</p><div class="checks">';
        foreach ( array( 'keyword' => 'Keyword', 'category' => 'Category', 'age' => 'Child age', 'day' => 'Day of week', 'price' => 'Price', 'region' => 'Region', 'town' => 'Town', 'free' => 'Free only', 'term_time' => 'Term time', 'near_me' => 'Near me / postcode' ) as $key => $label ) {
            echo '<label><input type="checkbox" name="search_fields[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $s['search_fields'], true ), true, false ) . '>' . esc_html( $label ) . '</label>';
        }
        echo '</div></section>';
        echo '<section class="panel" data-builder-panel="maps" hidden><h2>OpenStreetMap + Leaflet</h2><p><strong>Map provider: OpenStreetMap with Leaflet (fixed).</strong> No Google Maps API or billing keys. Production maps must load Leaflet assets from a pinned, reviewed package and comply with OpenStreetMap tile usage and attribution requirements.</p><div class="settings-grid">';
        foreach ( array( 'map_lat' => 'Default latitude', 'map_lng' => 'Default longitude', 'map_zoom' => 'Default zoom (3–18)', 'map_height' => 'Map height (px)' ) as $key => $label ) {
            echo '<label>' . esc_html( $label ) . '<input type="number" name="' . esc_attr( $key ) . '" step="' . ( in_array( $key, array( 'map_lat', 'map_lng' ), true ) ? 'any' : '1' ) . '" value="' . esc_attr( (string) $s[ $key ] ) . '" required></label>';
        }
        echo '</div><div class="preview"><strong>Map implementation</strong><p>Leaflet markers for activity venues, clustering for dense results, postcode and browser geolocation search, accessible map/list switching, and attribution: © OpenStreetMap contributors.</p></div></section>';
        echo '<p><button class="button button-primary button-large" type="submit">Save builder settings</button></p></form></div>';
        echo '<script>(function(){const root=document.querySelector(".bh3-builder");if(!root)return;const tabs=[...root.querySelectorAll("[data-builder-tab]")],panels=[...root.querySelectorAll("[data-builder-panel]")];tabs.forEach(tab=>tab.addEventListener("click",()=>{tabs.forEach(t=>t.setAttribute("aria-selected",t===tab?"true":"false"));panels.forEach(p=>p.hidden=p.dataset.builderPanel!==tab.dataset.builderTab)}));})();</script></div>';
    }
}
