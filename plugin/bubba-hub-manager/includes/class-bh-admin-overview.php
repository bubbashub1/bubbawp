<?php
/** Non-destructive admin overview; all existing screens and handlers remain registered. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Admin_Overview_3 {
    public static function register() {
        add_submenu_page( 'bubba-hub-3-status', 'Overview', 'Overview', 'manage_options', 'bh3-overview', array( __CLASS__, 'render' ), 0 );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission required.' ); }
        $groups = array(
            'Directory & listings' => array(
                'Directory Builder' => 'bh3-directory-builder',
                'Field Manager' => 'bh3-field-manager',
                'Activities' => 'bh3-activities',
                'Venues' => 'bh3-venues',
                'Organisers' => 'bh3-organisers',
            ),
            'Development & diagnostics' => array(
                'Developments' => 'bh3-developments',
                'Workstreams' => 'bh3-workstreams',
                'Directory readiness' => 'bh3-directory-audit',
                'System status and database checks' => 'bubba-hub-3-status',
                'Booking Test Generator' => 'bh3-booking-test-generator',
            ),
            'Settings' => array(
                'Marketplace Commission' => 'bh3-marketplace',
            ),
        );
        echo '<div class="wrap"><h1>Bubba Hub overview</h1><p>Quick access to existing administration tools. This screen does not modify records, schemas, feature flags or permissions.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1100px">';
        global $submenu;
        $registered = array();
        foreach ( $submenu as $items ) {
            foreach ( $items as $item ) { if ( isset( $item[2] ) ) { $registered[ $item[2] ] = true; } }
        }
        foreach ( $groups as $title => $links ) {
            echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><h2>' . esc_html( $title ) . '</h2><ul>';
            foreach ( $links as $label => $slug ) {
                if ( ! isset( $registered[ $slug ] ) && $slug !== 'bubba-hub-3-status' ) { continue; }
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( $slug ) ) ) . '">' . esc_html( $label ) . '</a></li>';
            }
            echo '</ul></section>';
        }
        echo '</div><p>Legacy screens remain available until their replacement navigation has been tested on staging.</p></div>';
    }
}
