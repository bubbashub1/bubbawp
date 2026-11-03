<?php
/**
 * Opt-in legacy activity editor. Disabled by default; no schema migrations.
 * Only WP administrators can save. Never invokes legacy session endpoints.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activity_Editor_3 {
    const OPTION = 'bh3_enable_activity_editing';

    public static function enabled() {
        return defined( 'BH3_ENABLE_LEGACY_ACTIVITY_EDITING' ) && BH3_ENABLE_LEGACY_ACTIVITY_EDITING === true;
    }

    public static function register() {
        add_action( 'admin_post_bh3_update_activity', array( __CLASS__, 'save' ) );
    }

    public static function form( $activity ) {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) {
            echo '<p><em>Editing is disabled until staging database and permissions are verified.</em></p>';
            return;
        }
        $id = absint( $activity['id'] );
        echo '<h2>Edit listing</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_update_activity">';
        echo '<input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'bh3_update_activity_' . $id );
        echo '<table class="form-table"><tbody>';
        foreach ( array( 'title' => 'Title', 'category' => 'Category' ) as $key => $label ) {
            echo '<tr><th><label for="bh3-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="bh3-' . esc_attr( $key ) . '" type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $activity[ $key ] ?? '' ) ) . '" required></td></tr>';
        }
        echo '<tr><th><label for="bh3-price">Price from (£)</label></th><td><input id="bh3-price" type="number" min="0" max="99999999.99" step="0.01" name="price_from" value="' . esc_attr( (string) ( $activity['price_from'] ?? '' ) ) . '"> <small>Leave blank if unspecified.</small></td></tr>';
        echo '<tr><th><label for="bh3-status">Publishing status</label></th><td><select id="bh3-status" name="status">';
        foreach ( array( 'draft' => 'Draft', 'pending' => 'Pending review', 'published' => 'Published' ) as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $activity['status'] ?? '', $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="bh3-description">Description</label></th><td><textarea id="bh3-description" name="description" rows="8" class="large-text">' . esc_textarea( (string) ( $activity['description'] ?? '' ) ) . '</textarea></td></tr>';
        echo '</tbody></table>';
        submit_button( 'Save listing details' );
        echo '</form>';
    }

    public static function save() {
        if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Editing is not enabled.', '', array( 'response' => 403 ) );
        }
        $id = isset( $_POST['activity_id'] ) ? absint( wp_unslash( $_POST['activity_id'] ) ) : 0;
        if ( $id < 1 ) { wp_die( 'Invalid activity ID.', '', array( 'response' => 400 ) ); }
        check_admin_referer( 'bh3_update_activity_' . $id );
        $title = isset( $_POST['title'] ) && is_string( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $category = isset( $_POST['category'] ) && is_string( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $description = isset( $_POST['description'] ) && is_string( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $status = isset( $_POST['status'] ) && is_string( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        $price_raw = isset( $_POST['price_from'] ) && is_string( $_POST['price_from'] ) ? trim( wp_unslash( $_POST['price_from'] ) ) : '';
        if ( ! in_array( $status, array( 'draft', 'pending', 'published' ), true ) ) {
            wp_die( 'Invalid publishing status.', '', array( 'response' => 400 ) );
        }
        if ( '' !== $price_raw && ( ! preg_match( '/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $price_raw ) || (float) $price_raw > 99999999.99 ) ) {
            wp_die( 'Invalid price.', '', array( 'response' => 400 ) );
        }
        $price = '' === $price_raw ? null : number_format( (float) $price_raw, 2, '.', '' );
        if ( '' === $title || '' === $category || mb_strlen( $title ) > 190 || mb_strlen( $category ) > 120 ) {
            wp_die( 'Title and category are required and must fit the legacy schema.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id, status FROM bh_activities WHERE id = %d', $id ), ARRAY_A );
        if ( ! $existing ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        if ( 'archived' === $existing['status'] ) { wp_die( 'Archived activities cannot be edited here.', '', array( 'response' => 403 ) ); }
        $updated = $wpdb->update(
            'bh_activities',
            array( 'title' => $title, 'category' => $category, 'description' => $description, 'price_from' => $price, 'status' => $status ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        if ( false === $updated ) { wp_die( 'Database update failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-activity-detail', 'activity_id' => $id, 'bh3_saved' => '1' ), admin_url( 'tools.php' ) ) );
        exit;
    }
}
