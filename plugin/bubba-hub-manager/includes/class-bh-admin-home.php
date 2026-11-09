<?php
/**
 * Friendly Bubba Hub administration home and navigation consolidation.
 * Presentation only: preserves existing page callbacks, data and permissions.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Admin_Home_3 {
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'replace_menu' ), 1000 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
    }

    public static function replace_menu() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        // Keep legacy screens and their URLs intact, but remove competing top-level entries.
        foreach ( array(
            'bh3-family-settings',
            'bh3-support-help',
            'bh3-team',
            'bh-support-dashboard',
            'bh3-leader-dashboard',
            'bh3-my-listings',
        ) as $slug ) {
            remove_menu_page( $slug );
        }

        // Re-register the existing Bubba Hub menu with this friendlier landing screen.
        remove_menu_page( 'bubba-hub-3-status' );
        add_menu_page(
            __( 'Bubba Hub', 'bubba-hub-manager' ),
            __( 'Bubba Hub', 'bubba-hub-manager' ),
            'manage_options',
            'bubba-hub-3-status',
            array( __CLASS__, 'render' ),
            'dashicons-location-alt',
            26
        );
    }

    private static function url( $page ) {
        return admin_url( 'admin.php?page=' . rawurlencode( $page ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }

        $sections = array(
            array( 'Activities & Directory', 'Manage listings, sessions, venues and organisers.', 'dashicons-location', 'bh3-activities', 'Open activities', 'ready' ),
            array( 'Families & Accounts', 'Family Hub setup, profile display and account checks.', 'dashicons-groups', 'bh3-family-settings', 'Open family settings', 'limited' ),
            array( 'Class Leaders', 'Leader portal readiness and leader-facing tools.', 'dashicons-businessperson', 'bh3-leader-settings', 'Open leader settings', 'limited' ),
            array( 'Bookings & Payments', 'Booking checks and payment configuration.', 'dashicons-calendar-alt', 'bh-booking-integration', 'Open booking checks', 'check' ),
            array( 'Events & Expo', 'A home for event listings, tickets and exhibitor management.', 'dashicons-tickets-alt', '', '', 'planned' ),
            array( 'Help & Content', 'Support resources, enquiries and article review.', 'dashicons-editor-help', 'bh-support-dashboard', 'Open support', 'limited' ),
            array( 'Marketing', 'Communications, newsletters and promotions.', 'dashicons-megaphone', '', '', 'planned' ),
            array( 'Reports', 'A clear view of directory, membership and booking performance.', 'dashicons-chart-bar', '', '', 'planned' ),
            array( 'Website Settings', 'Directory presentation, fields and website configuration.', 'dashicons-admin-settings', 'bh3-settings', 'Open settings', 'ready' ),
            array( 'Claims & Data Quality', 'Review directory readiness and keep records accurate.', 'dashicons-shield-alt', 'bh3-directory-audit', 'Open data checks', 'check' ),
            array( 'Developments', 'Roadmap, progress and technical checks for administrators.', 'dashicons-hammer', 'bh3-developments', 'Open developments', 'ready' ),
        );

        echo '<div class="wrap bh-admin-home">';
        echo '<header class="bh-admin-welcome"><p class="bh-admin-eyebrow">BUBBA HUB · TEAM ADMIN</p><h1>Welcome to Bubba Hub</h1><p>Everything for running the hub, organised into clear sections. Choose an area to get started.</p></header>';
        echo '<section class="bh-admin-quick"><h2>Quick actions</h2>';
        echo '<a class="bh-admin-action bh-admin-action-primary" href="' . esc_url( self::url( 'bh3-add-activity' ) ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span><strong>Add an activity</strong><small>Create a new directory listing</small></span></a>';
        echo '<a class="bh-admin-action" href="' . esc_url( self::url( 'bh3-activities' ) ) . '"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><span><strong>Manage activities</strong><small>View and update listings</small></span></a>';
        echo '<a class="bh-admin-action" href="' . esc_url( self::url( 'bh3-directory-audit' ) ) . '"><span class="dashicons dashicons-search" aria-hidden="true"></span><span><strong>Check directory data</strong><small>Review readiness and missing data</small></span></a>';
        echo '</section>';
        echo '<h2 class="bh-admin-section-title">Your admin areas</h2><div class="bh-admin-grid">';

        foreach ( $sections as $section ) {
            $class = 'bh-admin-card bh-admin-card-' . sanitize_html_class( $section[5] );
            echo '<article class="' . esc_attr( $class ) . '">';
            echo '<div class="bh-admin-card-icon"><span class="dashicons ' . esc_attr( $section[2] ) . '" aria-hidden="true"></span></div>';
            echo '<h3>' . esc_html( $section[0] ) . '</h3><p>' . esc_html( $section[1] ) . '</p>';
            if ( $section[3] ) {
                echo '<a href="' . esc_url( self::url( $section[3] ) ) . '">' . esc_html( $section[4] ) . ' <span aria-hidden="true">→</span></a>';
            } else {
                echo '<span class="bh-admin-coming">Not connected yet</span>';
            }
            echo '</article>';
        }

        echo '</div>';
        echo '<details class="bh-admin-technical"><summary>Technical tools and system checks</summary>';
        echo '<p>These tools are for maintenance and testing. You do not need them for everyday listing or content work.</p>';
        echo '<p><a href="' . esc_url( self::url( 'bh3-workstreams' ) ) . '">Workstream readiness</a> · <a href="' . esc_url( self::url( 'bh3-modules' ) ) . '">Module inventory</a> · <a href="' . esc_url( self::url( 'bubba-hub-3-status' ) ) . '">System status and database tools</a></p>';
        echo '</details>';
        echo '<p class="bh-admin-note">Some areas are still being connected and tested. A link being available does not mean that feature is live on the public website.</p>';
        echo '</div>';
    }

    public static function styles( $hook ) {
        if ( $hook !== 'toplevel_page_bubba-hub-3-status' ) { return; }
        wp_register_style( 'bh-admin-home', false, array(), '1.0.0' );
        wp_enqueue_style( 'bh-admin-home' );
        wp_add_inline_style( 'bh-admin-home', '
            .bh-admin-home{max-width:1240px;color:#263b32}
            .bh-admin-welcome{margin:24px 0 22px;padding:30px 34px;border-radius:18px;background:linear-gradient(115deg,#edf6ef 0%,#f7f1e8 100%)}
            .bh-admin-welcome h1{font-size:30px;line-height:1.2;margin:5px 0 9px;color:#214b38}
            .bh-admin-welcome p:last-child{font-size:15px;max-width:720px;margin:0;color:#53675b}
            .bh-admin-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.3px;color:#668170;margin:0}
            .bh-admin-quick{display:flex;flex-wrap:wrap;gap:12px;margin:0 0 30px}
            .bh-admin-quick h2{flex:0 0 100%;font-size:17px;margin:0 0 2px}
            .bh-admin-action{display:flex;align-items:center;gap:12px;min-width:205px;padding:13px 16px;background:#fff;border:1px solid #dce7df;border-radius:12px;text-decoration:none;box-shadow:0 2px 8px rgba(30,60,40,.04)}
            .bh-admin-action:hover{border-color:#8aaf98;background:#fbfdfb}
            .bh-admin-action .dashicons{font-size:23px;width:26px;height:26px;color:#47745a}
            .bh-admin-action-primary{background:#245b43;border-color:#245b43;color:#fff}
            .bh-admin-action-primary:hover{background:#194b35;color:#fff}
            .bh-admin-action-primary .dashicons{color:#fff}
            .bh-admin-action strong,.bh-admin-action small{display:block}
            .bh-admin-action strong{font-size:13px}
            .bh-admin-action small{font-size:11px;color:#6b7d71;margin-top:3px}
            .bh-admin-action-primary small{color:#dcebe1}
            .bh-admin-section-title{font-size:20px;margin:0 0 14px}
            .bh-admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
            .bh-admin-card{position:relative;min-height:190px;padding:20px;background:#fff;border:1px solid #e0e8e2;border-radius:15px;box-shadow:0 2px 10px rgba(30,60,40,.035);box-sizing:border-box}
            .bh-admin-card-icon{width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:11px;background:#edf5ef;color:#386b4d}
            .bh-admin-card-icon .dashicons{font-size:21px;width:21px;height:21px}
            .bh-admin-card h3{font-size:16px;margin:14px 0 6px;color:#294536}
            .bh-admin-card p{font-size:13px;line-height:1.5;color:#617168;min-height:40px;margin:0 0 12px}
            .bh-admin-card>a{font-size:13px;font-weight:600;color:#286044;text-decoration:none}
            .bh-admin-card>a:hover{text-decoration:underline}
            .bh-admin-coming{display:inline-block;font-size:11px;color:#806d50;background:#f7f1e7;padding:5px 8px;border-radius:20px}
            .bh-admin-card-planned{background:#fcfcfb}
            .bh-admin-card-planned .bh-admin-card-icon{background:#f3f1ec;color:#81745e}
            .bh-admin-card-limited .bh-admin-card-icon{background:#f1f3f8;color:#586b91}
            .bh-admin-card-check .bh-admin-card-icon{background:#f7f1e7;color:#8b7040}
            .bh-admin-technical{margin:24px 0 10px;padding:16px 18px;background:#f7f8f7;border:1px solid #e4e9e5;border-radius:12px}
            .bh-admin-technical summary{font-weight:600;cursor:pointer}
            .bh-admin-technical p{font-size:13px;color:#66736b}
            .bh-admin-note{font-size:12px;color:#718078;margin-top:18px}
            @media(max-width:1000px){.bh-admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:600px){.bh-admin-grid{grid-template-columns:1fr}.bh-admin-welcome{padding:22px}.bh-admin-action{width:100%;box-sizing:border-box}}
        ' );
    }
}
