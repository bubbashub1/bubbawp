<?php
/**
 * Read-only development roadmap in the Bubba Hub WordPress admin.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BH_Developments_Admin_3 {
    public static function register() {
        add_submenu_page(
            'bubba-hub-3-status',
            __( 'Developments', 'bubba-hub-manager' ),
            __( 'Developments', 'bubba-hub-manager' ),
            'manage_options',
            'bh3-developments',
            array( __CLASS__, 'render' )
        );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Administrator permission required.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }

        $phases = array(
            array( '4.1', 'Data Quality & Automation', 'Listing health, missing fields, duplicate detection, verification reminders and broken links.', 'Directory and data integrity' ),
            array( '4.2', 'Smart Booking & Timetables', 'Waiting lists, cancellation and space notifications, rescheduling and bulk timetable editing.', 'Booking, consent and notification QA' ),
            array( '4.3', 'Family Hub Plus', 'Planner Pro review, family recommendations, school and pregnancy reminders, personal calendar.', 'Family profiles, privacy and planner' ),
            array( '4.4', 'Leader Business Tools', 'Performance dashboard, onboarding, social tools, optional boosts and leader content.', 'Leader portal and membership permissions' ),
            array( '4.5', 'Communications Centre', 'Email delivery logs, notification preferences, templates and newsletter roundups.', 'FluentSMTP, verified recipients and consent' ),
            array( '4.6', 'Events & Expo Plus', 'Recurring events, ticketing enhancements, QR check-in, volunteers, sponsors and exhibitors.', 'Events templates and shared booking system' ),
            array( '4.7', 'Platform & Administration', 'Audit history, data exports, team roles, accessibility filters and reporting.', '3.0 migration and permissions' ),
        );
        echo '<div class="wrap bh3-developments">';
        echo '<h1>' . esc_html__( 'Developments', 'bubba-hub-manager' ) . '</h1>';
        echo '<p class="description">Planning dashboard only. No features are enabled or deployed from this screen.</p>';
        echo '<div class="notice notice-info inline"><p><strong>Our guiding principle:</strong> Does it save time, make family life easier, or help people connect? If it doesn\'t, it probably isn\'t a priority.</p></div>';
        echo '<h2>Bubba Hub 3.0 — Current delivery</h2>';
        echo '<p>Complete WordPress migration, legacy feature parity, security and staging testing across Directory, Family Hub, Leader Portal, Bookings and Support. Essential unfinished migration work stays in 3.0.</p>';
        echo '<h2>Bubba Hub 4.0 — Future roadmap</h2>';
        echo '<p>Less AI dependence, less administration for leaders, and a family portal to use, connect and be inspired. All phases below are <strong>planned</strong>, not delivered.</p>';
        echo '<style>.bh3-development-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;max-width:1250px}.bh3-development-card{background:#fff;border:1px solid #d6e0db;border-radius:12px;padding:20px;box-sizing:border-box}.bh3-development-card h3{margin:8px 0}.bh3-development-card p{line-height:1.5}.bh3-development-card small{color:#505c57}.bh3-development-tag{display:inline-block;background:#eef5f0;color:#215b43;border-radius:20px;padding:4px 10px;font-weight:600}</style>';
        echo '<div class="bh3-development-grid">';
        foreach ( $phases as $phase ) {
            echo '<section class="bh3-development-card">';
            echo '<span class="bh3-development-tag">' . esc_html( $phase[0] ) . ' · Planned</span>';
            echo '<h3>' . esc_html( $phase[1] ) . '</h3>';
            echo '<p>' . esc_html( $phase[2] ) . '</p>';
            echo '<small><strong>Depends on:</strong> ' . esc_html( $phase[3] ) . '</small>';
            echo '</section>';
        }
        echo '</div>';
        echo '<p style="margin-top:20px"><a class="button button-secondary" href="' . esc_url( 'https://github.com/bubbashub1/bubbawp/blob/main/docs/bubba-hub-4.0-roadmap.md' ) . '" target="_blank" rel="noopener noreferrer">View full roadmap on GitHub</a></p>';
        echo '<p class="description">This is a read-only snapshot. Update the roadmap in the repository and the plugin display together when plans change.</p>';
        echo '</div>';
    }
}
