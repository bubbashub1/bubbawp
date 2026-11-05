<?php
/**
 * Development roadmap and lightweight status tracker in the Bubba Hub WordPress admin.
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

    public static function save_statuses() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Administrator permission required.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_save_developments' );
        $allowed = array( 'planned', 'in_development', 'testing', 'complete' );
        $statuses = array();
        $posted = isset( $_POST['bh3_statuses'] ) && is_array( $_POST['bh3_statuses'] )
            ? wp_unslash( $_POST['bh3_statuses'] ) : array();
        foreach ( array( '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7' ) as $phase ) {
            $value = isset( $posted[ $phase ] ) && is_string( $posted[ $phase ] ) ? sanitize_key( $posted[ $phase ] ) : 'planned';
            $statuses[ $phase ] = in_array( $value, $allowed, true ) ? $value : 'planned';
        }
        update_option( 'bh3_development_statuses', $statuses, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-developments', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
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
        $statuses = get_option( 'bh3_development_statuses', array() );
        $statuses = is_array( $statuses ) ? $statuses : array();
        $labels = array(
            'planned' => 'Planned',
            'in_development' => 'In Development',
            'testing' => 'Testing',
            'complete' => 'Complete',
        );
        echo '<div class="wrap bh3-developments">';
        echo '<h1>' . esc_html__( 'Developments', 'bubba-hub-manager' ) . '</h1>';
        echo '<p class="description">Planning and progress dashboard. Changing a status does not deploy or enable a feature.</p>';
        echo '<div class="notice notice-info inline"><p><strong>Our guiding principle:</strong> Does it save time, make family life easier, or help people connect? If it doesn\'t, it probably isn\'t a priority.</p></div>';
        echo '<h2>Bubba Hub 3.0 — Current delivery</h2>';
        echo '<p>Complete WordPress migration, legacy feature parity, security and staging testing across Directory, Family Hub, Leader Portal, Bookings and Support. Essential unfinished migration work stays in 3.0.</p>';
        echo '<h2>Bubba Hub 4.0 — Future roadmap</h2>';
        echo '<p>Less AI dependence, less administration for leaders, and a family portal to use, connect and be inspired. Track the progress of each phase below. Statuses are manually updated, not linked to deployments.</p>';
        echo '<style>.bh3-development-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;max-width:1250px}.bh3-development-card{background:#fff;border:1px solid #d6e0db;border-radius:12px;padding:20px;box-sizing:border-box}.bh3-development-card h3{margin:8px 0}.bh3-development-card p{line-height:1.5}.bh3-development-card small{color:#505c57}.bh3-development-tag{display:inline-block;background:#eef5f0;color:#215b43;border-radius:20px;padding:4px 10px;font-weight:600}</style>';
        if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Development statuses saved.</p></div>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_save_development_statuses">';
        wp_nonce_field( 'bh3_save_developments' );
        echo '<div class="bh3-development-grid">';
        foreach ( $phases as $phase ) {
            echo '<section class="bh3-development-card">';
            $current = isset( $statuses[ $phase[0] ] ) && isset( $labels[ $statuses[ $phase[0] ] ] ) ? $statuses[ $phase[0] ] : 'planned';
            echo '<span class="bh3-development-tag">' . esc_html( $phase[0] ) . ' · ' . esc_html( $labels[ $current ] ) . '</span>';
            echo '<h3>' . esc_html( $phase[1] ) . '</h3>';
            echo '<p>' . esc_html( $phase[2] ) . '</p>';
            echo '<small><strong>Depends on:</strong> ' . esc_html( $phase[3] ) . '</small>';
            echo '<p><label for="bh3-status-' . esc_attr( $phase[0] ) . '"><strong>Progress</strong></label><br>';
            echo '<select id="bh3-status-' . esc_attr( $phase[0] ) . '" name="bh3_statuses[' . esc_attr( $phase[0] ) . ']">';
            foreach ( $labels as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></p>';
            echo '</section>';
        }
        echo '</div>';
        echo '<p><button type="submit" class="button button-primary">Save progress</button></p></form>';
        echo '<p style="margin-top:20px"><a class="button button-secondary" href="' . esc_url( 'https://github.com/bubbashub1/bubbawp/blob/main/docs/bubba-hub-4.0-roadmap.md' ) . '" target="_blank" rel="noopener noreferrer">View full roadmap on GitHub</a></p>';
        echo '<p class="description">The roadmap descriptions are a snapshot from GitHub; progress is saved locally in WordPress.</p>';
        echo '</div>';
    }
}
