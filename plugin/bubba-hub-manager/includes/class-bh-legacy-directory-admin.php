<?php
/**
 * Read-only legacy venue and organiser inspection in WordPress admin.
 * Never executes standalone API PHP or writes to legacy tables.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Legacy_Directory_Admin_3 {
    public static function register() {
        add_management_page( 'Bubba Hub Venues', 'BH Venues', 'manage_options', 'bh3-venues', array( __CLASS__, 'render_venues' ) );
        add_management_page( 'Bubba Hub Organisers', 'BH Organisers', 'manage_options', 'bh3-organisers', array( __CLASS__, 'render_organisers' ) );
    }

    private static function table_available( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
    }

    private static function browse( $table, $title, $columns, $search_column, $slug ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'bubba-hub-manager' ) );
        }
        global $wpdb;
        echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1><p>Read-only legacy data preview. Editing is not enabled.</p>';
        if ( ! self::table_available( $table ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html( $table . ' is not available in this database.' ) . '</p></div></div>';
            return;
        }
        $page = isset( $_GET['bh_page'] ) ? max( 1, absint( wp_unslash( $_GET['bh_page'] ) ) ) : 1;
        $search = isset( $_GET['bh_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ) : '';
        $params = array();
        $where = '';
        if ( '' !== $search ) {
            $where = ' WHERE ' . $search_column . ' LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        $params[] = 20;
        $params[] = ( $page - 1 ) * 20;
        // Table and column identifiers are hardcoded by the plugin, not user input.
        $sql = 'SELECT ' . implode( ', ', array_keys( $columns ) ) . ' FROM ' . $table . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        if ( $wpdb->last_error ) {
            echo '<div class="notice notice-error"><p>Unable to query these legacy records. Check the table schema.</p></div></div>';
            return;
        }
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( $slug ) . '"><label for="bh3-search">Search </label><input id="bh3-search" type="search" name="bh_search" value="' . esc_attr( $search ) . '"> ';
        submit_button( 'Search', 'secondary', '', false );
        echo '</form><table class="widefat striped" style="margin-top:16px"><thead><tr>';
        foreach ( $columns as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows ?: array() as $row ) {
            echo '<tr>';
            foreach ( array_keys( $columns ) as $key ) {
                echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>';
            }
            echo '</tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="' . esc_attr( (string) count( $columns ) ) . '">No records found.</td></tr>'; }
        echo '</tbody></table><p>';
        if ( $page > 1 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => $slug, 'bh_search' => $search, 'bh_page' => $page - 1 ), admin_url( 'tools.php' ) ) ) . '">Previous</a> ';
        }
        if ( count( $rows ?: array() ) === 20 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => $slug, 'bh_search' => $search, 'bh_page' => $page + 1 ), admin_url( 'tools.php' ) ) ) . '">Next</a>';
        }
        echo '</p></div>';
    }

    public static function render_venues() {
        self::browse( 'bh_venues', 'Bubba Hub Venues', array( 'id' => 'ID', 'venue_name' => 'Venue', 'town' => 'Town', 'region' => 'Region', 'postcode' => 'Postcode' ), 'venue_name', 'bh3-venues' );
    }

    public static function render_organisers() {
        self::browse( 'bh_organisers', 'Bubba Hub Organisers', array( 'id' => 'ID', 'organisation_name' => 'Organisation', 'slug' => 'Slug', 'status' => 'Status' ), 'organisation_name', 'bh3-organisers' );
    }
}
