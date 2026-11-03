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
        if ( '' === $title || '' === $category || mb_strlen( $title ) > 190 || mb_strlen( $category ) > 120 ) {
            wp_die( 'Title and category are required and must fit the legacy schema.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM bh_activities WHERE id = %d', $id ) );
        if ( (int) $existing !== $id ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        $updated = $wpdb->update(
            'bh_activities',
            array( 'title' => $title, 'category' => $category, 'description' => $description ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
        if ( false === $updated ) { wp_die( 'Database update failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-activity-detail', 'activity_id' => $id, 'bh3_saved' => '1' ), admin_url( 'tools.php' ) ) );
        exit;
    }
}
