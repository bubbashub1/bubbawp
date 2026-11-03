<?php
/**
 * Read-only activity browser for legacy bh_activities.
 * No legacy endpoints are executed and no data is modified.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activities_Admin_3 {
    public static function register() {
        add_management_page(
            __( 'Bubba Hub Activities', 'bubba-hub-manager' ),
            __( 'BH Activities', 'bubba-hub-manager' ),
            'manage_options',
            'bh3-activities',
            array( __CLASS__, 'render' )
        );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'bubba-hub-manager' ) );
        }
        global $wpdb;
        echo '<div class="wrap"><h1>' . esc_html__( 'Bubba Hub Activities', 'bubba-hub-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Read-only preview of existing published activities. Editing is disabled until the legacy data and permissions are verified.', 'bubba-hub-manager' ) . '</p>';
        $repo = new BH_Activity_Repository_3( $wpdb );
        if ( ! $repo->available() ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'The bh_activities table was not found in this WordPress database. No changes have been made.', 'bubba-hub-manager' ) . '</p></div></div>';
            return;
        }
        $page = isset( $_GET['bh_page'] ) ? max( 1, absint( wp_unslash( $_GET['bh_page'] ) ) ) : 1;
        $search = isset( $_GET['bh_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ) : '';
        $rows = $repo->search( array( 'page' => $page, 'per_page' => 20, 'search' => $search ) );
        echo '<form method="get" style="margin:16px 0">';
        echo '<input type="hidden" name="page" value="bh3-activities">';
        echo '<label for="bh3-search">' . esc_html__( 'Search activities', 'bubba-hub-manager' ) . '</label> ';
        echo '<input id="bh3-search" type="search" name="bh_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Title or description', 'bubba-hub-manager' ) . '"> ';
        submit_button( __( 'Search', 'bubba-hub-manager' ), 'secondary', '', false );
        echo '</form>';
        if ( is_wp_error( $rows ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $rows->get_error_message() ) . '</p></div></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th scope="col">ID</th><th scope="col">' . esc_html__( 'Activity', 'bubba-hub-manager' ) . '</th><th scope="col">' . esc_html__( 'Slug', 'bubba-hub-manager' ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( (string) $row['id'] ) . '</td><td>' . esc_html( $row['title'] ) . '</td><td><code>' . esc_html( $row['slug'] ) . '</code></td></tr>';
        }
        if ( ! $rows ) {
            echo '<tr><td colspan="3">' . esc_html__( 'No published activities found.', 'bubba-hub-manager' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ( $page > 1 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh_search' => $search, 'bh_page' => $page - 1 ), admin_url( 'tools.php' ) ) ) . '">' . esc_html__( 'Previous', 'bubba-hub-manager' ) . '</a> ';
        }
        if ( count( $rows ) === 20 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-activities', 'bh_search' => $search, 'bh_page' => $page + 1 ), admin_url( 'tools.php' ) ) ) . '">' . esc_html__( 'Next', 'bubba-hub-manager' ) . '</a>';
        }
        echo '</div></div></div>';
    }
}
