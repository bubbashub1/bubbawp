<?php
/** Staging-only custom field groups and definitions. No runtime schema writes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Field_Manager_3 {
    const VERSION = '1.0.0';
    public static function enabled() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function tables() {
        global $wpdb;
        return array( 'groups' => $wpdb->prefix . 'bh3_field_groups', 'fields' => $wpdb->prefix . 'bh3_field_definitions', 'values' => $wpdb->prefix . 'bh3_field_values' );
    }
    public static function register() {
        if ( ! self::enabled() ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_field_install', array( __CLASS__, 'install' ) );
        add_action( 'admin_post_bh3_field_group_save', array( __CLASS__, 'save_group' ) );
        add_action( 'admin_post_bh3_field_definition_save', array( __CLASS__, 'save_field' ) );
        add_action( 'admin_post_bh3_field_toggle', array( __CLASS__, 'toggle' ) );
        add_action( 'admin_post_bh3_field_move', array( __CLASS__, 'move' ) );
        add_action( 'admin_post_bh3_field_values_save', array( __CLASS__, 'save_values' ) );
        add_action( 'admin_post_bh3_field_placement_save', array( __CLASS__, 'save_placement' ) );
        add_action( 'admin_post_bh3_field_quick_step', array( __CLASS__, 'save_quick_step' ) );
        add_action( 'admin_post_bh3_field_order_save', array( __CLASS__, 'save_field_order' ) );
    }
    private static function guard( $nonce ) {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( $nonce );
    }
    public static function ready() {
        global $wpdb;
        foreach ( self::tables() as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return false; }
        }
        return true;
    }
    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Field Manager', 'Field Manager', 'manage_options', 'bh3-field-manager', array( __CLASS__, 'render' ) );
    }
    public static function install() {
        self::guard( 'bh3_field_install' );
        if ( sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) !== 'INSTALL' ) { wp_die( 'Type INSTALL to confirm.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $t = self::tables(); $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE {$t['groups']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            group_key varchar(100) NOT NULL,
            targets varchar(100) NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_key (group_key)
        ) $charset;" );
        dbDelta( "CREATE TABLE {$t['fields']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            field_key varchar(100) NOT NULL,
            label varchar(190) NOT NULL,
            field_type varchar(30) NOT NULL,
            options_json longtext NULL,
            is_required tinyint(1) NOT NULL DEFAULT 0,
            sort_order int NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY group_field (group_id,field_key),
            KEY group_id (group_id)
        ) $charset;" );
        dbDelta( "CREATE TABLE {$t['values']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_type varchar(20) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            field_id bigint(20) unsigned NOT NULL,
            value_json longtext NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_field (object_type,object_id,field_id),
            KEY field_id (field_id)
        ) $charset;" );
        if ( ! self::ready() ) { wp_die( 'Field Manager table verification failed.', '', array( 'response' => 500 ) ); }
        update_option( 'bh3_field_manager_schema_version', self::VERSION, false );
        self::redirect( 'installed' );
    }
    private static function targets( $input ) {
        $allowed = array( 'activity', 'venue', 'session' );
        $selected = array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) $input ) ) );
        return implode( ',', $selected );
    }
    public static function save_group() {
        self::guard( 'bh3_field_group_save' );
        if ( ! self::ready() ) { wp_die( 'Install Field Manager tables first.', '', array( 'response' => 409 ) ); }
        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $key = sanitize_key( wp_unslash( $_POST['group_key'] ?? '' ) );
        $targets = self::targets( wp_unslash( $_POST['targets'] ?? array() ) );
        if ( ! $name || ! $key || strlen( $name ) > 190 || strlen( $key ) > 100 || ! $targets ) { wp_die( 'Group name, key and at least one assignment are required.', '', array( 'response' => 400 ) ); }
        global $wpdb; $t = self::tables();
        $id = absint( $_POST['group_id'] ?? 0 );
        $payload = array( 'name' => $name, 'group_key' => $key, 'targets' => $targets );
        $ok = $id ? $wpdb->update( $t['groups'], $payload, array( 'id' => $id ) ) : $wpdb->insert( $t['groups'], $payload + array( 'created_at' => current_time( 'mysql', true ) ) );
        if ( $ok === false ) { wp_die( 'Could not save group. Its key may already exist.', '', array( 'response' => 409 ) ); }
        self::redirect( 'group_saved' );
    }
    public static function save_field() {
        self::guard( 'bh3_field_definition_save' );
        if ( ! self::ready() ) { wp_die( 'Install Field Manager tables first.', '', array( 'response' => 409 ) ); }
        global $wpdb; $t = self::tables();
        $group_id = absint( $_POST['group_id'] ?? 0 );
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['groups']}` WHERE id=%d", $group_id ) ) ) { wp_die( 'Invalid field group.', '', array( 'response' => 400 ) ); }
        $key = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
        $type = sanitize_key( wp_unslash( $_POST['field_type'] ?? '' ) );
        $types = array( 'text', 'textarea', 'number', 'checkbox', 'select', 'multiselect', 'date', 'time', 'url', 'email' );
        if ( ! $key || ! $label || strlen( $key ) > 100 || strlen( $label ) > 190 || ! in_array( $type, $types, true ) ) { wp_die( 'Invalid field definition.', '', array( 'response' => 400 ) ); }
        $options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\n|\r/', (string) wp_unslash( $_POST['options'] ?? '' ) ) ) ) ) );
        if ( count( $options ) > 100 || array_filter( $options, function( $v ) { return strlen( $v ) > 190; } ) ) { wp_die( 'Too many or overly long options.', '', array( 'response' => 400 ) ); }
        if ( ! in_array( $type, array( 'select', 'multiselect' ), true ) ) { $options = array(); }
        if ( in_array( $type, array( 'select', 'multiselect' ), true ) && ! $options ) { wp_die( 'Selection fields require options.', '', array( 'response' => 400 ) ); }
        $id = absint( $_POST['field_id'] ?? 0 );
        $payload = array(
            'group_id' => $group_id, 'field_key' => $key, 'label' => $label, 'field_type' => $type,
            'options_json' => wp_json_encode( $options ), 'is_required' => empty( $_POST['is_required'] ) ? 0 : 1
        );
        $ok = $id ? $wpdb->update( $t['fields'], $payload, array( 'id' => $id ) ) : $wpdb->insert( $t['fields'], $payload );
        if ( $ok === false ) { wp_die( 'Could not save field. Its key may already exist in this group.', '', array( 'response' => 409 ) ); }
        self::redirect( 'field_saved' );
    }
    public static function toggle() {
        self::guard( 'bh3_field_toggle' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        global $wpdb; $t = self::tables();
        $kind = sanitize_key( $_POST['kind'] ?? '' );
        $id = absint( $_POST['id'] ?? 0 );
        $table = $kind === 'group' ? $t['groups'] : ( $kind === 'field' ? $t['fields'] : '' );
        if ( ! $table || ! $id ) { wp_die( 'Invalid record.', '', array( 'response' => 400 ) ); }
        $current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM `{$table}` WHERE id=%d", $id ) );
        if ( $current === null || false === $wpdb->update( $table, array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) ) ) {
            wp_die( 'Could not change field status.', '', array( 'response' => 409 ) );
        }
        self::redirect( 'status_updated' );
    }
    public static function move() {
        self::guard( 'bh3_field_move' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        global $wpdb; $t = self::tables();
        $id = absint( $_POST['id'] ?? 0 );
        $direction = sanitize_key( $_POST['direction'] ?? '' );
        if ( ! $id || ! in_array( $direction, array( 'up', 'down' ), true ) ) { wp_die( 'Invalid order request.', '', array( 'response' => 400 ) ); }
        $field = $wpdb->get_row( $wpdb->prepare( "SELECT id,group_id FROM `{$t['fields']}` WHERE id=%d", $id ), ARRAY_A );
        if ( ! $field ) { wp_die( 'Field not found.', '', array( 'response' => 404 ) ); }
        $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t['fields']}` WHERE group_id=%d ORDER BY sort_order,id", $field['group_id'] ) ) );
        $index = array_search( $id, $ids, true );
        $other = $direction === 'up' ? $index - 1 : $index + 1;
        if ( $index === false || ! isset( $ids[ $other ] ) ) { self::redirect( 'order_unchanged' ); }
        $tmp = $ids[ $other ]; $ids[ $other ] = $id; $ids[ $index ] = $tmp;
        $wpdb->query( 'START TRANSACTION' );
        foreach ( $ids as $position => $field_id ) {
            if ( false === $wpdb->update( $t['fields'], array( 'sort_order' => $position + 1 ), array( 'id' => $field_id, 'group_id' => $field['group_id'] ), array( '%d' ), array( '%d', '%d' ) ) ) {
                $wpdb->query( 'ROLLBACK' ); wp_die( 'Unable to reorder fields.', '', array( 'response' => 500 ) );
            }
        }
        $wpdb->query( 'COMMIT' );
        self::redirect( 'order_updated' );
    }
    /** The value editor is admin-only until ownership rules are shared with the front-end wizard. */
    private static function object_table( $type ) {
        if ( ! in_array( $type, array( 'activity', 'venue', 'session' ), true ) ) { return ''; }
        global $wpdb;
        return $wpdb->prefix . array( 'activity' => 'activities', 'venue' => 'venues', 'session' => 'sessions' )[ $type ];
    }
    private static function object_exists( $type, $id ) {
        if ( ! $id || ! ( $table = self::object_table( $type ) ) ) { return false; }
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id=%d LIMIT 1", $id ) );
    }
    private static function normalized_value( $field, $raw ) {
        $type = $field['field_type'];
        $options = json_decode( $field['options_json'] ?: '[]', true );
        if ( ! is_array( $options ) ) { $options = array(); }
        if ( $type === 'checkbox' ) { return empty( $raw ) ? false : true; }
        if ( $type === 'multiselect' ) {
            if ( ! is_array( $raw ) || count( $raw ) > 100 ) { return new WP_Error( 'invalid_value', 'Invalid multi-select value.' ); }
            $values = array_values( array_unique( array_map( 'sanitize_text_field', $raw ) ) );
            return count( array_diff( $values, $options ) ) ? new WP_Error( 'invalid_option', 'Invalid multi-select option.' ) : $values;
        }
        if ( is_array( $raw ) || is_object( $raw ) ) { return new WP_Error( 'invalid_value', 'Invalid field value.' ); }
        $value = trim( (string) $raw );
        if ( strlen( $value ) > 10000 ) { return new WP_Error( 'too_long', 'Field value is too long.' ); }
        switch ( $type ) {
            case 'number':
                if ( $value !== '' && ! is_numeric( $value ) ) { return new WP_Error( 'invalid_number', 'Invalid number.' ); }
                if ( $value === '' ) { return ''; }
                $number = (float) $value;
                return is_finite( $number ) ? $number : new WP_Error( 'invalid_number', 'Number is out of range.' );
            case 'select':
                if ( $value !== '' && ! in_array( $value, $options, true ) ) { return new WP_Error( 'invalid_option', 'Invalid selection.' ); }
                return $value;
            case 'email':
                if ( $value !== '' && ! is_email( $value ) ) { return new WP_Error( 'invalid_email', 'Invalid email.' ); }
                return sanitize_email( $value );
            case 'url':
                if ( $value !== '' && ! wp_http_validate_url( $value ) ) { return new WP_Error( 'invalid_url', 'Invalid URL.' ); }
                return esc_url_raw( $value );
            case 'date':
                $date = $value ? DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : null;
                if ( $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) { return new WP_Error( 'invalid_date', 'Invalid date.' ); }
                return $value;
            case 'time':
                if ( $value !== '' && ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) { return new WP_Error( 'invalid_time', 'Invalid time.' ); }
                return $value;
            case 'textarea': return sanitize_textarea_field( $value );
            default: return sanitize_text_field( $value );
        }
    }
    public static function save_values() {
        self::guard( 'bh3_field_values_save' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        $type = sanitize_key( $_POST['object_type'] ?? '' );
        $id = absint( $_POST['object_id'] ?? 0 );
        if ( ! self::object_exists( $type, $id ) ) { wp_die( 'Record not found.', '', array( 'response' => 404 ) ); }
        $submitted = $_POST['custom_fields'] ?? array();
        $updates = self::validate_submitted_values( $type, is_array( $submitted ) ? wp_unslash( $submitted ) : $submitted );
        if ( is_wp_error( $updates ) ) {
            wp_die( esc_html( $updates->get_error_message() ), '', array( 'response' => 400 ) );
        }
        global $wpdb;
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            wp_die( 'Could not begin the custom field save.', '', array( 'response' => 500 ) );
        }
        if ( ! self::persist_validated_values( $type, $id, $updates ) ) {
            $wpdb->query( 'ROLLBACK' );
            wp_die( 'Could not save field values.', '', array( 'response' => 500 ) );
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            wp_die( 'Could not complete custom field save.', '', array( 'response' => 500 ) );
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-field-manager', 'object_type' => $type, 'object_id' => $id, 'notice' => 'values_saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    private static function render_values_editor() {
        $type = sanitize_key( $_GET['object_type'] ?? '' );
        $id = absint( $_GET['object_id'] ?? 0 );
        echo '<section class="bh3-fm-card"><h2>Edit record values</h2><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="bh3-field-manager"><label>Record type <select name="object_type">';
        foreach ( array( 'activity' => 'Activity', 'venue' => 'Venue', 'session' => 'Session' ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label> <label>Record ID <input type="number" name="object_id" min="1" value="' . esc_attr( $id ?: '' ) . '" required></label> <button class="button">Load fields</button></form>';
        if ( $id && self::object_exists( $type, $id ) ) {
            global $wpdb; $t = self::tables();
            $definitions = self::definitions_for( $type );
            $stored = $wpdb->get_results( $wpdb->prepare( "SELECT field_id,value_json FROM `{$t['values']}` WHERE object_type=%s AND object_id=%d", $type, $id ), ARRAY_A );
            $values = array();
            foreach ( $stored as $item ) { $values[ (int) $item['field_id'] ] = json_decode( $item['value_json'], true ); }
            echo '<h3>' . esc_html( ucfirst( $type ) . ' #' . $id ) . '</h3>';
            if ( ! $definitions ) { echo '<p>No active custom fields assigned to this record type.</p>'; }
            else {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_values_save"><input type="hidden" name="object_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="object_id" value="' . esc_attr( $id ) . '">';
                wp_nonce_field( 'bh3_field_values_save' );
                foreach ( $definitions as $field ) {
                    $fid = (int) $field['field_id']; $kind = $field['field_type']; $value = $values[ $fid ] ?? '';
                    $name = 'custom_fields[' . $fid . ']'; $options = json_decode( $field['options_json'] ?: '[]', true ) ?: array();
                    echo '<p><label><strong>' . esc_html( $field['group_name'] . ' / ' . $field['label'] ) . '</strong>' . ( $field['is_required'] ? ' *' : '' ) . '<br>';
                    if ( $kind === 'textarea' ) { echo '<textarea name="' . esc_attr( $name ) . '" rows="3" cols="55">' . esc_textarea( (string) $value ) . '</textarea>'; }
                    elseif ( $kind === 'checkbox' ) { echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . '>'; }
                    elseif ( in_array( $kind, array( 'select', 'multiselect' ), true ) ) {
                        echo '<select name="' . esc_attr( $name . ( $kind === 'multiselect' ? '[]' : '' ) ) . '" ' . ( $kind === 'multiselect' ? 'multiple size="4"' : '' ) . '>';
                        if ( $kind === 'select' ) { echo '<option value="">Choose…</option>'; }
                        foreach ( $options as $option ) {
                            $selected = $kind === 'multiselect' ? in_array( $option, (array) $value, true ) : $option === $value;
                            echo '<option value="' . esc_attr( $option ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $option ) . '</option>';
                        }
                        echo '</select>';
                    } else {
                        $input_type = in_array( $kind, array( 'number', 'date', 'time', 'url', 'email' ), true ) ? $kind : 'text';
                        echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
                    }
                    echo '</label></p>';
                }
                echo '<button class="button button-primary">Save custom values</button></form>';
            }
        } elseif ( $id ) { echo '<p>Record not found.</p>'; }
        echo '</section>';
    }
    /** Placement metadata is independent of the existing custom field schema. */
    public static function placements() {
        $saved = get_option( 'bh3_field_placements', array() );
        return is_array( $saved ) ? $saved : array();
    }
    public static function save_placement() {
        self::guard( 'bh3_field_placement_save' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        $id = absint( $_POST['field_id'] ?? 0 );
        $step = sanitize_key( wp_unslash( $_POST['wizard_step'] ?? '' ) );
        $allowed = array( 'basics', 'about', 'pricing', 'contact', 'images', 'locations' );
        if ( ! $id || ! in_array( $step, $allowed, true ) ) { wp_die( 'Invalid field placement.', '', array( 'response' => 400 ) ); }
        global $wpdb; $t = self::tables();
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['fields']}` WHERE id=%d", $id ) ) ) { wp_die( 'Field not found.', '', array( 'response' => 404 ) ); }
        $placements = self::placements();
        $placements[ $id ] = array(
            'wizard_step' => $step,
            'show_on_detail' => ! empty( $_POST['show_on_detail'] ),
            'show_in_search' => ! empty( $_POST['show_in_search'] ),
        );
        update_option( 'bh3_field_placements', $placements, false );
        self::redirect( 'placement_saved' );
    }
    public static function save_quick_step() {
        self::guard( 'bh3_field_quick_step' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        $id = absint( $_POST['field_id'] ?? 0 );
        $step = sanitize_key( wp_unslash( $_POST['wizard_step'] ?? '' ) );
        if ( ! $id || ! in_array( $step, array( 'basics', 'about', 'pricing', 'contact', 'images', 'locations' ), true ) ) { wp_die( 'Invalid field placement.', '', array( 'response' => 400 ) ); }
        global $wpdb; $tables = self::tables();
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$tables['fields']}` WHERE id=%d", $id ) ) ) { wp_die( 'Field not found.', '', array( 'response' => 404 ) ); }
        $placements = self::placements();
        $placements[ $id ] = array_merge( $placements[ $id ] ?? array(), array( 'wizard_step' => $step ) );
        update_option( 'bh3_field_placements', $placements, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-field-manager', 'notice' => 'placement_saved' ), admin_url( 'admin.php' ) ) . '#bh3-form-builder' );
        exit;
    }
    public static function definitions_with_placement( $target ) {
        $definitions = self::definitions_for( $target );
        $placements = self::placements();
        foreach ( $definitions as &$field ) {
            $field['placement'] = $placements[ (int) $field['field_id'] ] ?? array( 'wizard_step' => 'about', 'show_on_detail' => false, 'show_in_search' => false );
        }
        unset( $field );
        return $definitions;
    }
    /** Shared validation for the admin record editor and the BH3 listing wizard. */
    public static function validate_submitted_values( $target, $submitted ) {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) || ! self::ready() ) {
            return new WP_Error( 'unavailable', 'Custom fields are unavailable.' );
        }
        if ( ! is_array( $submitted ) ) {
            return new WP_Error( 'invalid_input', 'Invalid custom field values.' );
        }
        $result = array();
        foreach ( self::definitions_for( $target ) as $field ) {
            $id = (int) $field['field_id'];
            $raw = $submitted[ $id ] ?? ( $field['field_type'] === 'multiselect' ? array() : '' );
            $value = self::normalized_value( $field, $raw );
            if ( is_wp_error( $value ) ) {
                return new WP_Error( $value->get_error_code(), $field['label'] . ': ' . $value->get_error_message() );
            }
            if ( $field['is_required'] && ( $value === '' || $value === array() || $value === false ) ) {
                return new WP_Error( 'required_field', $field['label'] . ' is required.' );
            }
            $result[ $id ] = wp_json_encode( $value );
        }
        return $result;
    }
    /** Call inside an existing transaction when saving a draft activity. */
    public static function persist_validated_values( $target, $object_id, $values ) {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) || ! self::ready()
            || ! self::object_exists( $target, $object_id ) || ! is_array( $values ) ) {
            return false;
        }
        global $wpdb; $t = self::tables();
        foreach ( $values as $field_id => $json ) {
            if ( ! is_int( $field_id ) || ! is_string( $json ) ) { return false; }
            $ok = $wpdb->replace( $t['values'], array(
                'object_type' => $target,
                'object_id' => $object_id,
                'field_id' => $field_id,
                'value_json' => $json,
                'updated_at' => current_time( 'mysql', true ),
            ) );
            if ( false === $ok ) { return false; }
        }
        return true;
    }
    public static function values_for( $target, $object_id ) {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' )
            || ! self::ready() || ! self::object_exists( $target, $object_id ) ) { return array(); }
        global $wpdb; $t = self::tables();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_id,value_json FROM `{$t['values']}` WHERE object_type=%s AND object_id=%d",
            $target, $object_id
        ), ARRAY_A );
        $values = array();
        foreach ( $rows as $row ) { $values[ (int) $row['field_id'] ] = json_decode( $row['value_json'], true ); }
        return $values;
    }
    /** Save a complete, tamper-checked ordering for one field group. */
    public static function save_field_order() {
        self::guard( 'bh3_field_order_save' );
        if ( ! self::ready() ) { wp_die( 'Field Manager not installed.', '', array( 'response' => 409 ) ); }
        $group_id = absint( $_POST['group_id'] ?? 0 );
        $input = trim( (string) wp_unslash( $_POST['order'] ?? '' ) );
        if ( ! $group_id || ! preg_match( '/^\d+(,\d+)*$/', $input ) ) {
            wp_die( 'Invalid field order.', '', array( 'response' => 400 ) );
        }
        $ids = array_map( 'absint', explode( ',', $input ) );
        if ( count( $ids ) > 1000 || count( array_unique( $ids ) ) !== count( $ids ) ) {
            wp_die( 'Duplicate or excessive fields in the order.', '', array( 'response' => 400 ) );
        }
        global $wpdb; $t = self::tables();
        $actual = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM `{$t['fields']}` WHERE group_id=%d", $group_id
        ) ) );
        $sorted_ids = $ids; $sorted_actual = $actual;
        sort( $sorted_ids, SORT_NUMERIC ); sort( $sorted_actual, SORT_NUMERIC );
        if ( ! $actual || $sorted_ids !== $sorted_actual ) {
            wp_die( 'The field list changed; reload before reordering.', '', array( 'response' => 409 ) );
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            wp_die( 'Could not begin field reordering.', '', array( 'response' => 500 ) );
        }
        foreach ( $ids as $position => $id ) {
            if ( false === $wpdb->update(
                $t['fields'], array( 'sort_order' => $position + 1 ),
                array( 'id' => $id, 'group_id' => $group_id ),
                array( '%d' ), array( '%d', '%d' )
            ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_die( 'Unable to save field order.', '', array( 'response' => 500 ) );
            }
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            wp_die( 'Unable to finish field reorder.', '', array( 'response' => 500 ) );
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-field-manager', 'notice' => 'field_order_saved' ), admin_url( 'admin.php' ) ) . '#bh3-form-builder' );
        exit;
    }
    private static function render_builder( $groups, $fields ) {
        if ( ! $groups ) { return; }
        $placements = self::placements();
        echo '<section class="bh3-fm-card" id="bh3-form-builder"><h2>Visual form builder</h2><p class="description">Use Add field or Edit to configure fields, choose a wizard section and press Move to change placement. Drag the card to reorder within its group, or use ↑ and ↓ for touch and keyboard access. Changes are saved immediately.</p><div class="bh3-builder-grid">';
        foreach ( $groups as $group ) {
            $add_url = add_query_arg( array( 'page' => 'bh3-field-manager', 'add_to_group' => absint( $group['id'] ) ), admin_url( 'admin.php' ) ) . '#bh3-field-editor';
            echo '<div class="bh3-builder-group"><h3>' . esc_html( $group['name'] ) . '</h3><p class="description">' . esc_html( str_replace( ',', ', ', $group['targets'] ) ) . '</p><p><a class="button button-secondary" href="' . esc_url( $add_url ) . '">+ Add field to this group</a></p>';
            echo '<div class="bh3-builder-list" data-group-id="' . esc_attr( (string) $group['id'] ) . '">';
            $count = 0;
            foreach ( $fields as $field ) {
                if ( (int) $field['group_id'] !== (int) $group['id'] ) { continue; }
                ++$count;
                $placement = $placements[ (int) $field['id'] ]['wizard_step'] ?? 'about';
                $edit_url = add_query_arg( array( 'page' => 'bh3-field-manager', 'edit_field' => absint( $field['id'] ) ), admin_url( 'admin.php' ) ) . '#bh3-field-editor';
                echo '<div class="bh3-builder-item' . ( $field['is_active'] ? '' : ' bh3-fm-disabled' ) . '" draggable="true" tabindex="0" data-field-id="' . esc_attr( (string) $field['id'] ) . '"><span class="bh3-drag-grip" aria-hidden="true">⋮⋮</span><span class="bh3-builder-item-text"><strong>' . esc_html( $field['label'] ) . '</strong><small>' . esc_html( $field['field_type'] . ' · ' . ucfirst( $placement ) ) . '</small></span><span class="bh3-item-controls"><form class="bh3-quick-step-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_quick_step"><input type="hidden" name="field_id" value="' . esc_attr( (string) $field['id'] ) . '">' . wp_nonce_field( 'bh3_field_quick_step', '_wpnonce', true, false ) . '<select name="wizard_step" aria-label="Wizard section for ' . esc_attr( $field['label'] ) . '">'; foreach ( array( 'basics' => 'Basics', 'about' => 'About', 'pricing' => 'Pricing', 'contact' => 'Contact', 'images' => 'Images', 'locations' => 'Locations' ) as $step_key => $step_label ) { echo '<option value="' . esc_attr( $step_key ) . '" ' . selected( $placement, $step_key, false ) . '>' . esc_html( $step_label ) . '</option>'; } echo '</select><button class="button button-small" type="submit">Move</button></form><button type="button" class="button button-small" data-move-direction="-1" aria-label="Move field up">↑</button><button type="button" class="button button-small" data-move-direction="1" aria-label="Move field down">↓</button><a href="' . esc_url( $edit_url ) . '">Edit</a></span></div>';
            }
            if ( ! $count ) { echo '<p class="bh3-builder-empty">Add a field to start building this group.</p>'; }
            echo '</div>';
            if ( $count ) {
                echo '<form class="bh3-builder-order-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_order_save"><input type="hidden" name="group_id" value="' . esc_attr( (string) $group['id'] ) . '"><input type="hidden" name="order" value="">';
                wp_nonce_field( 'bh3_field_order_save' );
                echo '</form>';
            }
            echo '</div>';
        }
        echo '</div></section>';
        echo '<script>(function(){document.querySelectorAll(".bh3-builder-list").forEach(list=>{let moving=null;const saveOrder=()=>{const form=list.parentElement.querySelector(".bh3-builder-order-form");if(!form)return;form.elements.namedItem("order").value=[...list.querySelectorAll("[data-field-id]")].map(item=>item.dataset.fieldId).join(",");form.submit()};list.addEventListener("click",e=>{const btn=e.target.closest("[data-move-direction]");if(!btn)return;const item=btn.closest(".bh3-builder-item");if(!item||!list.contains(item))return;const dir=Number(btn.dataset.moveDirection);if(dir<0&&item.previousElementSibling){list.insertBefore(item,item.previousElementSibling);saveOrder()}else if(dir>0&&item.nextElementSibling){list.insertBefore(item.nextElementSibling,item);saveOrder()}});list.addEventListener("dragstart",e=>{const item=e.target.closest(".bh3-builder-item");if(!item||!list.contains(item)||e.target.closest("input,select,button,a,textarea"))return;moving=item;e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain",item.dataset.fieldId);item.classList.add("is-dragging")});list.addEventListener("dragover",e=>{if(!moving)return;e.preventDefault();const target=e.target.closest(".bh3-builder-item");if(!target||target===moving)return;const midpoint=target.getBoundingClientRect().top+target.offsetHeight/2;list.insertBefore(moving,e.clientY<midpoint?target:target.nextSibling)});list.addEventListener("drop",e=>{if(!moving)return;e.preventDefault();const form=list.parentElement.querySelector(".bh3-builder-order-form");if(form){form.elements.namedItem("order").value=[...list.querySelectorAll("[data-field-id]")].map(item=>item.dataset.fieldId).join(",");form.submit()}});list.addEventListener("dragend",()=>{if(moving)moving.classList.remove("is-dragging");moving=null})})})();</script>';
    }
    private static function action_form( $action, $nonce, $values, $label ) {
        $html = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $html .= '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
        foreach ( $values as $key => $value ) {
            $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
        }
        $html .= wp_nonce_field( $nonce, '_wpnonce', true, false );
        return $html . '<button type="submit" class="button button-small">' . esc_html( $label ) . '</button></form>';
    }
    private static function redirect( $notice ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-field-manager', 'notice' => $notice ), admin_url( 'admin.php' ) ) );
        exit;
    }
    /** Reusable definition API for future admin and frontend form renderers. */
    public static function definitions_for( $target ) {
        if ( ! self::enabled() || ! self::ready() || ! in_array( $target, array( 'activity', 'venue', 'session' ), true ) ) { return array(); }
        global $wpdb; $t = self::tables();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT g.id AS group_id,g.name AS group_name,g.group_key,f.id AS field_id,f.field_key,f.label,f.field_type,f.options_json,f.is_required
             FROM `{$t['groups']}` g JOIN `{$t['fields']}` f ON f.group_id=g.id
             WHERE g.is_active=1 AND f.is_active=1 AND FIND_IN_SET(%s,g.targets)>0
             ORDER BY g.sort_order,g.id,f.sort_order,f.id", $target
        ), ARRAY_A );
    }
    public static function render() {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap bh3-fm"><style>.bh3-fm{max-width:1200px;color:#203c31}.bh3-fm,.bh3-fm *{box-sizing:border-box}.bh3-fm .bh3-fm-card{min-width:0;overflow-wrap:anywhere}.bh3-fm .bh3-fm-card h2{font-size:21px;line-height:1.3}.bh3-fm .bh3-fm-card input,.bh3-fm .bh3-fm-card select,.bh3-fm .bh3-fm-card textarea{min-height:42px;max-width:100%}.bh3-fm .bh3-fm-card .button{min-height:38px;display:inline-flex;align-items:center;justify-content:center;white-space:normal;text-align:center}.bh3-fm .bh3-fm-card .button-primary{background:#176345;border-color:#176345}.bh3-fm .bh3-fm-card .button-primary:hover{background:#104b35;border-color:#104b35}@media(max-width:782px){.bh3-fm{margin:12px 12px 24px 0}.bh3-fm .bh3-fm-card{padding:16px;margin:14px 0;border-radius:14px}.bh3-fm .bh3-fm-grid{grid-template-columns:1fr;gap:16px}.bh3-fm .bh3-fm-actions{flex-wrap:wrap;gap:10px}.bh3-fm .bh3-fm-card .button{max-width:100%}.bh3-fm .bh3-fm-checks{gap:12px}}@media(max-width:420px){.bh3-fm .bh3-fm-card .button{width:100%}}.bh3-fm .bh3-fm-card{background:#fff;border:1px solid #dbe5de;border-radius:12px;padding:20px;margin:18px 0}.bh3-fm .bh3-fm-card h2{margin-top:0}.bh3-fm .bh3-fm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.bh3-fm .bh3-fm-grid label{display:flex;flex-direction:column;gap:6px;font-weight:600}.bh3-fm .bh3-fm-grid input,.bh3-fm .bh3-fm-grid select{width:100%;max-width:100%;box-sizing:border-box}.bh3-fm .bh3-fm-checks{display:flex;gap:18px;flex-wrap:wrap;margin:16px 0}.bh3-fm .bh3-fm-checks label{display:flex;align-items:center;gap:7px}.bh3-fm .bh3-fm-checks input{margin:0}.bh3-fm .bh3-fm-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.bh3-fm .bh3-fm-actions form{display:inline}.bh3-fm .bh3-fm-muted{color:#66746b}.bh3-fm .bh3-fm-card table{border-radius:8px;overflow:hidden}.bh3-fm .bh3-fm-card td{vertical-align:middle}.bh3-fm .bh3-fm-disabled{opacity:.55}@media(max-width:700px){.bh3-fm .bh3-fm-grid{grid-template-columns:1fr}.bh3-fm .bh3-fm-card{padding:14px;overflow-x:auto}}.bh3-fm .bh3-builder-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.bh3-fm .bh3-builder-group{background:#f8faf9;border:1px solid #e0e9e3;border-radius:10px;padding:14px;min-width:0}.bh3-fm .bh3-builder-group h3{margin:0 0 5px}.bh3-fm .bh3-builder-list{display:flex;flex-direction:column;gap:7px;min-height:30px}.bh3-fm .bh3-field-editor-preview{background:#f7faf8;border:1px solid #d6e5db;border-radius:12px;padding:16px;margin:18px 0;max-width:560px}.bh3-fm .bh3-field-editor-example{margin-top:12px}.bh3-fm .bh3-field-editor-example label{display:block;font-weight:600;margin-bottom:7px}.bh3-fm .bh3-field-editor-example input,.bh3-fm .bh3-field-editor-example select,.bh3-fm .bh3-field-editor-example textarea{width:100%;max-width:100%;border:1px solid #bdcec2;border-radius:7px;background:#fff;padding:8px;min-height:40px}.bh3-fm .bh3-quick-step-form{display:flex;gap:5px;align-items:center;margin:0}.bh3-fm .bh3-quick-step-form select{max-width:120px;min-height:36px}.bh3-fm .bh3-item-controls{display:flex;flex-wrap:wrap;gap:5px;align-items:center;flex-shrink:0}.bh3-fm .bh3-item-controls .button{min-width:36px;min-height:36px;padding:0 8px}.bh3-fm .bh3-builder-item{display:flex;align-items:center;gap:11px;border:1px solid #d2e2d7;border-radius:8px;background:#fff;padding:12px;cursor:grab}.bh3-fm .bh3-builder-item:focus{outline:2px solid #397253}.bh3-fm .bh3-builder-item.is-dragging{opacity:.45}.bh3-fm .bh3-drag-grip{font-size:23px;line-height:1;color:#728777}.bh3-fm .bh3-builder-item-text{flex:1;min-width:0}.bh3-fm .bh3-builder-item-text strong{display:block}.bh3-fm .bh3-builder-item-text small{display:block;color:#65766b;margin-top:3px}.bh3-fm .bh3-builder-empty{padding:12px;background:#fff;border:1px dashed #d2e2d7;border-radius:8px}@media(max-width:700px){.bh3-fm .bh3-builder-grid{grid-template-columns:1fr}}</style><h1>Bubba Hub Field Manager</h1><p>Create reusable field groups and assign them to activities, venues and sessions. Custom fields also appear in the activity wizard and can store values on individual records.</p>'; 
        if ( isset( $_GET['notice'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ) . '</p></div>'; }
        if ( ! self::ready() ) {
            echo '<h2>Install Field Manager tables</h2><p>Back up staging first. Creates three new tables without altering legacy or BH3 activity tables.</p><form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="bh3_field_install">';
            wp_nonce_field( 'bh3_field_install' );
            echo '<label>Type INSTALL <input name="confirm" required></label> <button class="button button-primary">Install Field Manager tables</button></form></div>';
            return;
        }
        global $wpdb; $t = self::tables();
        $groups = $wpdb->get_results( "SELECT * FROM `{$t['groups']}` ORDER BY sort_order,id", ARRAY_A );
        $fields = $wpdb->get_results( "SELECT * FROM `{$t['fields']}` ORDER BY group_id,sort_order,id", ARRAY_A );
        $edit_group_id = absint( $_GET['edit_group'] ?? 0 );
        $edit_field_id = absint( $_GET['edit_field'] ?? 0 );
        $add_to_group = absint( $_GET['add_to_group'] ?? 0 );
        $edit_group = null; $edit_field = null;
        foreach ( $groups as $g ) { if ( (int) $g['id'] === $edit_group_id ) { $edit_group = $g; break; } }
        foreach ( $fields as $f ) { if ( (int) $f['id'] === $edit_field_id ) { $edit_field = $f; break; } }

        echo '<section class="bh3-fm-card"><h2>Field groups</h2><table class="widefat striped"><thead><tr><th>Group</th><th>Key</th><th>Assigned to</th><th>Fields</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $groups as $group ) {
            $count = count( array_filter( $fields, function( $f ) use ( $group ) { return (int) $f['group_id'] === (int) $group['id']; } ) );
            echo '<tr><td>' . esc_html( $group['name'] ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-field-manager', 'edit_group' => absint( $group['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a></td><td><code>' . esc_html( $group['group_key'] ) . '</code></td><td>' . esc_html( str_replace( ',', ', ', $group['targets'] ) ) . '</td><td>' . esc_html( (string) $count ) . '</td><td>' . self::action_form( 'bh3_field_toggle', 'bh3_field_toggle', array( 'kind' => 'group', 'id' => $group['id'] ), $group['is_active'] ? 'Disable' : 'Enable' ) . '</td></tr>';
        }
        if ( ! $groups ) { echo '<tr><td colspan="5">No groups yet.</td></tr>'; }
        echo '</tbody></table></section><section class="bh3-fm-card"><h2>' . ( $edit_group ? 'Edit field group' : 'Add field group' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_group_save">';
        wp_nonce_field( 'bh3_field_group_save' );
        echo '<input type="hidden" name="group_id" value="' . esc_attr( (string) ( $edit_group['id'] ?? 0 ) ) . '">';
        echo '<div class="bh3-fm-grid"><label>Group name<input id="bh3-group-name" name="name" required maxlength="190" value="' . esc_attr( $edit_group['name'] ?? '' ) . '"></label><label>Unique key<input id="bh3-group-key" name="group_key" required maxlength="100" value="' . esc_attr( $edit_group['group_key'] ?? '' ) . '"></label></div><p>Assign to: ';
        foreach ( array( 'activity' => 'Activities', 'venue' => 'Venues', 'session' => 'Sessions' ) as $key => $label ) {
            echo '<label style="margin-right:14px"><input type="checkbox" name="targets[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, explode( ',', $edit_group['targets'] ?? '' ), true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</p><button class="button button-primary">' . ( $edit_group ? 'Save group' : 'Create group' ) . '</button></form></section>';
        echo '<section class="bh3-fm-card"><h2>Field definitions</h2><table class="widefat striped"><thead><tr><th>Group</th><th>Field</th><th>Key</th><th>Type</th><th>Required</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $fields as $field ) {
            $group_name = '';
            foreach ( $groups as $g ) { if ( (int) $g['id'] === (int) $field['group_id'] ) { $group_name = $g['name']; break; } }
            echo '<tr><td>' . esc_html( $group_name ) . '</td><td>' . esc_html( $field['label'] ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-field-manager', 'edit_field' => absint( $field['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a>' . '</td><td><code>' . esc_html( $field['field_key'] ) . '</code></td><td>' . esc_html( $field['field_type'] ) . '</td><td>' . ( $field['is_required'] ? 'Yes' : 'No' ) . '</td><td><div class="bh3-fm-actions">' . self::action_form( 'bh3_field_move', 'bh3_field_move', array( 'id' => $field['id'], 'direction' => 'up' ), '↑' ) . self::action_form( 'bh3_field_move', 'bh3_field_move', array( 'id' => $field['id'], 'direction' => 'down' ), '↓' ) . self::action_form( 'bh3_field_toggle', 'bh3_field_toggle', array( 'kind' => 'field', 'id' => $field['id'] ), $field['is_active'] ? 'Disable' : 'Enable' ) . '</div></td></tr>';
        }
        if ( ! $fields ) { echo '<tr><td colspan="6">No custom fields yet.</td></tr>'; }
        echo '</tbody></table></section>';
        self::render_builder( $groups, $fields );
        echo '<section class="bh3-fm-card" id="bh3-field-editor"><h2>' . ( $edit_field ? 'Edit field' : 'Add field to group' ) . '</h2><p class="description">Configure your field here and see its appearance update before saving.</p>'; 
        if ( $groups ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_definition_save">';
            wp_nonce_field( 'bh3_field_definition_save' );
            echo '<input type="hidden" name="field_id" value="' . esc_attr( (string) ( $edit_field['id'] ?? 0 ) ) . '">';
            echo '<div class="bh3-fm-grid"><label>Group<select name="group_id">';
            foreach ( $groups as $group ) { echo '<option value="' . esc_attr( (string) $group['id'] ) . '" ' . selected( (int) ( $edit_field['group_id'] ?? $add_to_group ), (int) $group['id'], false ) . '>' . esc_html( $group['name'] ) . '</option>'; }
            echo '</select></label><label>Label<input id="bh3-field-label" name="label" maxlength="190" required value="' . esc_attr( $edit_field['label'] ?? '' ) . '"></label><label>Unique field key<input id="bh3-field-key" name="field_key" maxlength="100" required value="' . esc_attr( $edit_field['field_key'] ?? '' ) . '"></label></div>';
            echo '<p><label>Field type <select name="field_type">';
            foreach ( array( 'text', 'textarea', 'number', 'checkbox', 'select', 'multiselect', 'date', 'time', 'url', 'email' ) as $type ) { echo '<option value="' . esc_attr( $type ) . '" ' . selected( $edit_field['field_type'] ?? '', $type, false ) . '>' . esc_html( ucfirst( $type ) ) . '</option>'; }
            echo '</select></label> <label><input type="checkbox" name="is_required" value="1" ' . checked( (int) ( $edit_field['is_required'] ?? 0 ), 1, false ) . '> Required</label></p>';
            echo '<p><label>Options (for select/multiselect, one per line)<br><textarea name="options" rows="5" cols="55">' . esc_textarea( implode( "\n", json_decode( $edit_field['options_json'] ?? '[]', true ) ?: array() ) ) . '</textarea></label></p>';
            echo '<div class="bh3-field-editor-preview" aria-live="polite"><strong>Field appearance preview</strong><p class="description">Example only — changes are not saved until you press Save field.</p><div class="bh3-field-editor-example"></div></div>';
            echo '<button class="button button-primary">' . ( $edit_field ? 'Save field' : 'Create field' ) . '</button></form>';
        }
        if ( $edit_field ) {
            $placement = self::placements()[ (int) $edit_field['id'] ] ?? array();
            echo '<div class="bh3-fm-card"><h2>Field placement</h2><p>Choose where this field belongs in the listing wizard. Display and search flags are stored for future layout integrations; they do not yet expose fields publicly.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_placement_save"><input type="hidden" name="field_id" value="' . esc_attr( (string) $edit_field['id'] ) . '">';
            wp_nonce_field( 'bh3_field_placement_save' );
            echo '<p><label>Wizard section <select name="wizard_step">';
            foreach ( array( 'basics' => '1. Basics', 'about' => '2. About & categories', 'pricing' => '3. Age & pricing', 'contact' => '4. Contact & booking', 'images' => '5. Images & accessibility', 'locations' => '6. Locations & timetable' ) as $key => $label ) {
                echo '<option value="' . esc_attr( $key ) . '" ' . selected( $placement['wizard_step'] ?? 'about', $key, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></label></p><p><label><input type="checkbox" name="show_on_detail" value="1" ' . checked( ! empty( $placement['show_on_detail'] ), true, false ) . '> Include on detail page (future integration)</label></p><p><label><input type="checkbox" name="show_in_search" value="1" ' . checked( ! empty( $placement['show_in_search'] ), true, false ) . '> Available as search filter (future integration)</label></p><button class="button button-primary">Save placement</button></form></div>';
        }
        echo '<script>(function(){function keyify(v){return v.toLowerCase().trim().replace(/[^a-z0-9]+/g,"_").replace(/^_+|_+$/g,"").slice(0,100)}for(const pair of [["bh3-group-name","bh3-group-key"],["bh3-field-label","bh3-field-key"]]){const source=document.getElementById(pair[0]),key=document.getElementById(pair[1]);if(source&&key){let manual=!!key.value;key.addEventListener("input",()=>{manual=!!key.value});source.addEventListener("input",()=>{if(!manual)key.value=keyify(source.value)})}}const preview=document.querySelector(".bh3-field-editor-example");if(preview){const form=preview.closest("form");const render=()=>{const type=form.elements.namedItem("field_type")?.value||"text",label=form.elements.namedItem("label")?.value||"Custom field",required=form.elements.namedItem("is_required")?.checked;preview.replaceChildren();const title=document.createElement("label");title.textContent=label+(required?" *":"");preview.appendChild(title);let control;if(type==="textarea"){control=document.createElement("textarea")}else if(type==="select"||type==="multiselect"){control=document.createElement("select");if(type==="multiselect"){control.multiple=true;control.size=3}const opts=(form.elements.namedItem("options")?.value||"").split(/\r?\n/).map(s=>s.trim()).filter(Boolean);for(const opt of opts.length?opts:["Example option"]){const option=document.createElement("option");option.textContent=opt;control.appendChild(option)}}else{control=document.createElement("input");control.type=type==="checkbox"?"checkbox":type}control.disabled=true;control.setAttribute("aria-label",label+" preview");preview.appendChild(control)};form.addEventListener("input",render);form.addEventListener("change",render);render()}})();</script></section></div>';
    }
}
