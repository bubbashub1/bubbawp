<?php
/** Bubba Hub's existing seven-step listing workflow, adapted to the BH3 WordPress schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Create_3 {
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function register() {
        add_shortcode( 'bh_admin_activity_wizard', array( __CLASS__, 'render_frontend' ) );
        add_submenu_page( 'bubba-hub-3-status', 'Add Activity', 'Add Activity', 'manage_options', 'bh3-add-activity', array( __CLASS__, 'render' ) );
    }
    /** Reuse the validated seven-step admin wizard on a staging front-end page. */
    public static function render_frontend() {
        if ( ! self::allowed() ) {
            return '<p>This activity creation tool is available to staging administrators only.</p>';
        }
        ob_start();
        self::render();
        return ob_get_clean();
    }
    public static function register_save() {
        add_action( 'admin_post_bh3_create_activity', array( __CLASS__, 'save' ) );
    }
    private static function field( $name, $label, $type = 'text', $value = '', $extra = '' ) {
        echo '<label class="bh3-field"><span>' . esc_html( $label ) . '</span>';
        if ( $type === 'textarea' ) {
            echo '<textarea name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( (string) $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" ' . $extra . '>';
        }
        echo '</label>';
    }
    /** Renders configurable custom fields within the designated wizard step. */
    private static function custom_fields_for_step( $step, $definitions, $values ) {
        $open = false; $last_group = '';
        foreach ( $definitions as $field ) {
            if ( ( $field['placement']['wizard_step'] ?? 'about' ) !== $step ) { continue; }
            if ( ! $open ) { echo '<div class="bh3-custom-fields bh3-wide"><h3>Additional fields</h3><div class="bh3-fields">'; $open = true; }
            if ( $last_group !== $field['group_name'] ) {
                echo '<h4 class="bh3-wide">' . esc_html( $field['group_name'] ) . '</h4>';
                $last_group = $field['group_name'];
            }
            $id = (int) $field['field_id']; $kind = $field['field_type'];
            $value = $values[ $id ] ?? ''; $name = 'bh3_custom_fields[' . $id . ']';
            $options = json_decode( $field['options_json'] ?: '[]', true );
            if ( ! is_array( $options ) ) { $options = array(); }
            echo '<label class="bh3-field" data-bh3-required="' . ( $field['is_required'] ? '1' : '0' ) . '"><span>' . esc_html( $field['label'] ) . ( $field['is_required'] ? ' *' : '' ) . '</span>';
            if ( $kind === 'textarea' ) {
                echo '<textarea name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( (string) $value ) . '</textarea>';
            } elseif ( $kind === 'checkbox' ) {
                echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . '>';
            } elseif ( in_array( $kind, array( 'select', 'multiselect' ), true ) ) {
                $multiple = $kind === 'multiselect';
                echo '<select name="' . esc_attr( $name . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple size="4"' : '' ) . '>';
                if ( ! $multiple ) { echo '<option value="">Choose an option</option>'; }
                foreach ( $options as $option ) {
                    $is_selected = $multiple ? in_array( $option, (array) $value, true ) : $option === $value;
                    echo '<option value="' . esc_attr( $option ) . '" ' . selected( $is_selected, true, false ) . '>' . esc_html( $option ) . '</option>';
                }
                echo '</select>';
            } else {
                $input_type = in_array( $kind, array( 'number', 'email', 'url', 'date', 'time' ), true ) ? $kind : 'text';
                echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
            }
            echo '</label>';
        }
        if ( $open ) { echo '</div></div>'; }
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
        echo '<div class="wrap bh3-wizard"><h1>' . ( absint( $_GET['activity_id'] ?? 0 ) ? 'Edit draft activity' : 'Add activity' ) . '</h1>';
        if ( ! self::allowed() ) { echo '<p>Activity creation is restricted to staging administrators.</p></div>'; return; }
        if ( isset( $_GET['created'] ) ) {
            echo '<div class="notice notice-success"><p>Draft created successfully. ID: ' . esc_html( (string) absint( $_GET['created'] ) ) . '</p></div>';
        }
        global $wpdb;
        $edit_id = absint( $_GET['activity_id'] ?? 0 );
        $existing = null;
        $existing_venue_ids = array();
        $existing_sessions = array();
        if ( $edit_id ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}activities` WHERE id=%d AND status='draft' LIMIT 1", $edit_id ), ARRAY_A );
            if ( ! $existing ) { wp_die( 'Only existing draft activities can be edited.', '', array( 'response' => 404 ) ); }
            $existing_venue_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT venue_id FROM `{$wpdb->prefix}activity_venues` WHERE activity_id=%d", $edit_id ) ) );
            $existing_sessions = $wpdb->get_results( $wpdb->prepare( "SELECT day_of_week AS day, LEFT(start_time,5) AS start, LEFT(end_time,5) AS end, venue_id, term_time_only FROM `{$wpdb->prefix}sessions` WHERE activity_id=%d AND frequency='weekly' AND status='scheduled' ORDER BY id ASC LIMIT 100", $edit_id ), ARRAY_A );
        }
        $organisers = $wpdb->get_results( "SELECT id,name FROM `{$wpdb->prefix}organisers` ORDER BY name ASC LIMIT 1000", ARRAY_A );
        $venues_table = $wpdb->prefix . 'venues';
        $venues = array();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $venues_table ) ) ) === $venues_table ) {
            $venue_columns = $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $venues_table ) . '`', 0 );
            if ( is_array( $venue_columns ) && in_array( 'id', $venue_columns, true ) && in_array( 'name', $venue_columns, true ) ) {
                $town_column = in_array( 'town', $venue_columns, true ) ? 'town' : ( in_array( 'city', $venue_columns, true ) ? 'city' : null );
                $venue_select = 'id, name' . ( $town_column ? ', `' . $town_column . '` AS town' : ', NULL AS town' );
                $venues = $wpdb->get_results( 'SELECT ' . $venue_select . ' FROM `' . esc_sql( $venues_table ) . '` ORDER BY name ASC LIMIT 2000', ARRAY_A );
            }
        }
        $bh3_custom_definitions = class_exists( 'BH_Field_Manager_3' ) && BH_Field_Manager_3::ready()
            ? BH_Field_Manager_3::definitions_with_placement( 'activity' ) : array();
        $bh3_custom_values = $edit_id && $bh3_custom_definitions
            ? BH_Field_Manager_3::values_for( 'activity', $edit_id ) : array();
        echo '<p>Seven steps with configurable custom fields from Field Manager. Save creates a draft; existing venues and weekly sessions can be added in step six.</p>';
        echo '<style>
        .bh3-wizard{max-width:1100px}.bh3-wizard-layout{display:grid;grid-template-columns:210px minmax(0,1fr);gap:22px;margin-top:20px}
        .bh3-steps{display:flex;flex-direction:column;gap:6px}.bh3-steps button{text-align:left;padding:12px;border:1px solid #d5e3d9;border-radius:9px;background:#fff;cursor:pointer}.bh3-steps button[aria-current=step]{background:#eaf3ed;border-color:#397253;font-weight:700}
        .bh3-panel{background:#fff;border:1px solid #dce5de;border-radius:12px;padding:22px;min-width:0}.bh3-panel[hidden]{display:none}
        .bh3-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.bh3-field{display:flex;flex-direction:column;gap:6px;min-width:0;font-weight:600}.bh3-field input,.bh3-field textarea,.bh3-field select{width:100%;max-width:100%;box-sizing:border-box}
        .bh3-wide{grid-column:1/-1}.bh3-actions{display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;margin-top:22px}.bh3-actions .button{min-height:36px}.bh3-actions [hidden]{display:none!important}
        .bh3-review{background:#f6f9f6;padding:14px;border-radius:9px;line-height:1.8}.bh3-custom-fields{margin-top:20px;padding:18px;border:1px solid #dce5de;border-radius:10px;background:#f9fbfa}.bh3-custom-fields>h3{margin:0 0 14px}.bh3-custom-fields h4{margin:4px 0 0;color:#34684a}
        .bh3-location-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}.bh3-venue-results{max-height:280px;overflow:auto;border:1px solid #dcdcde;border-radius:8px;padding:8px;margin-top:10px}.bh3-venue-option{display:flex;gap:8px;align-items:flex-start;padding:8px;border-bottom:1px solid #eee}.bh3-venue-option:last-child{border:0}.bh3-session-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;align-items:end;padding:12px;border:1px solid #dcdcde;border-radius:8px;margin:0 0 10px}.bh3-session-row label{display:flex;flex-direction:column;gap:5px;font-weight:600}.bh3-session-row input,.bh3-session-row select{max-width:100%;width:100%}.bh3-session-row .bh3-session-term{display:flex;align-items:center;gap:6px;white-space:nowrap}.bh3-session-row .bh3-session-term input{width:auto}@media(max-width:782px){.bh3-location-grid{grid-template-columns:1fr}.bh3-session-row{grid-template-columns:1fr 1fr}.bh3-session-row .bh3-session-remove{grid-column:1/-1}}
        /* Wizard controls: prevent WP admin styles from stretching checkboxes and timetable inputs. */
        .bh3-wizard{max-width:1320px}
        .bh3-wizard .bh3-field:has(input[type=checkbox]){display:flex;flex-direction:row-reverse;justify-content:flex-end;align-items:center;gap:10px;min-height:40px;font-weight:600}
        .bh3-wizard .bh3-field input[type=checkbox],.bh3-wizard .bh3-venue-option input[type=checkbox],.bh3-wizard .bh3-session-term input[type=checkbox]{width:18px!important;min-width:18px;max-width:18px!important;height:18px!important;min-height:18px;margin:0!important;flex:0 0 18px;accent-color:#397253}
        .bh3-wizard .bh3-field:has(input[type=checkbox]) input{box-sizing:border-box}
        .bh3-location-grid{grid-template-columns:minmax(0,0.85fr) minmax(0,1.35fr);gap:20px}
        .bh3-location-grid>div{min-width:0}
        .bh3-venue-option{align-items:center;min-height:38px;box-sizing:border-box}
        .bh3-session-row{grid-template-columns:minmax(90px,1.15fr) minmax(100px,1fr) minmax(100px,1fr) minmax(125px,1.2fr);gap:10px;align-items:end}
        .bh3-session-row label{min-width:0}
        .bh3-session-row input[type=time],.bh3-session-row select{min-width:0;max-width:100%;width:100%;box-sizing:border-box}
        .bh3-session-row .bh3-session-remove{grid-column:4;justify-self:end}
        .bh3-session-row .bh3-session-term{grid-column:1/4;grid-row:auto;display:flex;flex-direction:row;align-items:center;gap:8px}
        .bh3-wizard .bh3-actions .button{border-radius:7px;padding:5px 16px}
        @media(max-width:1150px){.bh3-location-grid{grid-template-columns:1fr}.bh3-session-row{grid-template-columns:repeat(2,minmax(0,1fr))}.bh3-session-row .bh3-session-remove{grid-column:2}.bh3-session-row .bh3-session-term{grid-column:1}}
        @media(max-width:560px){.bh3-session-row{grid-template-columns:1fr}.bh3-session-row .bh3-session-remove,.bh3-session-row .bh3-session-term{grid-column:1}.bh3-steps{grid-template-columns:1fr 1fr}}
        @media(max-width:782px){.bh3-wizard-layout{grid-template-columns:1fr}.bh3-steps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.bh3-fields{grid-template-columns:1fr}.bh3-wide{grid-column:auto}.bh3-panel{padding:14px}}
        .bh3-day-grid{display:flex;flex-direction:column;gap:10px}.bh3-day-card{border:1px solid #d7e4dc;border-radius:10px;background:#fff;padding:12px}.bh3-day-header{display:flex;align-items:center;justify-content:space-between;gap:12px}.bh3-day-header label{display:flex;align-items:center;gap:12px;font-weight:650}.bh3-day-header input[type=checkbox]{width:18px;height:18px;margin:0;accent-color:#397253}.bh3-day-slots{display:flex;flex-direction:column;gap:10px;margin-top:12px}.bh3-day-card:not(.is-on) .bh3-day-slots{display:none}.bh3-day-card .bh3-session-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:end;gap:10px;margin:0;background:#f7faf8}.bh3-day-card .bh3-session-row>label{min-width:0}.bh3-day-card .bh3-session-row .bh3-session-term{grid-column:1;grid-row:auto;display:flex;flex-direction:row;align-items:center;white-space:normal}.bh3-session-row-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:end}.bh3-day-card .bh3-session-row .bh3-session-remove{grid-column:auto;justify-self:auto}.bh3-day-card input[type=time],.bh3-day-card select{max-width:100%;width:100%;box-sizing:border-box}@media(max-width:720px){.bh3-day-card .bh3-session-row{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.bh3-day-card .bh3-session-row{grid-template-columns:1fr}.bh3-day-header{align-items:flex-start}.bh3-day-header label{padding-top:5px}.bh3-day-header button{white-space:nowrap}}
        </style>';
        echo '<div class="bh3-wizard-layout"><nav class="bh3-steps" aria-label="Listing steps">';
        $steps = array( 'Basics', 'About & categories', 'Age & pricing', 'Contact & booking', 'Images & accessibility', 'Locations & timetable', 'Review' );
        foreach ( $steps as $i => $label ) { echo '<button type="button" data-step="' . esc_attr( (string) $i ) . '" aria-current="' . ( $i === 0 ? 'step' : 'false' ) . '">' . esc_html( ( $i + 1 ) . '. ' . $label ) . '</button>'; }
        echo '</nav><form id="bh3-listing-wizard" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_create_activity">';
        wp_nonce_field( 'bh3_create_activity' );
        echo '<input type="hidden" name="activity_id" value="' . esc_attr( (string) $edit_id ) . '">';
        if ( ! is_admin() ) { echo '<input type="hidden" name="bh3_frontend_wizard" value="1">'; }
        echo '<section class="bh3-panel" data-panel="0"><h2>Basics</h2><div class="bh3-fields">';
        self::field( 'title', 'Activity title', 'text', '', 'required maxlength="255"' );
        echo '<label class="bh3-field"><span>Organiser</span><select name="organiser_id"><option value="0">Select organiser (optional)</option>';
        foreach ( $organisers as $org ) { echo '<option value="' . esc_attr( (string) $org['id'] ) . '">' . esc_html( $org['name'] ) . '</option>'; }
        echo '</select></label>';
        self::field( 'slug', 'URL slug (leave blank to generate)', 'text', '', 'maxlength="255"' );
        self::custom_fields_for_step( 'basics', $bh3_custom_definitions, $bh3_custom_values );
        echo '</div></section>';
        echo '<section class="bh3-panel" data-panel="1" hidden><h2>About & categories</h2><div class="bh3-fields">';
        self::field( 'category', 'Primary category', 'text', '', 'maxlength="255"' );
        self::field( 'tags', 'Tags and search terms (comma separated)', 'textarea' );
        self::field( 'description', 'Description', 'textarea', '', 'class="bh3-wide"' );
        self::field( 'accessibility', 'Accessibility information', 'textarea', '', 'class="bh3-wide"' );
        self::field( 'what_to_bring', 'What to bring', 'text', '', 'maxlength="250"' );
        self::field( 'good_to_know', 'Good to know', 'text', '', 'maxlength="250"' );
        self::custom_fields_for_step( 'about', $bh3_custom_definitions, $bh3_custom_values );
        echo '</div></section>';
        echo '<section class="bh3-panel" data-panel="2" hidden><h2>Age & pricing</h2><div class="bh3-fields">';
        self::field( 'age_range', 'Display age range', 'text', '', 'maxlength="255" placeholder="e.g. 0–12 months"' );
        self::field( 'age_min_months', 'Minimum age (months)', 'number', '', 'min="0" max="216"' );
        self::field( 'age_max_months', 'Maximum age (months)', 'number', '', 'min="0" max="216"' );
        self::field( 'price', 'Price (£)', 'number', '', 'min="0" step="0.01"' );
        self::field( 'price_from', 'Price from (£)', 'number', '', 'min="0" step="0.01"' );
        echo '<label class="bh3-field"><span>Pricing type</span><select name="price_type"><option value="per_session">Per session</option><option value="per_family">Per family</option><option value="per_child">Per child</option><option value="free">Free</option><option value="per_block">Per block</option></select></label>';
        self::field( 'block_length_weeks', 'Block length (weeks)', 'number', '', 'min="1" max="104"' );
        echo '<label class="bh3-field"><span>Currency</span><select name="currency"><option value="GBP">GBP (£)</option></select></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="is_free" value="1"> Free activity</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="price_per_family" value="1"> Price per family</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="price_per_session" value="1" checked> Price per session</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="term_time_only" value="1"> Term-time only</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="drop_in_welcome" value="1"> Drop-ins welcome</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="trial_available" value="1"> Trial session available</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="holiday_sessions" value="1"> Holiday sessions</span></label>';
        echo '<label class="bh3-field"><span><input type="checkbox" name="siblings_welcome" value="1"> Siblings welcome</span></label>';
        self::field( 'session_length_minutes', 'Session length (minutes)', 'number', '', 'min="1" max="1440"' );
        self::custom_fields_for_step( 'pricing', $bh3_custom_definitions, $bh3_custom_values );
        echo '</div></section>';
        echo '<section class="bh3-panel" data-panel="3" hidden><h2>Contact & booking</h2><div class="bh3-fields">';
        self::field( 'contact_email', 'Contact email', 'email', '', 'maxlength="255"' );
        self::field( 'contact_phone', 'Contact phone', 'tel', '', 'maxlength="100"' );
        self::field( 'website', 'Website', 'url', '', 'maxlength="1000"' );
        self::field( 'facebook_url', 'Facebook URL', 'url', '', 'maxlength="500"' );
        self::field( 'instagram_url', 'Instagram URL', 'url', '', 'maxlength="500"' );
        self::field( 'booking_url', 'Booking URL', 'url', '', 'maxlength="2048"' );
        echo '<label class="bh3-field"><span><input type="checkbox" name="booking_required" value="1"> Booking required</span></label>';
        self::custom_fields_for_step( 'contact', $bh3_custom_definitions, $bh3_custom_values );
        echo '</div></section>';
        echo '<section class="bh3-panel" data-panel="4" hidden><h2>Images & accessibility</h2><div class="bh3-fields">';
        self::field( 'image_path', 'Main listing image URL', 'url', '', 'maxlength="1000"' );
        self::field( 'gallery_images', 'Additional image URLs (one per line, up to 10)', 'textarea', '', 'class="bh3-wide"' );
        echo '<p class="description bh3-wide">The main image URL is retained for compatibility. The WordPress media attachment field remains available separately in BH3.</p>';
        self::custom_fields_for_step( 'images', $bh3_custom_definitions, $bh3_custom_values );
        echo '</div></section>';
        echo '<section class="bh3-panel" data-panel="5" hidden><h2>Locations & timetable</h2><p>Choose one or more existing venues, then add the weekly sessions for this activity. You can leave the timetable empty and add it later.</p>';
        echo '<div class="bh3-location-grid"><div><h3>Find existing venues</h3><input type="search" id="bh3-venue-search" class="regular-text" placeholder="Search venue name or town"><div id="bh3-venue-results" class="bh3-venue-results">';
        foreach ( $venues as $venue ) {
            $town = (string) ( $venue['town'] ?? '' );
            $search_text = strtolower( $venue['name'] . ' ' . $town );
            echo '<label class="bh3-venue-option" data-search="' . esc_attr( $search_text ) . '"><input type="checkbox" name="venue_ids[]" value="' . esc_attr( (string) $venue['id'] ) . '"> <span><strong>' . esc_html( $venue['name'] ) . '</strong>' . ( $town ? ' <small>— ' . esc_html( $town ) . '</small>' : '' ) . '</span></label>';
        }
        if ( ! $venues ) { echo '<p>No existing venues were found. You can save the draft and add venues from My Venues afterwards.</p>'; }
        echo '</div><p class="description">Only existing venues are selectable here. New venue creation remains in Venues Manager.</p></div>';
        echo '<div><h3>Weekly sessions</h3><p class="description">Switch on a day to add its session times. Add more than one time, or copy a session to another day.</p><div id="bh3-session-rows" class="bh3-day-grid"></div></div></div>';
        self::custom_fields_for_step( 'locations', $bh3_custom_definitions, $bh3_custom_values );
        echo '</section>';
        echo '<section class="bh3-panel" data-panel="6" hidden><h2>Review your listing</h2><div class="bh3-review" id="bh3-review-summary">Review the fields above before saving.</div><p>This creates a draft only. Publishing remains administrator-controlled.</p></section>';
        echo '<div class="bh3-actions"><div><button type="button" class="button" id="bh3-prev" disabled>← Back</button> <button type="button" class="button button-primary" id="bh3-next">Continue →</button></div><button type="submit" class="button button-primary" id="bh3-submit" hidden>Save draft activity</button></div></form></div>';
        // Enqueue outside the shortcode HTML so wptexturize and content filters cannot alter JavaScript operators.
        wp_register_script( 'bh3-activity-wizard', false, array(), '1.0.1', true );
        wp_enqueue_script( 'bh3-activity-wizard' );
        wp_add_inline_script( 'bh3-activity-wizard',
            'const bh3SavedListing=' . wp_json_encode( $existing ?: array() ) . ';' .
            'const bh3SavedVenues=' . wp_json_encode( $existing_venue_ids ) . ';' .
            'const bh3SavedSessions=' . wp_json_encode( $existing_sessions ) . ';',
            'before'
        );
        wp_add_inline_script( 'bh3-activity-wizard', <<<'BH3_WIZARD_SCRIPT'
(function(){const form=document.getElementById("bh3-listing-wizard");if(!form)return;let step=0;const panels=[...form.querySelectorAll("[data-panel]")],nav=[...document.querySelectorAll(".bh3-steps [data-step]")],prev=document.getElementById("bh3-prev"),next=document.getElementById("bh3-next"),save=document.getElementById("bh3-submit"),summary=document.getElementById("bh3-review-summary");
        const missingCustom=n=>{for(const label of panels[n].querySelectorAll('label[data-bh3-required="1"]')){const el=label.querySelector("input,textarea,select");if(!el)continue;const filled=el.type==="checkbox"?el.checked:el.multiple?el.selectedOptions.length>0:String(el.value||"").trim()!=="";if(!filled)return el;}return null;};
        const show=n=>{n=Math.max(0,Math.min(6,n));let missing=null;if(n>step){for(let i=step;i<n;i++){missing=missingCustom(i);if(missing){n=i;break;}}}if(step===5&&n>5){for(const row of sessionRows.querySelectorAll('.bh3-session-row')){if(row.closest('.bh3-day-card:not(.is-on)'))continue;const day=row.querySelector('input[name$="[day]"]'),times=row.querySelectorAll('input[type=time]'),start=times[0],end=times[1];if(!day.value||!start.value||!end.value||end.value<=start.value){alert('Complete each weekly session and make sure its end time is later than its start.');(!day.value?day:!start.value?start:end).focus();return;}}}step=n;panels.forEach((p,i)=>p.hidden=i!==step);nav.forEach((b,i)=>b.setAttribute("aria-current",i===step?"step":"false"));prev.disabled=step===0;if(missing){alert('Please complete the required custom field before continuing.');missing.focus();}next.hidden=step===6;save.hidden=step!==6;if(step===6){const fields=[["Activity", "title"],["Category","category"],["Organiser","organiser_id"],["Age range","age_range"],["Price from","price_from"],["Contact email","contact_email"],["Website","website"]];summary.innerHTML=fields.map(([label,name])=>{const el=form.elements.namedItem(name);return "<p><strong>"+label+":</strong> "+String(el?.selectedOptions?.[0]?.textContent||el?.value||"—").replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]))+"</p>"}).join("");}};
        prev.addEventListener("click",()=>show(step-1));next.addEventListener("click",()=>{if(step===0&&!form.elements.namedItem("title").value.trim()){form.elements.namedItem("title").focus();return;}show(step+1)});nav.forEach(b=>b.addEventListener("click",()=>show(Number(b.dataset.step))));form.addEventListener("submit",e=>{if(!form.elements.namedItem("title").value.trim()){e.preventDefault();show(0);form.elements.namedItem("title").focus();}});
        const venueSearch=document.getElementById("bh3-venue-search"),venueOptions=[...document.querySelectorAll(".bh3-venue-option")],sessionRows=document.getElementById("bh3-session-rows");
        venueSearch?.addEventListener("input",()=>{const q=venueSearch.value.trim().toLowerCase();venueOptions.forEach(row=>{row.hidden=q!==""&&!row.dataset.search.includes(q);});});
        const dayNames=["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
        sessionRows.innerHTML=dayNames.map((name,i)=>'<div class="bh3-day-card" data-day="'+(i+1)+'"><div class="bh3-day-header"><label><input type="checkbox" class="bh3-day-toggle" aria-label="Enable '+name+' sessions"> <strong>'+name+'</strong></label><button type="button" class="button button-small bh3-add-day-time">+ Add time</button></div><div class="bh3-day-slots"></div></div>').join("");
        const dayCard=day=>sessionRows.querySelector('.bh3-day-card[data-day="'+day+'"]');
        const escapeOption=text=>String(text).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
        const venueListHtml='<option value="0">No specific venue</option>'+venueOptions.map(v=>{const cb=v.querySelector("input");return '<option value="'+cb.value+'">'+escapeOption(v.querySelector("strong").textContent)+'</option>';}).join("");
        const syncDay=card=>{const on=card.querySelector(".bh3-day-toggle").checked;card.classList.toggle("is-on",on);card.querySelectorAll(".bh3-day-slots input,.bh3-day-slots select").forEach(el=>el.disabled=!on);};
        let sessionIndex=0;
        const addSessionRow=(day=1,data={})=>{
            day=Math.min(7,Math.max(1,Number(day)||1));
            const card=dayCard(day);if(!card)return null;
            card.querySelector(".bh3-day-toggle").checked=true;
            const i=sessionIndex++,row=document.createElement("div");row.className="bh3-session-row";
            row.innerHTML='<input type="hidden" name="sessions['+i+'][day]" value="'+day+'">'+
                '<label>Start<input type="time" name="sessions['+i+'][start]" aria-label="'+dayNames[day-1]+' session start"></label>'+
                '<label>End<input type="time" name="sessions['+i+'][end]" aria-label="'+dayNames[day-1]+' session end"></label>'+
                '<label>Venue<select name="sessions['+i+'][venue_id]">'+venueListHtml+'</select></label>'+
                '<label class="bh3-session-term"><input type="checkbox" name="sessions['+i+'][term_time_only]" value="1"> Term time only</label>'+
                '<label>Copy to<select class="bh3-copy-target" aria-label="Copy session to weekday">'+dayNames.map((name,index)=>'<option value="'+(index+1)+'">'+name+'</option>').join("")+'</select></label>'+
                '<div class="bh3-session-row-actions"><button type="button" class="button button-small bh3-session-copy">Copy</button> <button type="button" class="button button-small bh3-session-remove">Remove</button></div>';
            card.querySelector(".bh3-day-slots").appendChild(row);
            row.querySelector('input[name$="[start]"]').value=data.start||"";
            row.querySelector('input[name$="[end]"]').value=data.end||"";
            row.querySelector('select[name$="[venue_id]"]').value=String(data.venue_id||0);
            row.querySelector('input[name$="[term_time_only]"]').checked=Number(data.term_time_only)===1;
            row.querySelector(".bh3-copy-target").value=String(day);
            syncDay(card);
            return row;
        };
        sessionRows.addEventListener("click",e=>{
            const card=e.target.closest(".bh3-day-card");if(!card)return;
            const day=Number(card.dataset.day);
            if(e.target.closest(".bh3-add-day-time")){addSessionRow(day);return;}
            const row=e.target.closest(".bh3-session-row");
            if(!row)return;
            if(e.target.closest(".bh3-session-remove")){
                row.remove();
                if(!card.querySelector(".bh3-session-row")){card.querySelector(".bh3-day-toggle").checked=false;syncDay(card);}
            }
            if(e.target.closest(".bh3-session-copy")){
                const target=Number(row.querySelector(".bh3-copy-target").value);
                addSessionRow(target,{
                    start:row.querySelector('input[name$="[start]"]').value,
                    end:row.querySelector('input[name$="[end]"]').value,
                    venue_id:row.querySelector('select[name$="[venue_id]"]').value,
                    term_time_only:row.querySelector('input[name$="[term_time_only]"]').checked?1:0
                });
            }
        });
        sessionRows.addEventListener("change",e=>{
            if(e.target.matches('select[name$="[venue_id]"]')){
                const venueId=Number(e.target.value);
                if(venueId){const assigned=form.querySelector('input[name="venue_ids[]"][value="'+venueId+'"]');if(assigned)assigned.checked=true;}
                return;
            }
            if(!e.target.matches(".bh3-day-toggle"))return;
            const card=e.target.closest(".bh3-day-card");
            if(e.target.checked&&!card.querySelector(".bh3-session-row"))addSessionRow(Number(card.dataset.day));
            syncDay(card);
        });
        if(bh3SavedListing&&bh3SavedListing.id){for(const [name,value] of Object.entries(bh3SavedListing)){const el=form.elements.namedItem(name);if(!el)continue;if(el instanceof RadioNodeList)continue;if(el.type==="checkbox")el.checked=Number(value)===1;else if(name==="gallery_images"){try{el.value=JSON.parse(value||"[]").join("\\n");}catch(e){el.value=value||"";}}else if(value!==null)el.value=String(value);}for(const id of bh3SavedVenues){const el=form.querySelector('input[name="venue_ids[]"][value="'+Number(id)+'"]');if(el)el.checked=true;}for(const s of bh3SavedSessions){addSessionRow(Number(s.day)||1,s);}}
        show(0);})();
BH3_WIZARD_SCRIPT
        );
        echo '</div>';


    }
    private static function checkbox( $key ) { return ! empty( $_POST[$key] ) ? 1 : 0; }
    private static function text_value( $key, $max = 0 ) {
        $value = sanitize_text_field( wp_unslash( $_POST[$key] ?? '' ) );
        return $max ? mb_substr( $value, 0, $max ) : $value;
    }
    private static function url_value( $key, $max ) {
        $raw = trim( (string) wp_unslash( $_POST[$key] ?? '' ) );
        if ( $raw === '' ) { return null; }
        $url = esc_url_raw( $raw, array( 'http', 'https' ) );
        if ( ! $url || strlen( $raw ) > $max ) { wp_die( 'Invalid URL in ' . esc_html( $key ) . '.', '', array( 'response' => 400 ) ); }
        return $url;
    }
    private static function number_value( $key, $min, $max, $decimals = false ) {
        $raw = trim( (string) wp_unslash( $_POST[$key] ?? '' ) );
        if ( $raw === '' ) { return null; }
        if ( ! is_numeric( $raw ) || (float) $raw < $min || (float) $raw > $max || ( ! $decimals && (float) (int) $raw !== (float) $raw ) ) {
            wp_die( 'Invalid value for ' . esc_html( $key ) . '.', '', array( 'response' => 400 ) );
        }
        return $decimals ? round( (float) $raw, 2 ) : (int) $raw;
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_create_activity' );
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { wp_die( 'Activities table is missing.', '', array( 'response' => 409 ) ); }
        $title = self::text_value( 'title', 255 );
        if ( $title === '' ) { wp_die( 'Enter an activity title.', '', array( 'response' => 400 ) ); }
        $edit_id = absint( $_POST['activity_id'] ?? 0 );
        if ( $edit_id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id=%d AND status='draft' LIMIT 1", $edit_id ) ) ) {
            wp_die( 'Draft activity not found or no longer editable.', '', array( 'response' => 409 ) );
        }
        $organiser_id = absint( $_POST['organiser_id'] ?? 0 );
        if ( $organiser_id ) {
            $org_table = $wpdb->prefix . 'organisers';
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$org_table}` WHERE id=%d", $organiser_id ) ) ) { wp_die( 'Selected organiser does not exist.', '', array( 'response' => 400 ) ); }
        }
        $min = self::number_value( 'age_min_months', 0, 216 );
        $max = self::number_value( 'age_max_months', 0, 216 );
        if ( $min !== null && $max !== null && $min > $max ) { wp_die( 'Minimum age cannot exceed maximum age.', '', array( 'response' => 400 ) ); }
        $price = self::number_value( 'price', 0, 99999999.99, true );
        $price_from = self::number_value( 'price_from', 0, 99999999.99, true );
        $price_type = self::text_value( 'price_type', 40 );
        if ( ! in_array( $price_type, array( 'per_session', 'per_family', 'per_child', 'free', 'per_block' ), true ) ) { $price_type = 'per_session'; }
        $free = self::checkbox( 'is_free' ) || $price_type === 'free';
        $slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
        if ( $slug === '' ) { $slug = sanitize_title( $title ); }
        $base = substr( $slug ?: 'activity', 0, 220 ); $slug = $base;
        for ( $suffix = 2; $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug=%s AND id<>%d LIMIT 1", $slug, $edit_id ) ); ++$suffix ) {
            $slug = substr( $base, 0, 220 ) . '-' . $suffix;
            if ( $suffix > 10000 ) { wp_die( 'Unable to allocate a unique slug.', '', array( 'response' => 409 ) ); }
        }
        $accessibility = trim( (string) wp_unslash( $_POST['accessibility'] ?? '' ) );
        $gallery = array_slice( array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['gallery_images'] ?? '' ) ) ) ) ), 0, 10 );
        foreach ( $gallery as $image_url ) {
            if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) || ! in_array( strtolower( (string) wp_parse_url( $image_url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) { wp_die( 'One of the gallery image URLs is invalid.', '', array( 'response' => 400 ) ); }
        }
        $venue_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['venue_ids'] ?? array() ) ) ) ) );
        $sessions_input = isset( $_POST['sessions'] ) && is_array( $_POST['sessions'] ) ? wp_unslash( $_POST['sessions'] ) : array();
        $valid_sessions = array();
        foreach ( $sessions_input as $session ) {
            if ( ! is_array( $session ) ) { continue; }
            $day = filter_var( $session['day'] ?? null, FILTER_VALIDATE_INT );
            $start = sanitize_text_field( $session['start'] ?? '' );
            $end = sanitize_text_field( $session['end'] ?? '' );
            if ( $day === false || $day < 1 || $day > 7 || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end ) || $end <= $start ) {
                wp_die( 'Each weekly session needs a valid day, start time and end time (later than its start).', '', array( 'response' => 400 ) );
            }
            $venue_id = absint( $session['venue_id'] ?? 0 );
            if ( $venue_id && ! in_array( $venue_id, $venue_ids, true ) ) { wp_die( 'A session venue must also be selected in the venue list.', '', array( 'response' => 400 ) ); }
            $valid_sessions[] = array( 'day' => $day, 'start' => $start, 'end' => $end, 'venue_id' => $venue_id, 'term_time_only' => empty( $session['term_time_only'] ) ? 0 : 1 );
        }
        if ( $venue_ids || $valid_sessions ) {
            $venues_table = $wpdb->prefix . 'venues';
            $links_table = $wpdb->prefix . 'activity_venues';
            $sessions_table = $wpdb->prefix . 'sessions';
            foreach ( array( $venues_table, $links_table, $sessions_table ) as $required_table ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $required_table ) ) ) !== $required_table ) {
                    wp_die( 'Venue or timetable tables are not ready. Save the activity without locations, then add them from My Venues and My Timetable.', '', array( 'response' => 409 ) );
                }
            }
            if ( $venue_ids ) {
                $placeholders = implode( ',', array_fill( 0, count( $venue_ids ), '%d' ) );
                $found_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$venues_table}` WHERE id IN ($placeholders)", $venue_ids ) );
                if ( count( array_map( 'intval', $found_ids ) ) !== count( $venue_ids ) ) { wp_die( 'One or more selected venues no longer exist. Refresh and try again.', '', array( 'response' => 409 ) ); }
            }
        }
        $bh3_custom_values = array();
        if ( class_exists( 'BH_Field_Manager_3' ) && BH_Field_Manager_3::ready() ) {
            $submitted = $_POST['bh3_custom_fields'] ?? array();
            $bh3_custom_values = BH_Field_Manager_3::validate_submitted_values(
                'activity', is_array( $submitted ) ? wp_unslash( $submitted ) : $submitted
            );
            if ( is_wp_error( $bh3_custom_values ) ) {
                wp_die( esc_html( $bh3_custom_values->get_error_message() ), '', array( 'response' => 400 ) );
            }
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'organiser_id' => $organiser_id ?: null, 'title' => $title, 'slug' => $slug,
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ), 'status' => 'draft',
            'category' => self::text_value( 'category', 255 ), 'tags' => sanitize_textarea_field( wp_unslash( $_POST['tags'] ?? '' ) ),
            'age_min_months' => $min, 'age_max_months' => $max, 'age_range' => self::text_value( 'age_range', 255 ),
            'price' => $price, 'price_from' => $free ? 0 : $price_from, 'price_type' => $free ? 'free' : $price_type,
            'currency' => 'GBP', 'is_free' => $free ? 1 : 0, 'booking_required' => self::checkbox( 'booking_required' ),
            'booking_url' => self::url_value( 'booking_url', 2048 ), 'image_path' => self::url_value( 'image_path', 1000 ),
            'contact_email' => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
            'contact_phone' => self::text_value( 'contact_phone', 100 ), 'website' => self::url_value( 'website', 1000 ),
            'facebook_url' => self::url_value( 'facebook_url', 500 ), 'instagram_url' => self::url_value( 'instagram_url', 500 ),
            'session_length_minutes' => self::number_value( 'session_length_minutes', 1, 1440 ),
            'term_time_only' => self::checkbox( 'term_time_only' ), 'price_per_family' => self::checkbox( 'price_per_family' ),
            'price_per_session' => self::checkbox( 'price_per_session' ), 'drop_in_welcome' => self::checkbox( 'drop_in_welcome' ),
            'trial_available' => self::checkbox( 'trial_available' ), 'holiday_sessions' => self::checkbox( 'holiday_sessions' ),
            'siblings_welcome' => self::checkbox( 'siblings_welcome' ), 'what_to_bring' => self::text_value( 'what_to_bring', 250 ),
            'good_to_know' => self::text_value( 'good_to_know', 250 ), 'gallery_images' => wp_json_encode( $gallery ),
            'block_length_weeks' => self::number_value( 'block_length_weeks', 1, 104 ),
            'accessibility' => $accessibility, 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
        );
        $wpdb->query( 'START TRANSACTION' );
        if ( $edit_id ) {
            unset( $data['created_at'], $data['created_by'] );
            $updated = $wpdb->update( $table, $data, array( 'id' => $edit_id, 'status' => 'draft' ) );
            if ( $updated === false ) { $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to update activity.', '', array( 'response' => 500 ) ); }
            $activity_id = $edit_id;
            $deleted_links = $wpdb->delete( $wpdb->prefix . 'activity_venues', array( 'activity_id' => $activity_id ), array( '%d' ) );
            $deleted_sessions = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->prefix}sessions` WHERE activity_id=%d AND frequency='weekly' AND status='scheduled'", $activity_id ) );
            if ( $deleted_links === false || $deleted_sessions === false ) { $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to update related records.', '', array( 'response' => 500 ) ); }
        } else {
            if ( $wpdb->insert( $table, $data ) === false ) { $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to save activity: ' . esc_html( $wpdb->last_error ), '', array( 'response' => 500 ) ); }
            $activity_id = (int) $wpdb->insert_id;
        }
        foreach ( $venue_ids as $venue_id ) {
            if ( false === $wpdb->insert( $wpdb->prefix . 'activity_venues', array( 'activity_id' => $activity_id, 'venue_id' => $venue_id ), array( '%d', '%d' ) ) ) {
                $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to save venue assignments: ' . esc_html( $wpdb->last_error ), '', array( 'response' => 500 ) );
            }
        }
        foreach ( $valid_sessions as $session ) {
            $session_data = array(
                'activity_id' => $activity_id, 'venue_id' => $session['venue_id'] ?: null,
                'day_of_week' => $session['day'], 'start_time' => $session['start'] . ':00',
                'end_time' => $session['end'] . ':00', 'frequency' => 'weekly',
                'term_time_only' => $session['term_time_only'], 'status' => 'scheduled',
                'created_at' => $now, 'updated_at' => $now,
            );
            if ( false === $wpdb->insert( $wpdb->prefix . 'sessions', $session_data ) ) {
                $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to save weekly sessions: ' . esc_html( $wpdb->last_error ), '', array( 'response' => 500 ) );
            }
        }
        if ( $bh3_custom_values && ! BH_Field_Manager_3::persist_validated_values( 'activity', $activity_id, $bh3_custom_values ) ) {
            $wpdb->query( 'ROLLBACK' );
            wp_die( 'Unable to save custom fields.', '', array( 'response' => 500 ) );
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to complete activity save.', '', array( 'response' => 500 ) ); }
        $frontend = isset( $_POST['bh3_frontend_wizard'] ) && (string) $_POST['bh3_frontend_wizard'] === '1';
        $destination = $frontend
            ? add_query_arg( array( 'created' => $activity_id, 'edited' => $edit_id ? 1 : 0 ), home_url( '/leader/new-activity/' ) )
            : add_query_arg( array( 'page' => 'bh3-add-activity', 'created' => $activity_id, 'edited' => $edit_id ? 1 : 0 ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $destination );
        exit;
    }
}
