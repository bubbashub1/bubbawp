<?php
/**
 * Presentation preferences only. No legacy tables or feature activation.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Settings_3 {
    const OPTION = 'bh3_presentation_settings';

    public static function defaults() {
        return array(
            'directory_view' => 'list',
            'cards_per_row' => 3,
            'show_map_toggle' => 1,
            'show_calendar_toggle' => 1,
        );
    }

    public static function current() {
        $stored = get_option( self::OPTION, array() );
        if ( ! is_array( $stored ) ) { $stored = array(); }
        return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
    }

    public static function register() {
        add_submenu_page(
            'bubba-hub-3-status',
            __( 'Bubba Hub 3.0 Settings', 'bubba-hub-manager' ),
            __( 'Bubba Hub Settings', 'bubba-hub-manager' ),
            'manage_options',
            'bh3-settings',
            array( __CLASS__, 'render' )
        );
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $view = isset( $input['directory_view'] ) && is_scalar( $input['directory_view'] ) ? (string) $input['directory_view'] : 'list';
        $columns = isset( $input['cards_per_row'] ) ? absint( $input['cards_per_row'] ) : 3;
        return array(
            'directory_view' => in_array( $view, array( 'list', 'grid' ), true ) ? $view : 'list',
            'cards_per_row' => max( 1, min( 4, $columns ) ),
            'show_map_toggle' => ! empty( $input['show_map_toggle'] ) ? 1 : 0,
            'show_calendar_toggle' => ! empty( $input['show_calendar_toggle'] ) ? 1 : 0,
        );
    }

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_save_settings' );
        $input = isset( $_POST['bh3'] ) && is_array( $_POST['bh3'] ) ? wp_unslash( $_POST['bh3'] ) : array();
        update_option( self::OPTION, self::sanitize( $input ), false );
        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'tools.php?page=bh3-settings' ) ) );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ) );
        }
        $v = self::current();
        echo '<div class="wrap"><h1>' . esc_html__( 'Bubba Hub 3.0 Settings', 'bubba-hub-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'These are saved display preferences for future directory integration. They do not yet change the public website or activate any modules.', 'bubba-hub-manager' ) . '</p>';
        if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Preferences saved.', 'bubba-hub-manager' ) . '</p></div>';
        }
        echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
        wp_nonce_field( 'bh3_save_settings' );
        echo '<input type="hidden" name="action" value="bh3_save_settings">';
        echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="bh3-view">' . esc_html__( 'Default directory view', 'bubba-hub-manager' ) . '</label></th><td><select id="bh3-view" name="bh3[directory_view]">';
        foreach ( array( 'list' => __( 'List', 'bubba-hub-manager' ), 'grid' => __( 'Grid', 'bubba-hub-manager' ) ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $v['directory_view'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr><tr><th scope="row"><label for="bh3-columns">' . esc_html__( 'Grid columns (desktop)', 'bubba-hub-manager' ) . '</label></th><td><select id="bh3-columns" name="bh3[cards_per_row]">';
        for ( $i = 1; $i <= 4; $i++ ) {
            echo '<option value="' . esc_attr( (string) $i ) . '"' . selected( $v['cards_per_row'], $i, false ) . '>' . esc_html( (string) $i ) . '</option>';
        }
        echo '</select></td></tr>';
        foreach ( array( 'show_map_toggle' => __( 'Offer map view', 'bubba-hub-manager' ), 'show_calendar_toggle' => __( 'Offer calendar view', 'bubba-hub-manager' ) ) as $key => $label ) {
            echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="bh3[' . esc_attr( $key ) . ']" value="1"' . checked( $v[$key], 1, false ) . '> ' . esc_html__( 'Enabled when the directory module is ready', 'bubba-hub-manager' ) . '</label></td></tr>';
        }
        echo '</tbody></table>';
        submit_button( __( 'Save preferences', 'bubba-hub-manager' ) );
        echo '</form></div>';
    }
}
