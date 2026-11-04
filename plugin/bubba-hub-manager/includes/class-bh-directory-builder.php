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
            'detail_order' => array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser', 'gallery', 'accessibility' ),
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
        $allowed_sections = array( 'hero', 'overview', 'timetable', 'map', 'contact', 'organiser', 'gallery', 'accessibility' );
        $raw_order = isset( $_POST['detail_order'] ) && is_scalar( $_POST['detail_order'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['detail_order'] ) ) ) : array();
        $order = array_values( array_unique( array_intersect( $raw_order, $allowed_sections ) ) );
        $order = array_merge( $order, array_values( array_diff( $allowed_sections, $order ) ) );
        $search = array_values( array_intersect( array( 'keyword', 'category', 'age', 'day', 'region', 'town', 'price', 'free', 'term_time', 'near_me' ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['search_fields'] ?? array() ) ) ) );
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
            'detail_order' => $order,
            'search_fields' => $search,
        );
        update_option( self::OPTION, $settings, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-directory-builder', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render() {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $custom_fields = array();
        if ( class_exists( 'BH_Field_Manager_3' ) && BH_Field_Manager_3::ready() ) {
            foreach ( BH_Field_Manager_3::definitions_with_placement( 'activity' ) as $definition ) {
                $step = sanitize_key( $definition['placement']['wizard_step'] ?? 'about' );
                if ( ! in_array( $step, array( 'basics', 'about', 'pricing', 'contact', 'images', 'locations' ), true ) ) { continue; }
                $custom_fields[ $step ][] = array(
                    'label' => sanitize_text_field( $definition['label'] ),
                    'type' => sanitize_key( $definition['field_type'] ),
                    'required' => ! empty( $definition['is_required'] ),
                );
            }
        }
        $s = self::settings();
        echo '<div class="wrap bh3-builder"><style>
        .bh3-builder{max-width:1180px;color:#203c31}
        .bh3-builder h1{font-size:30px;line-height:1.2;font-weight:750;letter-spacing:-.025em;margin:22px 0 12px}
        .bh3-builder h2{font-size:21px;margin:0 0 12px;letter-spacing:-.015em}
        .bh3-builder .intro{font-size:15px;line-height:1.7}
        .bh3-builder .nav button{font-size:14px;line-height:1.4;transition:background .15s,border-color .15s,box-shadow .15s}
        .bh3-builder .nav button:hover{background:#f4f8f5;border-color:#8db49b}
        .bh3-builder .nav button:focus-visible,.bh3-builder .checks input:focus-visible{outline:3px solid #7cbf95;outline-offset:2px}
        .bh3-builder .panel{box-shadow:0 12px 32px rgba(20,60,40,.055)}
        .bh3-builder .checks label{background:#fcfdfc;cursor:pointer;min-height:48px;transition:background .15s,border-color .15s}
        .bh3-builder .checks label:hover{background:#f0f8f2;border-color:#8db49b}
        .bh3-builder .settings-grid{margin-top:20px}
        .bh3-builder .settings-grid label{font-weight:600}
        .bh3-builder .detail-sort{display:flex;flex-direction:column;gap:9px;margin:18px 0}.bh3-builder .detail-sort .sort-row{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1px solid #d9e5dc;background:#fff;border-radius:10px}.bh3-builder .detail-sort .sort-row[draggable=true]{cursor:grab}.bh3-builder .detail-sort .sort-row.dragging{opacity:.5}.bh3-builder .detail-sort .sort-row .sort-name{flex:1;font-weight:650}.bh3-builder .detail-sort .sort-row button{min-width:34px;min-height:34px;border:1px solid #d4e0d7;background:#f7faf8;border-radius:7px;cursor:pointer}.bh3-builder .detail-sort .sort-row button:focus-visible{outline:3px solid #7cbf95;outline-offset:2px}
.bh3-builder .bh3-live-preview{margin-top:22px;padding:20px;background:#f7faf8;border:1px solid #dce9df;border-radius:16px}
        .bh3-builder .bh3-live-preview h3{margin:0 0 12px;font-size:16px}
        .bh3-builder .bh3-preview-page{background:#fff;border:1px solid #dce7df;border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px;max-width:780px;margin:auto}
        .bh3-builder .bh3-preview-page.is-full{max-width:100%}
        .bh3-builder .bh3-preview-section{background:#f3f7f4;border:1px solid #dfe9e1;border-radius:10px;padding:12px 14px;font-size:13px}
        .bh3-builder .bh3-preview-section[data-preview-key=hero]{background:#e4f1e8;padding:22px}
        .bh3-builder .bh3-preview-section strong{display:block;font-size:14px;margin-bottom:5px}
        .bh3-builder .bh3-preview-section[hidden]{display:none}
        .bh3-builder .bh3-preview-caption{font-size:12px;color:#596f61;margin:12px 0 0}
        .bh3-builder .button-primary{background:#176345;border-color:#176345}
        .bh3-builder .button-primary:hover{background:#104b35;border-color:#104b35}.bh3-builder .intro{color:#596b60;max-width:850px}
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
        .bh3-builder .bh3-wizard-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}
        .bh3-builder .bh3-wizard-field{display:flex;flex-direction:column;gap:6px;font-weight:600;font-size:13px;min-width:0}
        .bh3-builder .bh3-wizard-field input,.bh3-builder .bh3-wizard-field select,.bh3-builder .bh3-wizard-field textarea{width:100%;max-width:100%;min-height:42px;border:1px solid #cfdcd2;border-radius:7px;background:#f8faf8;padding:8px;color:#385344}
        .bh3-builder .bh3-wizard-field textarea{min-height:76px}
        .bh3-builder .bh3-wizard-field[hidden]{display:none}
        .bh3-builder .bh3-wizard-field-wide{grid-column:1/-1}
        @media(max-width:600px){.bh3-builder .bh3-wizard-fields{grid-template-columns:1fr}.bh3-builder .bh3-wizard-steps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.bh3-builder .bh3-wizard-steps button{font-size:12px;padding:9px 7px;text-align:left}.bh3-builder .bh3-wizard-stage{padding:14px}}
        .bh3-builder .bh3-wizard-preview{margin-top:20px;border:1px solid #dce6df;border-radius:14px;padding:18px;background:#f8faf8}
        .bh3-builder .bh3-wizard-steps{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}
        .bh3-builder .bh3-wizard-steps button{border:1px solid #cddfd3;border-radius:9px;padding:9px 12px;background:#fff;cursor:pointer;font-weight:600}
        .bh3-builder .bh3-wizard-steps button[aria-current=step]{background:#176345;color:#fff;border-color:#176345}
        /* Mobile-first wizard navigation: one scrollable row, not a seven-button grid. */
        .bh3-builder .bh3-wizard-steps{display:flex;flex-wrap:nowrap;overflow-x:auto;overscroll-behavior-inline:contain;scroll-snap-type:x proximity;scrollbar-width:thin;gap:7px;padding:2px 2px 9px}
        .bh3-builder .bh3-wizard-steps button{flex:0 0 auto;white-space:nowrap;scroll-snap-align:start;min-height:40px}
        .bh3-builder .bh3-wizard-field input,.bh3-builder .bh3-wizard-field select,.bh3-builder .bh3-wizard-field textarea{min-height:38px}
        .bh3-builder .bh3-wizard-field textarea{min-height:64px}
        @media(max-width:600px){.bh3-builder .bh3-wizard-preview{padding:12px}.bh3-builder .bh3-wizard-steps{display:flex;flex-wrap:nowrap;gap:6px;margin:10px 0}.bh3-builder .bh3-wizard-steps button{font-size:12px;padding:8px 10px;text-align:center}.bh3-builder .bh3-wizard-stage{padding:12px;min-height:0}.bh3-builder .bh3-wizard-fields{gap:10px;margin-top:12px}.bh3-builder .bh3-wizard-field{gap:4px}.bh3-builder .bh3-wizard-field input,.bh3-builder .bh3-wizard-field select{min-height:38px}.bh3-builder .bh3-wizard-field textarea{min-height:60px}}

        .bh3-builder .bh3-wizard-stage{background:#fff;border:1px solid #dce6df;border-radius:10px;padding:20px;min-height:130px}
        .bh3-builder .bh3-wizard-stage h3{margin:0 0 10px;font-size:18px}
        .bh3-builder .bh3-wizard-actions{display:flex;justify-content:space-between;gap:10px;margin-top:12px}
        .bh3-builder .bh3-wizard-actions button{padding:9px 16px;border:1px solid #176345;background:#fff;color:#176345;border-radius:8px;cursor:pointer}
        .bh3-builder .bh3-wizard-actions button:disabled{opacity:.4;cursor:not-allowed}
        .bh3-builder .bh3-map-preview{margin-top:20px;border:1px solid #dce6df;border-radius:14px;overflow:hidden;background:#e7f0e9}
        .bh3-builder .bh3-map-canvas{min-height:220px;position:relative;display:grid;place-items:center;background:repeating-linear-gradient(35deg,transparent 0 36px,#cde0d2 37px 42px,transparent 43px 90px),repeating-linear-gradient(125deg,#e7f0e9 0 48px,#d9e8db 49px 55px,#e7f0e9 56px 105px)}
        .bh3-builder .bh3-map-pin{width:28px;height:28px;border-radius:50% 50% 50% 0;background:#176345;transform:rotate(-45deg);box-shadow:0 4px 12px #1a4b3270;position:relative}
        .bh3-builder .bh3-map-pin:after{content:"";position:absolute;inset:8px;border-radius:50%;background:#fff}
        .bh3-builder .bh3-map-meta{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:12px 16px;background:#fff;font-size:13px}
        .bh3-builder .bh3-map-preview p{margin:0;padding:10px 16px;font-size:12px;color:#566d5d;background:#f7faf8}
        .bh3-builder,.bh3-builder *{box-sizing:border-box}
        .bh3-builder .layout,.bh3-builder .layout>*,.bh3-builder .panel,.bh3-builder form{min-width:0}
        .bh3-builder .panel{overflow:visible}
        .bh3-builder .preview{position:relative;margin:18px 0 0;max-width:100%;overflow-wrap:anywhere}
        .bh3-builder .panel>p{line-height:1.65}
        .bh3-builder .panel .button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;white-space:normal;text-align:center;margin:0 6px 8px 0}
        .bh3-builder .settings-grid input{min-height:44px}
        .bh3-builder .nav button{min-height:58px}
        .bh3-builder .panel h2{line-height:1.3}
        @media(max-width:782px){
          .bh3-builder{margin:10px 12px 20px 0;max-width:100%}
          .bh3-builder h1{font-size:26px}
          .bh3-builder .layout{gap:16px;margin-top:16px}
          .bh3-builder .nav{gap:10px}
          .bh3-builder .nav button{padding:14px 12px;min-height:64px}
          .bh3-builder .panel{padding:20px 16px;margin-bottom:16px;border-radius:14px}
          .bh3-builder .preview{padding:16px;margin-top:16px}
          .bh3-builder .settings-grid{gap:18px}
          .bh3-builder .panel .button{max-width:100%}
          .bh3-builder .bh3-live-preview{padding:14px}
        }
        @media(max-width:420px){
          .bh3-builder .panel .button{width:100%;margin-right:0}
          .bh3-builder .nav button{font-size:13px}
          .bh3-builder .detail-sort .sort-row{padding:10px 8px;gap:6px}
        }
        </style><h1>Bubba Hub Directory Builder</h1><p class="intro">Configure the listing workflow, activity detail layout, search controls and OpenStreetMap settings. Inspired by Directorist and GeoDirectory, designed for Bubba Hub activities and recurring sessions.</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Builder settings saved on staging.</p></div>'; }
        echo '<div class="layout"><nav class="nav" aria-label="Builder sections">';
        foreach ( array( 'form' => 'Listing form', 'detail' => 'Single listing', 'search' => 'Search & filters', 'maps' => 'Maps & location' ) as $id => $name ) {
            echo '<button type="button" data-builder-tab="' . esc_attr( $id ) . '" aria-selected="' . ( $id === 'form' ? 'true' : 'false' ) . '">' . esc_html( $name ) . '</button>';
        }
        echo '</nav><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_directory_builder_save">';
        wp_nonce_field( 'bh3_directory_builder_save' );
        echo '<section class="panel" data-builder-panel="form"><h2>Listing form builder</h2><p>Seven-step activity submission with configurable custom fields. Use Field Manager to add fields, choose their wizard step and reorder them.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bh3-field-manager' ) ) . '">Manage fields</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=bh3-add-activity' ) ) . '">Preview activity wizard</a></p><div class="bh3-wizard-preview"><strong>Submission workflow preview</strong><p>Explore the seven stages below. This preview shows example fields plus active custom activity fields from Field Manager. It does not save listing data.</p><div class="bh3-wizard-steps" role="group" aria-label="Wizard steps">';
        $wizard_steps = array(
            array( 'Basics', 'Activity title, organiser and listing type' ),
            array( 'About & categories', 'Description, categories and tags' ),
            array( 'Age & pricing', 'Age range, cost and free activities' ),
            array( 'Contact & booking', 'Website, email, phone and booking link' ),
            array( 'Images & accessibility', 'Gallery, images and accessibility details' ),
            array( 'Locations & timetable', 'Linked venues, weekly sessions and term times' ),
            array( 'Review', 'Check the listing before publishing' ),
        );
        foreach ( $wizard_steps as $index => $step ) {
            echo '<button type="button" data-wizard-step="' . esc_attr( (string) $index ) . '" aria-current="' . ( $index === 0 ? 'step' : 'false' ) . '">' . esc_html( (string) ( $index + 1 ) . '. ' . $step[0] ) . '</button>';
        }
        echo '</div><div class="bh3-wizard-stage" aria-live="polite"><h3 id="bh3-wizard-heading">Basics</h3><p id="bh3-wizard-description">Activity title, organiser and listing type</p><div class="bh3-wizard-fields" id="bh3-wizard-fields" aria-label="Example fields for this step"></div></div><div class="bh3-wizard-actions"><button type="button" data-wizard-prev disabled>Previous</button><button type="button" data-wizard-next>Next step</button></div></div></section>';
        echo '<section class="panel" data-builder-panel="detail" hidden><h2>Single listing layout</h2><p>Choose the sections shown on the staging activity page. Changes take effect after saving.</p><div class="checks">';
        foreach ( array( 'hero' => 'Hero and images', 'overview' => 'Overview', 'timetable' => 'Weekly timetable', 'map' => 'Map and directions', 'contact' => 'Contact and booking', 'organiser' => 'Organiser profile', 'gallery' => 'Photo gallery', 'accessibility' => 'Accessibility' ) as $key => $label ) {
            echo '<label><input type="checkbox" name="detail_sections[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $s['detail_sections'], true ), true, false ) . '>' . esc_html( $label ) . '</label>';
        }
        echo '</div><h3>Section order</h3><p>Drag sections to reorder, or use the up/down buttons for keyboard and touch accessibility.</p><div class="detail-sort" id="bh3-detail-sort">';
        $names = array( 'hero' => 'Hero and images', 'overview' => 'Overview', 'timetable' => 'Weekly timetable', 'map' => 'Locations', 'contact' => 'Contact and booking', 'organiser' => 'Organiser', 'gallery' => 'Photo gallery', 'accessibility' => 'Accessibility' );
        $order = array_values( array_unique( array_merge( array_intersect( (array) ( $s['detail_order'] ?? array() ), array_keys( $names ) ), array_keys( $names ) ) ) );
        foreach ( $order as $key ) {
            echo '<div class="sort-row" draggable="true" data-sort-key="' . esc_attr( $key ) . '"><span aria-hidden="true">☰</span><span class="sort-name">' . esc_html( $names[ $key ] ) . '</span><button type="button" data-move="-1" aria-label="Move ' . esc_attr( $names[ $key ] ) . ' up">↑</button><button type="button" data-move="1" aria-label="Move ' . esc_attr( $names[ $key ] ) . ' down">↓</button></div>';
        }
        echo '</div><div class="bh3-live-preview" aria-label="Activity layout preview"><h3>Live layout preview</h3><div class="bh3-preview-page" id="bh3-preview-page">';
        foreach ( $order as $key ) {
            $hint = array( 'hero' => 'Activity title, category and organiser', 'overview' => 'Description and activity information', 'timetable' => 'Weekly sessions and times', 'map' => 'Venue locations and directions', 'contact' => 'Booking link and contact options', 'organiser' => 'Organiser profile and website', 'gallery' => 'Reserved for future photo gallery', 'accessibility' => 'Reserved for future accessibility details' );
            echo '<div class="bh3-preview-section" data-preview-key="' . esc_attr( $key ) . '"><strong>' . esc_html( $names[ $key ] ) . '</strong>' . esc_html( $hint[ $key ] ) . '</div>';
        }
        echo '</div><p class="bh3-preview-caption">Schematic preview. Uses sample labels, not a real activity. Gallery and accessibility blocks are not yet rendered on public pages.</p></div><input type="hidden" name="detail_order" id="bh3-detail-order" value="' . esc_attr( implode( ',', $order ) ) . '"><div class="settings-grid"><label>Page width<select name="listing_layout"><option value="sidebar" ' . selected( $s['listing_layout'], 'sidebar', false ) . '>Content with sidebar</option><option value="full_width" ' . selected( $s['listing_layout'], 'full_width', false ) . '>Full width</option></select></label></div></section>';
        echo '<section class="panel" data-builder-panel="search" hidden><h2>Directory search builder</h2><p>Choose the filters shown on the staging directory. Changes take effect after saving. Price filters use the starting price; Near Me supports a distance radius when location is available.</p><div class="checks">';
        foreach ( array( 'keyword' => 'Keyword', 'category' => 'Category', 'age' => 'Child age', 'day' => 'Day of week', 'region' => 'Region', 'town' => 'Town', 'price' => 'Maximum price', 'free' => 'Free only', 'term_time' => 'Term time', 'near_me' => 'Near me / postcode' ) as $key => $label ) {
            echo '<label><input type="checkbox" name="search_fields[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $s['search_fields'], true ), true, false ) . '>' . esc_html( $label ) . '</label>';
        }
        echo '</div></section>';
        echo '<section class="panel" data-builder-panel="maps" hidden><h2>OpenStreetMap + Leaflet</h2><p><strong>Map provider: OpenStreetMap with Leaflet (fixed).</strong> No Google Maps API or billing keys. Production maps must load Leaflet assets from a pinned, reviewed package and comply with OpenStreetMap tile usage and attribution requirements.</p><div class="settings-grid">';
        foreach ( array( 'map_lat' => 'Default latitude', 'map_lng' => 'Default longitude', 'map_zoom' => 'Default zoom (3–18)', 'map_height' => 'Map height (px)' ) as $key => $label ) {
            echo '<label>' . esc_html( $label ) . '<input type="number" name="' . esc_attr( $key ) . '" step="' . ( in_array( $key, array( 'map_lat', 'map_lng' ), true ) ? 'any' : '1' ) . '" value="' . esc_attr( (string) $s[ $key ] ) . '" required></label>';
        }
        echo '</div><div class="bh3-map-preview" aria-label="Map settings preview"><div class="bh3-map-canvas"><span class="bh3-map-pin" aria-hidden="true"></span></div><div class="bh3-map-meta"><strong>Map centre: <output id="bh3-map-coordinates">' . esc_html( $s['map_lat'] . ', ' . $s['map_lng'] ) . '</output></strong><span>Zoom <output id="bh3-map-zoom">' . esc_html( (string) $s['map_zoom'] ) . '</output> · Height <output id="bh3-map-height">' . esc_html( (string) $s['map_height'] ) . '</output>px</span></div><p>Illustrative settings preview, not a live map. The public directory uses OpenStreetMap and Leaflet.</p></div><div class="preview"><strong>Map implementation</strong><p>Leaflet markers for activity venues, clustering for dense results, postcode and browser geolocation search, accessible map/list switching, and attribution: © OpenStreetMap contributors.</p></div></section>';
        echo '<p><button class="button button-primary button-large" type="submit">Save builder settings</button></p></form></div>';
        echo '<script type="application/json" id="bh3-custom-fields-data">' . wp_json_encode( $custom_fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
        echo '<script>(function(){const root=document.querySelector(".bh3-builder");if(!root)return;const steps=[...root.querySelectorAll("[data-wizard-step]")],heading=root.querySelector("#bh3-wizard-heading"),description=root.querySelector("#bh3-wizard-description"),prev=root.querySelector("[data-wizard-prev]"),next=root.querySelector("[data-wizard-next]");if(!steps.length)return;const names=["Basics","About & categories","Age & pricing","Contact & booking","Images & accessibility","Locations & timetable","Review"],hints=["Activity title, organiser and listing type","Description, categories and tags","Age range, cost and free activities","Website, email, phone and booking link","Gallery, images and accessibility details","Linked venues, weekly sessions and term times","Check the listing before publishing"];const samples=[
[["Activity title","text"],["Organiser","text"],["Listing type","select"]],
[["Description","textarea"],["Primary category","select"],["Keywords","text"]],
[["Minimum age (months)","number"],["Maximum age (months)","number"],["Price","text"]],
[["Website","url"],["Contact email","email"],["Booking URL","url"]],
[["Listing image","text"],["Accessibility information","textarea"]],
[["Venue","text"],["Day of week","select"],["Session time","text"]],
[["Activity summary","textarea"],["Publication status","select"]]
];const stepKeys=["basics","about","pricing","contact","images","locations","review"];let custom={};try{custom=JSON.parse(document.getElementById("bh3-custom-fields-data")?.textContent||"{}");}catch(e){custom={};}const fieldbox=root.querySelector("#bh3-wizard-fields");const renderFields=step=>{if(!fieldbox)return;fieldbox.replaceChildren();const configured=Array.isArray(custom[stepKeys[step]])?custom[stepKeys[step]]:[];const fields=[...samples[step],...configured.map(f=>[f.label+(f.required?" *":""),["textarea","select","number","email","url","date"].includes(f.type)?f.type:"text"])];fields.forEach(([label,type])=>{const wrap=document.createElement("label");wrap.className="bh3-wizard-field"+(type==="textarea"?" bh3-wizard-field-wide":"");const title=document.createElement("span");title.textContent=label;wrap.appendChild(title);let input;if(type==="textarea"){input=document.createElement("textarea");}else if(type==="select"){input=document.createElement("select");const option=document.createElement("option");option.textContent="Select "+label.toLowerCase();input.appendChild(option);}else{input=document.createElement("input");input.type=type;input.placeholder="Example "+label.toLowerCase();}input.disabled=true;input.setAttribute("aria-label",label+" (preview only)");wrap.appendChild(input);fieldbox.appendChild(wrap);});};let index=0;const show=i=>{index=Math.max(0,Math.min(steps.length-1,i));steps.forEach((b,n)=>b.setAttribute("aria-current",n===index?"step":"false"));steps[index].scrollIntoView({behavior:"smooth",block:"nearest",inline:"nearest"});heading.textContent=names[index];description.textContent=hints[index];renderFields(index);prev.disabled=index===0;next.disabled=index===steps.length-1;};steps.forEach((b,i)=>b.addEventListener("click",()=>show(i)));prev.addEventListener("click",()=>show(index-1));next.addEventListener("click",()=>show(index+1));show(0);})();</script>';
        echo '<script>(function(){const builder=document.querySelector(".bh3-builder");if(builder){const form=builder.querySelector("form");if(form){const fields=["map_lat","map_lng","map_zoom","map_height"];const targets=["bh3-map-coordinates","bh3-map-zoom","bh3-map-height"];const update=()=>{const vals=fields.map(k=>form.elements.namedItem(k)?.value||"—");targets.forEach((id,i)=>{const el=builder.querySelector("#"+id);if(el)el.textContent=i===0?vals[0]+", "+vals[1]:vals[i+1]});};fields.forEach(k=>form.elements.namedItem(k)?.addEventListener("input",update));}}})();</script>';
        echo '<script>(function(){const root=document.querySelector(".bh3-builder");if(!root)return;const tabs=[...root.querySelectorAll("[data-builder-tab]")],panels=[...root.querySelectorAll("[data-builder-panel]")];tabs.forEach(tab=>tab.addEventListener("click",()=>{tabs.forEach(t=>t.setAttribute("aria-selected",t===tab?"true":"false"));panels.forEach(p=>p.hidden=p.dataset.builderPanel!==tab.dataset.builderTab)}));const list=root.querySelector("#bh3-detail-sort"),input=root.querySelector("#bh3-detail-order");if(list&&input){const preview=root.querySelector("#bh3-preview-page");const sync=()=>{const order=[...list.children].map(el=>el.dataset.sortKey);input.value=order.join(",");if(preview){order.forEach(key=>{const el=preview.querySelector("[data-preview-key="+key+"]");if(el)preview.appendChild(el)});preview.querySelectorAll("[data-preview-key]").forEach(el=>{const checkbox=[...root.querySelectorAll("input")].find(i=>i.name==="detail_sections[]"&&i.value===el.dataset.previewKey);el.hidden=!checkbox?.checked})}};[...root.querySelectorAll("input")].filter(i=>i.name==="detail_sections[]").forEach(el=>el.addEventListener("change",sync));const width=[...root.querySelectorAll("select")].find(i=>i.name==="listing_layout");if(width){const applyWidth=()=>preview?.classList.toggle("is-full",width.value==="full_width");width.addEventListener("change",applyWidth);applyWidth()}sync();list.addEventListener("click",e=>{const button=e.target.closest("button[data-move]");if(!button)return;const row=button.closest(".sort-row"),delta=Number(button.dataset.move),other=delta<0?row.previousElementSibling:row.nextElementSibling;if(!other)return;if(delta<0)list.insertBefore(row,other);else list.insertBefore(other,row);sync();button.focus()});let dragged=null;list.addEventListener("dragstart",e=>{dragged=e.target.closest(".sort-row");if(dragged){dragged.classList.add("dragging");e.dataTransfer.effectAllowed="move"}});list.addEventListener("dragover",e=>{if(!dragged)return;const over=e.target.closest(".sort-row");if(!over||over===dragged)return;e.preventDefault();const after=e.clientY>over.getBoundingClientRect().top+over.offsetHeight/2;list.insertBefore(dragged,after?over.nextSibling:over)});list.addEventListener("drop",e=>{if(dragged){e.preventDefault();sync()}});list.addEventListener("dragend",()=>{if(dragged)dragged.classList.remove("dragging");dragged=null;sync()})}})();</script></div>';
    }
}
