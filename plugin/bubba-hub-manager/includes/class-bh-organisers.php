<?php
/** Staging-only organiser profiles for the fresh BH3 schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Organisers_3 {
    private static function allowed() {
        return current_user_can( 'manage_options' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Organisers', 'Organisers', 'manage_options', 'bh3-organisers', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_save_organiser', array( __CLASS__, 'save' ) );
    }
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'organisers';
    }
    public static function render() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $table = self::table();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            wp_die( 'Organisers table is missing.', '', array( 'response' => 409 ) );
        }
        $id = absint( $_GET['organiser_id'] ?? 0 );
        $row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ), ARRAY_A ) : null;
        if ( $id && ! $row ) { wp_die( 'Organiser not found.', '', array( 'response' => 404 ) ); }
        echo '<div class="wrap"><h1>Organisers</h1>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Organiser saved.</p></div>'; }
        echo '<p>Create and update organiser profiles. Deletion and account claiming are not enabled yet.</p>';
        echo '<h2>' . ( $id ? 'Edit organiser' : 'Add organiser' ) . '</h2>';
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="bh3-organisers"><input type="hidden" name="organiser_id" value="' . esc_attr( (string) $id ) . '">';
        echo '<label for="user_search">Find WordPress user</label> <input id="user_search" type="search" name="user_search" value="' . esc_attr( $search_user ?? sanitize_text_field( wp_unslash( $_GET['user_search'] ?? '' ) ) ) . '" placeholder="Name, username or email"> ';
        submit_button( 'Find user', 'secondary', '', false );
        echo '</form>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_save_organiser"><input type="hidden" name="organiser_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_save_organiser_' . $id );
        echo '<table class="form-table"><tbody>';
        foreach ( array( 'name' => 'Organiser name', 'website_url' => 'Website URL', 'contact_email' => 'Contact email' ) as $key => $label ) {
            $type = $key === 'website_url' ? 'url' : ( $key === 'contact_email' ? 'email' : 'text' );
            echo '<tr><th><label for="org_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="' . esc_attr( $type ) . '" id="org_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $row[$key] ?? '' ) ) . '"' . ( $key === 'name' ? ' required maxlength="255"' : '' ) . '></td></tr>';
        }
        echo '<tr><th><label for="org_description">Description</label></th><td><textarea class="large-text" rows="6" id="org_description" name="description">' . esc_textarea( (string) ( $row['description'] ?? '' ) ) . '</textarea></td></tr>';
        $linked_users = $id ? get_users( array( 'meta_key' => 'bh3_organiser_id', 'meta_value' => (string) $id, 'number' => 2 ) ) : array();
        $linked = $linked_users ? $linked_users[0] : null;
        $search_user = isset( $_GET['user_search'] ) && is_string( $_GET['user_search'] )
            ? substr( sanitize_text_field( wp_unslash( $_GET['user_search'] ) ), 0, 100 ) : '';
        echo '<tr><th><label for="org_user">Linked WordPress user</label></th><td>';
        echo '<select class="regular-text" id="org_user" name="linked_user_id">';
        echo '<option value="0">No linked user</option>';
        $options = array();
        if ( $linked ) { $options[$linked->ID] = $linked; }
        if ( strlen( trim( $search_user ) ) >= 2 ) {
            $matches = get_users( array( 'search' => '*' . esc_attr( $search_user ) . '*',
                'search_columns' => array( 'user_login', 'user_email', 'display_name' ), 'number' => 25 ) );
            foreach ( $matches as $user ) { $options[$user->ID] = $user; }
        }
        foreach ( $options as $user ) {
            echo '<option value="' . esc_attr( (string) $user->ID ) . '"' . selected( $linked && $linked->ID === $user->ID, true, false ) . '>' .
                esc_html( $user->display_name . ' (' . $user->user_login . ')' ) . '</option>';
        }
        echo '</select><p class="description">Choose an existing account. Only administrators can link users; this does not grant editing permissions.</p>';
        echo '<p>To find a user, search below first, then select the result above.</p></td></tr>';
        echo '</tbody></table>';
        submit_button( $id ? 'Update organiser' : 'Create organiser' );
        echo '</form>';
        if ( $id ) { echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bh3-organisers' ) ) . '">Add another organiser</a></p>'; }
        $term = substr( sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ), 0, 100 );
        echo '<h2>Existing organisers</h2><form method="get"><input type="hidden" name="page" value="bh3-organisers"><input type="search" name="search" value="' . esc_attr( $term ) . '" placeholder="Search organisers"> ';
        submit_button( 'Search', 'secondary', '', false );
        echo '</form>';
        $where = $term === '' ? '' : $wpdb->prepare( 'WHERE name LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
        $activities = $wpdb->prefix . 'activities';
        $rows = $wpdb->get_results( "SELECT o.id, o.name, o.website_url, COUNT(a.id) AS activity_count FROM `{$table}` o LEFT JOIN `{$activities}` a ON a.organiser_id = o.id {$where} GROUP BY o.id, o.name, o.website_url ORDER BY o.name LIMIT 100", ARRAY_A );
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Website</th><th>Linked user</th><th>Activities</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $organiser ) {
            $url = add_query_arg( array( 'page' => 'bh3-organisers', 'organiser_id' => absint( $organiser['id'] ) ), admin_url( 'admin.php' ) );
            $owners = get_users( array( 'meta_key' => 'bh3_organiser_id', 'meta_value' => (string) absint( $organiser['id'] ), 'number' => 1 ) );
            echo '<tr><td>' . esc_html( $organiser['name'] ) . '</td><td>' . esc_html( (string) $organiser['website_url'] ) . '</td><td>' . esc_html( $owners ? $owners[0]->user_login : 'Not linked' ) . '</td><td>' . esc_html( (string) $organiser['activity_count'] ) . '</td><td><a href="' . esc_url( $url ) . '">Edit</a></td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="5">No organisers found.</td></tr>'; }
        echo '</tbody></table></div>';
    }
    public static function save() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['organiser_id'] ?? 0 );
        check_admin_referer( 'bh3_save_organiser_' . $id );
        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $url = esc_url_raw( wp_unslash( $_POST['website_url'] ?? '' ) );
        if ( $name === '' || strlen( $name ) > 255 || strlen( $email ) > 254 || ( $email !== '' && ! is_email( $email ) ) ||
            strlen( $url ) > 2048 || ( $url && ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) {
            wp_die( 'Invalid organiser details.', '', array( 'response' => 400 ) );
        }
        $user_id = absint( $_POST['linked_user_id'] ?? 0 );
        $linked_user = $user_id ? get_user_by( 'id', $user_id ) : null;
        if ( $user_id && ! $linked_user ) { wp_die( 'Selected WordPress user not found.', '', array( 'response' => 400 ) ); }
        if ( $linked_user ) {
            $other = absint( get_user_meta( $linked_user->ID, 'bh3_organiser_id', true ) );
            if ( $other && $other !== $id ) { wp_die( 'This user is already linked to another organiser.', '', array( 'response' => 409 ) ); }
        }
        global $wpdb;
        $table = self::table();
        $now = current_time( 'mysql', true );
        $data = array( 'name' => $name, 'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ), 'website_url' => $url, 'contact_email' => $email, 'updated_at' => $now );
        if ( $id ) {
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d", $id ) ) ) { wp_die( 'Organiser not found.', '', array( 'response' => 404 ) ); }
            $result = $wpdb->update( $table, $data, array( 'id' => $id ) );
        } else {
            $base = substr( sanitize_title( $name ) ?: 'organiser', 0, 175 );
            $slug = $base;
            for ( $n = 2; $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s LIMIT 1", $slug ) ); ++$n ) {
                $slug = $base . '-' . $n;
                if ( $n > 10000 ) { wp_die( 'Unable to allocate organiser slug.', '', array( 'response' => 409 ) ); }
            }
            $data['slug'] = $slug;
            $data['created_at'] = $now;
            $result = $wpdb->insert( $table, $data );
            $id = (int) $wpdb->insert_id;
        }
        if ( $result === false ) { wp_die( 'Unable to save organiser.', '', array( 'response' => 500 ) ); }
        // Maintain the one-to-one association without altering the manually created schema.
        $existing_users = get_users( array( 'meta_key' => 'bh3_organiser_id', 'meta_value' => (string) $id, 'fields' => 'ID' ) );
        foreach ( $existing_users as $existing_user_id ) {
            if ( ! $linked_user || (int) $existing_user_id !== (int) $linked_user->ID ) {
                delete_user_meta( (int) $existing_user_id, 'bh3_organiser_id', (string) $id );
            }
        }
        if ( $linked_user ) { update_user_meta( $linked_user->ID, 'bh3_organiser_id', (string) $id ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-organisers', 'organiser_id' => $id, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
