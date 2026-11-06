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

    private static function readiness_areas() {
        return array(
            'directory' => 'Directory & Listings',
            'family' => 'Family Hub',
            'leader' => 'Leader Portal',
            'booking' => 'Bookings & Consent',
            'support' => 'Support & Guidance',
            'membership' => 'Membership',
            'communications' => 'Communications',
            'events' => 'Events & Expo',
            'claims' => 'Claims & Data Quality',
            'platform' => 'Platform & Developments',
        );
    }

    private static function sector_blueprints() {
        return array(
            'directory' => array(
                'Tables' => 'WP organisers, activities, venues, activity_venues, sessions; legacy bh_activities, bh_venues, bh_sessions',
                'Fields to verify' => 'Age months, category, session price, recurrence, postcode, accessibility, verified organiser ownership',
                'Forms' => 'Native activity wizard',
                'Shortcodes' => '[bubbahub_directory], [bubbahub_activity], [bh_admin_activity_wizard] (verify placement)',
                'Pages' => '/directory/ · /activity/{slug}/ · /organiser/{slug}/',
                'Next gate' => 'Compare staging DDL, verify permalink routing and canonical session model',
            ),
            'family' => array(
                'Tables' => 'bh_family_profiles, bh_family_preferences, bh_saved_activities, bh_planner, bh_family_reminders; legacy bh_children, bh_bumps',
                'Fields to verify' => 'Profile type, gender, birth/due date, user ownership, planner session/date, visited',
                'Forms' => 'Native family profile editor',
                'Shortcodes' => '[bubbahub_family_hub], [bubbahub_family_profiles], [bubbahub_family_favourites] (verify placement)',
                'Pages' => '/my-hub/ · profile/ · children/ · preferences/ · planner/ · favourites/',
                'Next gate' => 'Verify WP-to-legacy user mapping and profile migration before data writes',
            ),
            'leader' => array(
                'Tables' => 'WP organisers, venues, activities, sessions; legacy bh_leader_faqs, bh_leader_messages',
                'Fields to verify' => 'Verified owner, contact details, payout preference, session edit rights, venue link',
                'Forms' => 'Native listing/venue wizard; Form 6 for enquiries',
                'Shortcodes' => '[bh_leader_dashboard], [bh_leader_booking_dashboard], [bh_leader_payout_settings]',
                'Pages' => '/leader/ · about/ · new-activity/ · bookings/ · messages/ · venues/ · business/ · payment/',
                'Next gate' => 'Check leader capabilities, ownership and field mappings',
            ),
            'booking' => array(
                'Tables' => 'bh3_bookings, bh3_booking_attendees, bh3_booking_consents; legacy bh_booking_slots, bh_booking_reservations, bh_booking_consent_snapshots',
                'Fields to verify' => 'Booking vs payment status, occurrence, capacity, attendee identity, consent version, audit history',
                'Forms' => 'Fluent Forms 6 enquiry and 7 consent — export verification pending',
                'Shortcodes' => '[bh_parent_booking], [bh_leader_booking_dashboard], [bh_booking_readiness]',
                'Pages' => '/leader/bookings/ · /leader/payment/ · /legal/bookings/ · activity booking CTA',
                'Next gate' => 'Choose canonical booking writer and verify form JSON/consent snapshots',
            ),
            'support' => array(
                'Tables' => 'bh_ask_questions, bh_ask_private_contacts, bh_ask_replies, bh_ask_audit; Fluent Forms inbox',
                'Fields to verify' => 'Topic, visibility, moderation, verified replies, private contact, response history',
                'Forms' => 'Fluent Forms 4 Ask a Question and 5 Family Support',
                'Shortcodes' => '[bh_ask_question], [bh_support_hub], [bh_support_resources]',
                'Pages' => '/guidance/ · resources/ · blog/ · ask/',
                'Next gate' => 'Confirm form submissions and canonical public/private question records',
            ),
            'membership' => array(
                'Tables' => 'Simple Membership and WP users; existing membership plugin tables',
                'Fields to verify' => 'Family/Leader tier, access rights, registration and renewal status',
                'Forms' => 'Simple Membership registration',
                'Shortcodes' => '[bh_membership_registration] and membership plugin blocks',
                'Pages' => '/membership-join/ · /auth/',
                'Next gate' => 'Verify membership tier IDs and permissions on staging',
            ),
            'communications' => array(
                'Tables' => 'bh_newsletter_subscribers, bh_push_subscriptions, bh_push_notifications; FluentSMTP logs',
                'Fields to verify' => 'Opt-in version, frequency, unsubscribe, dedupe, delivery state',
                'Forms' => 'Native preferences; newsletter opt-in if required',
                'Shortcodes' => 'Public shortcode not confirmed',
                'Pages' => 'Admin communications · /my-hub/preferences/',
                'Next gate' => 'Review existing sender and delivery logs before adding tables',
            ),
            'events' => array(
                'Tables' => 'bh_expo_reservations, bh_expo_tickets, bh_expo_exhibitors, bh_expo_tables, bh_expo_booking_qrs, bh_expo_family_checkins',
                'Fields to verify' => 'Event key, timed entry, capacity, QR uniqueness, sponsors, check-ins',
                'Forms' => 'Visitor, exhibitor and sponsor — planned; IDs not assigned',
                'Shortcodes' => 'Event templates and shortcodes to be decided',
                'Pages' => '/events/ · /events/{event-slug}/ (planned)',
                'Next gate' => 'Audit legacy Expo schema before generalising to multiple events',
            ),
            'claims' => array(
                'Tables' => 'Existing listing ownership, moderation and audit records',
                'Fields to verify' => 'Claimant identity, proof, duplicate review, approval history',
                'Forms' => 'Native claim and venue submission (planned)',
                'Shortcodes' => 'To be decided after existing module audit',
                'Pages' => 'Admin listings and moderation · /leader/venues/',
                'Next gate' => 'Locate canonical claim and approval persistence',
            ),
            'platform' => array(
                'Tables' => 'WP options bh_development_tracker_statuses, bh3_development_statuses, bhs_development_readiness',
                'Fields to verify' => 'Feature ownership, dependencies, QA evidence and deployment sign-off',
                'Forms' => 'None',
                'Shortcodes' => 'None — WordPress admin only',
                'Pages' => '/wp-admin/admin.php?page=bh3-developments',
                'Next gate' => 'Verify all three status forms save/reload on staging',
            ),
        );
    }

    public static function save_legacy_features() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Administrator permission required.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_save_legacy_features' );
        $items = function_exists( 'bh_development_tracker_items' ) ? bh_development_tracker_items() : array();
        $allowed = array( 'Planned', 'In progress', 'Needs testing', 'Ready to deploy', 'Deployed', 'Blocked', 'Complete' );
        $saved = get_option( 'bh_development_tracker_statuses', array() );
        $saved = is_array( $saved ) ? $saved : array();
        $posted = isset( $_POST['bh_tracker_status'] ) && is_array( $_POST['bh_tracker_status'] ) ? wp_unslash( $_POST['bh_tracker_status'] ) : array();
        foreach ( $items as $key => $item ) {
            if ( isset( $posted[$key] ) && is_string( $posted[$key] ) && in_array( $posted[$key], $allowed, true ) ) {
                $saved[$key] = $posted[$key];
            }
        }
        update_option( 'bh_development_tracker_statuses', $saved, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-developments', 'legacy_updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function save_readiness() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Administrator permission required.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_save_development_readiness' );
        $allowed = array( 'not_assessed', 'draft', 'staging_validated', 'implemented', 'tested', 'ready_to_deploy', 'deployed', 'blocked' );
        $dimensions = array( 'sql', 'forms', 'shortcodes', 'pages', 'code', 'testing', 'deployment' );
        $existing = get_option( 'bhs_development_readiness', array() );
        $existing = is_array( $existing ) ? $existing : array();
        $posted = isset( $_POST['bhs_readiness'] ) && is_array( $_POST['bhs_readiness'] ) ? wp_unslash( $_POST['bhs_readiness'] ) : array();
        foreach ( self::readiness_areas() as $key => $label ) {
            foreach ( $dimensions as $dimension ) {
                if ( isset( $posted[$key][$dimension] ) && is_string( $posted[$key][$dimension] ) ) {
                    $value = sanitize_key( $posted[$key][$dimension] );
                    if ( in_array( $value, $allowed, true ) ) {
                        $existing[$key][$dimension] = $value;
                    }
                }
            }
        }
        update_option( 'bhs_development_readiness', $existing, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-developments', 'readiness_updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
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
        echo '<h2>Sector implementation blueprints</h2>';
        echo '<p>Read-only planning references. Shortcodes and pages listed here may require verification on staging; nothing below creates a table, form or page.</p>';
        echo '<style>.bh3-sector-details{max-width:1250px;margin:8px 0;background:#fff;border:1px solid #dcdcde;border-radius:6px}.bh3-sector-details summary{cursor:pointer;padding:12px 15px;font-weight:600}.bh3-sector-details[open] summary{border-bottom:1px solid #dcdcde}.bh3-sector-details dl{display:grid;grid-template-columns:minmax(110px,170px) minmax(0,1fr);gap:9px 16px;margin:15px}.bh3-sector-details dt{font-weight:600}.bh3-sector-details dd{margin:0;overflow-wrap:anywhere}@media(max-width:782px){.bh3-sector-details dl{grid-template-columns:1fr;gap:4px}.bh3-sector-details dd{margin-bottom:12px}}</style>';
        foreach ( self::readiness_areas() as $sector_key => $sector_title ) {
            $blueprint = self::sector_blueprints();
            if ( ! isset( $blueprint[$sector_key] ) ) { continue; }
            echo '<details class="bh3-sector-details"><summary>' . esc_html( $sector_title ) . '</summary><dl>';
            foreach ( $blueprint[$sector_key] as $field_label => $field_value ) {
                echo '<dt>' . esc_html( $field_label ) . '</dt><dd>' . esc_html( $field_value ) . '</dd>';
            }
            echo '</dl></details>';
        }
        echo '<h2>Sector readiness — SQL, forms, shortcodes, pages and delivery</h2>';
        echo '<p>Manually maintained planning statuses. These are not deployment or test results. Existing 3.0 tracker statuses and 4.0 phase statuses are preserved separately.</p>';
        if ( isset( $_GET['readiness_updated'] ) && '1' === $_GET['readiness_updated'] ) {
            echo '<div class="notice notice-success inline"><p>Sector readiness saved.</p></div>';
        }
        $readiness = get_option( 'bhs_development_readiness', array() );
        $readiness = is_array( $readiness ) ? $readiness : array();
        $readiness_labels = array(
            'not_assessed' => 'Not assessed',
            'draft' => 'Draft prepared',
            'staging_validated' => 'Staging validated',
            'implemented' => 'Implemented',
            'tested' => 'Tested',
            'ready_to_deploy' => 'Ready to deploy',
            'deployed' => 'Deployed',
            'blocked' => 'Blocked',
        );
        $readiness_dimensions = array( 'sql' => 'SQL', 'forms' => 'Forms', 'shortcodes' => 'Shortcodes', 'pages' => 'Pages', 'code' => 'Code', 'testing' => 'Testing', 'deployment' => 'Deployment' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_save_development_readiness">';
        wp_nonce_field( 'bh3_save_development_readiness' );
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Sector</th>';
        foreach ( $readiness_dimensions as $label ) { echo '<th>' . esc_html( $label ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( self::readiness_areas() as $key => $sector ) {
            echo '<tr><th scope="row">' . esc_html( $sector ) . '</th>';
            foreach ( $readiness_dimensions as $dimension => $label ) {
                $current = isset( $readiness[$key][$dimension] ) && isset( $readiness_labels[$readiness[$key][$dimension]] ) ? $readiness[$key][$dimension] : 'not_assessed';
                echo '<td><label class="screen-reader-text" for="bhs-' . esc_attr( $key . '-' . $dimension ) . '">' . esc_html( $sector . ' ' . $label ) . '</label><select id="bhs-' . esc_attr( $key . '-' . $dimension ) . '" name="bhs_readiness[' . esc_attr( $key ) . '][' . esc_attr( $dimension ) . ']">';
                foreach ( $readiness_labels as $value => $option_label ) {
                    echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
                }
                echo '</select></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div><p><button class="button button-primary" type="submit">Save sector readiness</button></p></form>';
        $legacy_items = function_exists( 'bh_development_tracker_items' ) ? bh_development_tracker_items() : array();
        $legacy_saved = get_option( 'bh_development_tracker_statuses', array() );
        $legacy_saved = is_array( $legacy_saved ) ? $legacy_saved : array();
        if ( isset( $_GET['legacy_updated'] ) && '1' === $_GET['legacy_updated'] ) {
            echo '<div class="notice notice-success inline"><p>3.0 feature statuses saved.</p></div>';
        }
        $legacy_allowed = array( 'Planned', 'In progress', 'Needs testing', 'Ready to deploy', 'Deployed', 'Blocked', 'Complete' );
        echo '<details><summary><strong>Existing 3.0 feature tracker (' . esc_html( count( $legacy_items ) ) . ' items) — editable and preserved</strong></summary>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_save_legacy_features">';
        wp_nonce_field( 'bh3_save_legacy_features' );
        echo '<table class="widefat striped"><thead><tr><th>Sector</th><th>Feature</th><th>Status</th></tr></thead><tbody>';
        foreach ( $legacy_items as $feature_key => $feature ) {
            $feature_status = isset( $legacy_saved[$feature_key] ) && in_array( $legacy_saved[$feature_key], $legacy_allowed, true ) ? $legacy_saved[$feature_key] : $feature[2];
            echo '<tr><td>' . esc_html( $feature[0] ) . '</td><td>' . esc_html( $feature[1] ) . '</td><td><label class="screen-reader-text" for="bh-legacy-' . esc_attr( $feature_key ) . '">Status for ' . esc_html( $feature[1] ) . '</label><select id="bh-legacy-' . esc_attr( $feature_key ) . '" name="bh_tracker_status[' . esc_attr( $feature_key ) . ']">';
            foreach ( $legacy_allowed as $option ) {
                echo '<option value="' . esc_attr( $option ) . '"' . selected( $feature_status, $option, false ) . '>' . esc_html( $option ) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table><p><button type="submit" class="button button-secondary">Save 3.0 feature statuses</button></p></form></details>';
        echo '<p><a href="' . esc_url( 'https://github.com/bubbashub1/bubbawp/blob/audit/foundation-contracts-2026-11-06/docs/data-foundation/DEVELOPMENT_BLUEPRINT.md' ) . '" target="_blank" rel="noopener noreferrer">Read the full development blueprint (SQL, forms, shortcodes and page hierarchy)</a></p>';
        echo '<h2>SQL &amp; forms development register</h2>';
        echo '<p>Source-code assessment only. Reuse and extension proposals are not approved migrations. Confirm staging schemas and Fluent Forms exports before creating or changing tables.</p>';
        $data_register = array(
            array( 'Listings &amp; Directory', 'Reuse / extend', 'Legacy bh_organisers, bh_activities, bh_venues, bh_sessions; native organisers, activities, venues, activity_venues, sessions', 'Preserve organiser phone/status; map age, media and shared venues', 'Native listing wizard', '3.0' ),
            array( 'Family Hub', 'Reuse / extend', 'Legacy bh_children, bh_bumps, bh_user_preferences; native bh_family_profiles, bh_family_preferences, bh_saved_activities, bh_planner, bh_family_reminders', 'Verified identity mapping; typed child/bump profile provenance; reminder rules', 'Native family profile fields', '3.0' ),
            array( 'Bookings &amp; Consent', 'Reuse / extend', 'Legacy bh_booking_sources, bh_booking_slots, bh_booking_reservations, bh_consent_profiles, bh_booking_consent_snapshots; native bh3_bookings, bh3_booking_attendees, bh3_booking_consents', 'Separate booking/enrolment status from payment status; occurrence capacity, immutable consent snapshot', 'Form 6 enquiry; Form 7 consent (verify actual field mapping)', '3.0' ),
            array( 'Leader Portal', 'Reuse / extend', 'Organisers, activities, sessions, venues, legacy bh_leader_faqs, bh_leader_expertise, bh_leader_messages', 'Verified ownership, payout settings, leader communications', 'Native dashboard and timetable editor', '3.0' ),
            array( 'Support &amp; Guidance', 'Reuse / reconcile', 'Legacy bh_guidance_topics, bh_support_questions, bh_support_answers; native bh_ask_questions, bh_ask_replies and Fluent Forms submissions', 'Choose one authoritative Q&amp;A record and private contact storage', 'Form 4 Ask Question; Form 5 Family Support', '3.0' ),
            array( 'Communications', 'Reuse / possible extension', 'Legacy bh_newsletter_subscribers, bh_push_subscriptions, bh_push_notifications and newsletter preferences', 'Consent evidence, delivery idempotency and optional message delivery log', 'Native preferences; optional opt-in if not already covered', '4.5' ),
            array( 'Events &amp; Expo', 'Reuse / generalise', 'Legacy bh_expo_reservations, bh_expo_tickets, bh_expo_exhibitors, bh_expo_tables, bh_expo_booking_qrs, bh_expo_family_checkins, bh_expo_raffle_prizes', 'Event identity, multi-event scoping, sponsors and check-in workflow; inspect legacy tables first', 'Visitor registration, exhibitor application, sponsor enquiry (assess native vs Fluent Forms)', '4.6' ),
            array( 'Claims &amp; Approvals', 'Assess extension', 'Existing organisers, venues and moderation modules; legacy approval code', 'Ownership verification, review decisions, audit history if not already stored', 'Native claim and venue submission workflow', '3.0 / 4.7' ),
            array( 'Developments', 'No new SQL', 'Existing WP options bh_development_tracker_statuses and bh3_development_statuses', 'Unify screens without losing either saved status set', 'Native WP admin only', '3.0' ),
        );
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Area</th><th>Decision</th><th>Reusable SQL</th><th>Extra data required</th><th>Forms</th><th>Phase</th></tr></thead><tbody>';
        foreach ( $data_register as $entry ) {
            echo '<tr>';
            foreach ( $entry as $index => $value ) {
                echo '<td>' . ( 0 === $index ? '<strong>' : '' ) . esc_html( $value ) . ( 0 === $index ? '</strong>' : '' ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="description">Source: legacy SQL schema and Expo migrations, native schema/family setup, and foundation audit. No database changes are performed by this register.</p>';
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
