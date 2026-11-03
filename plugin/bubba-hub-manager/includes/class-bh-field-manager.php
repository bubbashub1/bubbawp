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
        echo '<div class="wrap bh3-fm"><style>.bh3-fm{max-width:1200px}.bh3-fm .bh3-fm-card{background:#fff;border:1px solid #dbe5de;border-radius:12px;padding:20px;margin:18px 0}.bh3-fm .bh3-fm-card h2{margin-top:0}.bh3-fm .bh3-fm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.bh3-fm .bh3-fm-grid label{display:flex;flex-direction:column;gap:6px;font-weight:600}.bh3-fm .bh3-fm-grid input,.bh3-fm .bh3-fm-grid select{width:100%;max-width:100%;box-sizing:border-box}.bh3-fm .bh3-fm-checks{display:flex;gap:18px;flex-wrap:wrap;margin:16px 0}.bh3-fm .bh3-fm-checks label{display:flex;align-items:center;gap:7px}.bh3-fm .bh3-fm-checks input{margin:0}.bh3-fm .bh3-fm-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.bh3-fm .bh3-fm-actions form{display:inline}.bh3-fm .bh3-fm-muted{color:#66746b}.bh3-fm .bh3-fm-card table{border-radius:8px;overflow:hidden}.bh3-fm .bh3-fm-card td{vertical-align:middle}.bh3-fm .bh3-fm-disabled{opacity:.55}@media(max-width:700px){.bh3-fm .bh3-fm-grid{grid-template-columns:1fr}.bh3-fm .bh3-fm-card{padding:14px;overflow-x:auto}}</style><h1>Bubba Hub Field Manager</h1><p>Create reusable field groups and assign them to activities, venues and sessions. Values on individual records will be added in the next stage.</p>'; 
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
        $edit_group = null; $edit_field = null;
        foreach ( $groups as $g ) { if ( (int) $g['id'] === $edit_group_id ) { $edit_group = $g; break; } }
        foreach ( $fields as $f ) { if ( (int) $f['id'] === $edit_field_id ) { $edit_field = $f; break; } }

        echo '<section class="bh3-fm-card"><h2>Field groups</h2><table class="widefat striped"><thead><tr><th>Group</th><th>Key</th><th>Assigned to</th><th>Fields</th></tr></thead><tbody>';
        foreach ( $groups as $group ) {
            $count = count( array_filter( $fields, function( $f ) use ( $group ) { return (int) $f['group_id'] === (int) $group['id']; } ) );
            echo '<tr><td>' . esc_html( $group['name'] ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-field-manager', 'edit_group' => absint( $group['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a></td><td><code>' . esc_html( $group['group_key'] ) . '</code></td><td>' . esc_html( str_replace( ',', ', ', $group['targets'] ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
        }
        if ( ! $groups ) { echo '<tr><td colspan="4">No groups yet.</td></tr>'; }
        echo '</tbody></table></section><section class="bh3-fm-card"><h2>' . ( $edit_group ? 'Edit field group' : 'Add field group' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_group_save">';
        wp_nonce_field( 'bh3_field_group_save' );
        echo '<input type="hidden" name="group_id" value="' . esc_attr( (string) ( $edit_group['id'] ?? 0 ) ) . '">';
        echo '<p><label>Group name <input name="name" required maxlength="190"></label> <label>Unique key <input name="group_key" required maxlength="100" pattern="[a-z0-9_\-]+"></label></p><p>Assign to: ';
        foreach ( array( 'activity' => 'Activities', 'venue' => 'Venues', 'session' => 'Sessions' ) as $key => $label ) {
            echo '<label style="margin-right:14px"><input type="checkbox" name="targets[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, explode( ',', $edit_group['targets'] ?? '' ), true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</div><button class="button button-primary">' . ( $edit_group ? 'Save group' : 'Create group' ) . '</button></form></section>';
        echo '<section class="bh3-fm-card"><h2>Field definitions</h2><table class="widefat striped"><thead><tr><th>Group</th><th>Field</th><th>Key</th><th>Type</th><th>Required</th></tr></thead><tbody>';
        foreach ( $fields as $field ) {
            $group_name = '';
            foreach ( $groups as $g ) { if ( (int) $g['id'] === (int) $field['group_id'] ) { $group_name = $g['name']; break; } }
            echo '<tr><td>' . esc_html( $group_name ) . '</td><td>' . esc_html( $field['label'] ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-field-manager', 'edit_field' => absint( $field['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a>' . '</td><td><code>' . esc_html( $field['field_key'] ) . '</code></td><td>' . esc_html( $field['field_type'] ) . '</td><td>' . ( $field['is_required'] ? 'Yes' : 'No' ) . '</td></tr>';
        }
        if ( ! $fields ) { echo '<tr><td colspan="5">No custom fields yet.</td></tr>'; }
        echo '</tbody></table></section><section class="bh3-fm-card"><h2>' . ( $edit_field ? 'Edit field' : 'Add field to group' ) . '</h2>'; 
        if ( $groups ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_field_definition_save">';
            wp_nonce_field( 'bh3_field_definition_save' );
            echo '<input type="hidden" name="field_id" value="' . esc_attr( (string) ( $edit_field['id'] ?? 0 ) ) . '">';
            echo '<div class="bh3-fm-grid"><label>Group<select name="group_id">';
            foreach ( $groups as $group ) { echo '<option value="' . esc_attr( (string) $group['id'] ) . '" ' . selected( (int) ( $edit_field['group_id'] ?? 0 ), (int) $group['id'], false ) . '>' . esc_html( $group['name'] ) . '</option>'; }
            echo '</select></label> <label>Label <input name="label" maxlength="190" required></label> <label>Unique field key <input name="field_key" maxlength="100" required pattern="[a-z0-9_\-]+"></label></p>';
            echo '<p><label>Field type <select name="field_type">';
            foreach ( array( 'text', 'textarea', 'number', 'checkbox', 'select', 'multiselect', 'date', 'time', 'url', 'email' ) as $type ) { echo '<option value="' . esc_attr( $type ) . '" ' . selected( $edit_field['field_type'] ?? '', $type, false ) . '>' . esc_html( ucfirst( $type ) ) . '</option>'; }
            echo '</select></label> <label><input type="checkbox" name="is_required" value="1" ' . checked( (int) ( $edit_field['is_required'] ?? 0 ), 1, false ) . '> Required</label></p>';
            echo '<p><label>Options (for select/multiselect, one per line)<br><textarea name="options" rows="5" cols="55">' . esc_textarea( implode( "\n", json_decode( $edit_field['options_json'] ?? '[]', true ) ?: array() ) ) . '</textarea></label></p>';
            echo '<button class="button button-primary">' . ( $edit_field ? 'Save field' : 'Create field' ) . '</button></form>';
        }
        echo '<script>(function(){function keyify(v){return v.toLowerCase().trim().replace(/[^a-z0-9]+/g,"_").replace(/^_+|_+$/g,"").slice(0,100)}for(const pair of [["bh3-group-name","bh3-group-key"],["bh3-field-label","bh3-field-key"]]){const source=document.getElementById(pair[0]),key=document.getElementById(pair[1]);if(source&&key){let manual=!!key.value;key.addEventListener("input",()=>{manual=!!key.value});source.addEventListener("input",()=>{if(!manual)key.value=keyify(source.value)})}}})();</script></section></div>';
    }
}
