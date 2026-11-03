<?php
/**
 * Read-only staging audit of legacy directory tables.
 * Never creates or modifies tables and never exposes personal records.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Directory_Audit_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Directory readiness', 'Directory readiness', 'manage_options', 'bh3-directory-audit', array( __CLASS__, 'render' ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'bubba-hub-manager' ) );
        }
        global $wpdb;
        echo '<div class="wrap"><h1>Directory readiness audit</h1>';
        echo '<p>Read-only inventory. No modules are enabled and no records are changed. Verify mappings before building the directory.</p>';
        foreach ( array( 'bh_activities', 'bh_venues', 'bh_organisers' ) as $suffix ) {
            // Existing legacy tables may use literal bh_ names or WordPress-prefixed names.
            $candidates = array_unique( array( $suffix, $wpdb->prefix . $suffix ) );
            $found = null;
            foreach ( $candidates as $candidate ) {
                $match = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) );
                if ( $match === $candidate ) { $found = $candidate; break; }
            }
            echo '<h2>' . esc_html( $suffix ) . '</h2>';
            if ( ! $found ) {
                echo '<p>Not found in the current WordPress database connection. This does not prove the legacy table is absent from another database.</p>';
                continue;
            }
            // Identifier comes only from fixed, internally constructed table names.
            $quoted = '`' . str_replace( '`', '``', $found ) . '`';
            $columns = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $quoted, ARRAY_A );
            echo '<p>Found: <code>' . esc_html( $found ) . '</code></p>';
            if ( ! is_array( $columns ) ) {
                echo '<p>Unable to inspect columns; check database permissions.</p>';
                continue;
            }
            echo '<table class="widefat striped"><thead><tr><th>Column</th><th>Type</th><th>Nullable</th><th>Key</th></tr></thead><tbody>';
            foreach ( $columns as $column ) {
                echo '<tr><td>' . esc_html( $column['Field'] ) . '</td><td>' . esc_html( $column['Type'] ) . '</td><td>' . esc_html( $column['Null'] ) . '</td><td>' . esc_html( $column['Key'] ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p><strong>Next:</strong> confirm legacy table connections, column mappings, publication status, venue joins and organiser joins before any frontend directory activation.</p></div>';
    }
}
