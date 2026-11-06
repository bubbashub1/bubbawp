<?php
/**
 * Safe bootstrap: no migrations, routes, external calls or data writes.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class BH_Manager_3 {
    private static $booted = false;

    /** Explicitly disabled until isolated staging compatibility QA. */
    public const FEATURE_DIRECTORY = false;
    public const FEATURE_LEADER = false;
    public const FEATURE_FAMILY = false;
    public const FEATURE_BOOKINGS = false;

    public static function boot() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        // Support is opt-in until compatibility and staging checks pass.
        // Set BH3_ENABLE_SUPPORT to true in staging wp-config.php to preview.
        if ( defined( 'BH3_ENABLE_SUPPORT' ) && BH3_ENABLE_SUPPORT === true
            && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk' ) {
            foreach ( array(
                'support-resources.php',
                'support-articles.php',
                'support-hub.php',
                'support-website-help.php',
                'support-ask-question.php',
                'support-article-moderation.php',
                'support-fluentforms-inbox.php',
                'support-admin-dashboard.php',
            ) as $support_module ) {
                require_once BUBBA_HUB_MANAGER_PATH . 'modules/Support/' . $support_module;
            }
        }
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Membership/class-bh-membership-registration.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-workstream-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-developments-admin.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-field-manager.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-builder.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-audit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-preview.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-detail.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-modules.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activities.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-schema.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-booking-lifecycle.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-booking-consent-contract.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-booking-form7-binding.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-booking-form7-owner.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-booking-turnstile.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-family-db-setup.php';
        BH_Family_DB_Setup_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-parity.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-create.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-edit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-organisers.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-sessions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-term-dates-admin.php';
        BH_Term_Dates_Admin_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-my-listings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-dashboard.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-layout.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-frontend.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-ask-support-optin.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-ask-support-inbox.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-ask-support-storage.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-ask-support-moderation.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-portal-access.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-edit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-sessions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-ui.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-identity.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-read-model.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-profile-editor.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-native-profiles.php';
        BH_Family_Native_Profiles_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-native-favourites.php';
        BH_Family_Native_Favourites_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-actions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-preferences.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-recommendations.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-account.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-notifications.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-privacy.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-schema-audit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-hub.php';
        BH_Family_Preferences_3::register();
        BH_Family_Settings_3::register();
        BH_Family_Account_3::register();
        BH_Family_Notifications_3::register();
        BH_Family_Privacy_3::register();
        BH_Family_Schema_Audit_3::register();
        BH_Family_Profile_Editor_3::register();
        BH_Family_Actions_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-frontend-editor.php';
        BH_Leader_Frontend_Editor_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-frontend-sessions.php';
        BH_Leader_Frontend_Sessions_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-frontend-venues.php';
        BH_Leader_Frontend_Venues_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-frontend-business.php';
        BH_Leader_Frontend_Business_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-storefront.php';
        BH_Storefront_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-business-directory.php';
        BH_Business_Directory_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-bookings.php';
        BH_Leader_Bookings_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-support.php';
        BH_Leader_Support_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-statistics.php';
        BH_Leader_Statistics_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-account.php';
        BH_Leader_Account_3::register();
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-marketplace-settings.php';
        BH_Marketplace_Settings_3::register();
        BH_Leader_Layout_3::register();
        BH_Leader_Frontend_3::register();
        BH_Portal_Access_3::register();
        BH_Family_Hub_3::register(); // Preview shortcode only; FEATURE_FAMILY stays disabled.
        BH_Directory_Preview_3::register();
        BH_Directory_3::register();
        BH_Activity_Detail_3::register();
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_workstreams_page' ), 30 );
        add_action( 'admin_menu', array( __CLASS__, 'register_booking_test_generator_page' ), 30 );
        add_action( 'admin_menu', array( __CLASS__, 'register_marketplace_commission_page' ), 30 );
        add_action( 'admin_menu', array( 'BH_Developments_Admin_3', 'register' ), 30 );
        add_action( 'admin_post_bh3_save_development_statuses', array( 'BH_Developments_Admin_3', 'save_statuses' ) );
        add_action( 'admin_post_bh3_save_development_readiness', array( 'BH_Developments_Admin_3', 'save_readiness' ) );
        add_action( 'admin_post_bh3_save_legacy_features', array( 'BH_Developments_Admin_3', 'save_legacy_features' ) );
        // Keep legacy staging screens reachable by their existing URLs, but not as duplicate top-level menus.
        add_action( 'admin_menu', array( __CLASS__, 'consolidate_leader_admin_menus' ), 999 );
        add_action( 'admin_menu', array( 'BH_Workstream_Settings_3', 'register' ), 30 );
        BH_Activity_Parity_3::register();
        BH_Field_Manager_3::register();
        BH_Directory_Builder_3::register();
        add_action( 'admin_menu', array( 'BH_Settings_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Directory_Audit_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Modules_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activities_3', 'register' ) );
        // Register the front-end shortcode on every request; admin_menu only runs in wp-admin.
        add_shortcode( 'bh_admin_activity_wizard', array( 'BH_Activity_Create_3', 'render_frontend' ) );
        add_action( 'admin_menu', array( 'BH_Activity_Create_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activity_Edit_3', 'register' ) );
        // admin-post.php does not fire admin_menu; register form handlers during admin_init.
        add_action( 'admin_init', static function () {
            add_action( 'admin_post_bh3_update_activity', array( 'BH_Activity_Edit_3', 'save' ) );
            add_action( 'admin_post_bh3_change_activity_status', array( 'BH_Activity_Edit_3', 'change_status' ) );
        } );
        add_action( 'admin_menu', array( 'BH_Venues_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activity_Venues_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Organisers_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Sessions_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_My_Listings_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Leader_Dashboard_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Leader_Edit_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Leader_Sessions_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Leader_Venues_3', 'register' ) );
        add_action( 'admin_enqueue_scripts', array( 'BH_Leader_UI_3', 'enqueue' ) );
        add_action( 'admin_post_bh3_leader_attach_venue', array( 'BH_Leader_Venues_3', 'attach' ) );
        add_action( 'admin_post_bh3_leader_detach_venue', array( 'BH_Leader_Venues_3', 'detach' ) );
        add_action( 'admin_post_bh3_leader_save_session', array( 'BH_Leader_Sessions_3', 'save' ) );
        add_action( 'admin_post_bh3_leader_delete_session', array( 'BH_Leader_Sessions_3', 'remove' ) );
        add_action( 'admin_post_bh3_leader_save_activity', array( 'BH_Leader_Edit_3', 'save' ) );
        // admin-post.php does not fire admin_menu; register handlers at bootstrap.
        add_action( 'admin_post_bh3_update_activity', array( 'BH_Activity_Edit_3', 'save' ) );
        add_action( 'admin_post_bh3_save_venue', array( 'BH_Venues_3', 'save' ) );
        add_action( 'admin_post_bh3_attach_venue', array( 'BH_Activity_Venues_3', 'attach' ) );
        add_action( 'admin_post_bh3_detach_venue', array( 'BH_Activity_Venues_3', 'detach' ) );
        add_action( 'admin_post_bh3_save_organiser', array( 'BH_Organisers_3', 'save' ) );
        add_action( 'admin_post_bh3_save_session', array( 'BH_Sessions_3', 'save' ) );
        add_action( 'admin_post_bh3_delete_session', array( 'BH_Sessions_3', 'remove' ) );
        add_action( 'admin_init', array( 'BH_Activity_Create_3', 'register_save' ) );
        add_action( 'admin_post_bh3_save_settings', array( 'BH_Settings_3', 'save' ) );
        add_action( 'admin_post_bh3_family_display_save', array( 'BH_Workstream_Settings_3', 'family_display_save' ) );
        add_action( 'admin_post_bh3_install_schema', array( __CLASS__, 'install_schema' ) );
        add_action( 'admin_post_bh3_install_missing_booking_tables', array( __CLASS__, 'install_missing_booking_tables' ) );
    }

    private static function is_staging() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }

    /** Additive booking-only migration; requires confirmed staging backup. */
    public static function install_missing_booking_tables() {
        if ( ! current_user_can( 'manage_options' ) || ! self::is_staging() ) {
            wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_install_missing_booking_tables' );
        $confirm = isset( $_POST['bh3_booking_confirm'] ) && is_string( $_POST['bh3_booking_confirm'] )
            ? sanitize_text_field( wp_unslash( $_POST['bh3_booking_confirm'] ) ) : '';
        $backup = isset( $_POST['bh3_backup_confirmed'] ) && $_POST['bh3_backup_confirmed'] === '1';
        if ( $confirm !== 'CREATE MISSING' || ! $backup ) {
            wp_die( 'Confirm the completed staging backup and type CREATE MISSING.', '', array( 'response' => 400 ) );
        }
        $result = BH_Schema_3::install_missing_booking_tables();
        set_transient( 'bh3_booking_schema_result_' . get_current_user_id(), is_wp_error( $result )
            ? $result->get_error_message() : 'Booking table check completed; created ' . count( $result ) . ' missing table(s).', 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=bubba-hub-3-status' ) );
        exit;
    }

    public static function install_schema() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_install_schema' );
        if ( ! self::is_staging() ) { wp_die( 'Only staging may install tables.', '', array( 'response' => 403 ) ); }
        if ( ! isset( $_POST['bh3_confirm'] ) || sanitize_text_field( wp_unslash( $_POST['bh3_confirm'] ) ) !== 'INSTALL' ) {
            wp_die( 'Type INSTALL to confirm.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        if ( get_option( 'bh3_schema_version' ) !== BH_Schema_3::VERSION ) {
            foreach ( BH_Schema_3::table_names() as $table ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
                    wp_die( 'A target table already exists. No changes were made; review its ownership before installing.', '', array( 'response' => 409 ) );
                }
            }
        }
        $result = BH_Schema_3::install();
        wp_safe_redirect( add_query_arg( 'bh3_schema_result', is_wp_error( $result ) ? 'error' : 'success', admin_url( 'admin.php?page=bubba-hub-3-status' ) ) );
        exit;
    }

    public static function register_admin_page() {
        add_menu_page(
            __( 'Bubba Hub Status', 'bubba-hub-manager' ),
            __( 'Bubba Hub', 'bubba-hub-manager' ),
            'manage_options',
            'bubba-hub-3-status',
            array( __CLASS__, 'render_status' ),
            'dashicons-location-alt',
            26
        );
    }


    /** Hide legacy leader navigation only; do not unregister pages, callbacks, or permissions. */
    public static function consolidate_leader_admin_menus() {
        remove_menu_page( 'bh3-my-listings' );
        remove_menu_page( 'bh3-leader-dashboard' );
    }

    /** Register marketplace settings after the Bubba Hub parent admin menu exists. */
    public static function register_marketplace_commission_page() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! class_exists( 'BH_Marketplace_Settings_3' ) ) {
            return;
        }
        add_submenu_page(
            'bubba-hub-3-status',
            'Marketplace Commission',
            'Marketplace Commission',
            'manage_options',
            'bh3-marketplace',
            array( 'BH_Marketplace_Settings_3', 'admin_form' )
        );
    }

    /** Register the staging QA screen independently of marketplace feature boot guards. */
    public static function register_booking_test_generator_page() {
        if ( ! class_exists( 'BH_Marketplace_Settings_3' ) ) { return; }
        add_submenu_page(
            'bubba-hub-3-status',
            'Booking Test Generator',
            'Booking Test Generator',
            'manage_options',
            'bh3-booking-test-generator',
            array( 'BH_Marketplace_Settings_3', 'test_booking_page' )
        );
    }

    /** Read-only navigation and implementation inventory; no feature activation. */
    public static function register_workstreams_page() {
        add_submenu_page(
            'bubba-hub-3-status',
            'Workstreams & readiness',
            'Workstreams',
            'manage_options',
            'bh3-workstreams',
            array( __CLASS__, 'render_workstreams_page' )
        );
    }

    public static function render_workstreams_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Administrator permission required.', '', array( 'response' => 403 ) );
        }
        $items = array(
            array( 'Directory & Listings', 'Admin screens available', 'Directory Builder, fields, activities, venues and organisers. Test saves on staging.' ),
            array( 'Family Hub & Planner', 'Staging preview only', 'Family shortcode and adapters exist; private actions require explicit staging flags and verified identity.' ),
            array( 'Leader Portal', 'Under staging QA', 'Editing, venues and sessions code exists; verify permissions, ownership and forms before rollout.' ),
            array( 'Support & Resources', 'Not yet integrated', 'Resource and support administration remains on the migration roadmap.' ),
            array( 'Events & Bookings', 'Not yet integrated', 'Do not assume the legacy booking or Expo system has been migrated.' ),
            array( 'Newsletters & Emails', 'Not yet integrated', 'Mail templates, logs and opt-in management require implementation and QA.' ),
        );
        echo '<div class="wrap"><h1>Workstreams &amp; readiness</h1>';
        echo '<p>Read-only implementation overview. A workstream listed here is not necessarily enabled or deployed. No feature switches or data are changed by this screen.</p>';
        echo '<style>.bh3-workstreams{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px;max-width:1100px}.bh3-workstreams article{background:#fff;border:1px solid #d6e0db;border-radius:12px;padding:20px;box-sizing:border-box}.bh3-workstreams h2{margin:0 0 10px;font-size:18px}.bh3-workstreams strong{color:#176047}.bh3-workstreams p{line-height:1.5}</style>';
        echo '<div class="bh3-workstreams">';
        foreach ( $items as $item ) {
            echo '<article><h2>' . esc_html( $item[0] ) . '</h2><strong>' . esc_html( $item[1] ) . '</strong><p>' . esc_html( $item[2] ) . '</p></article>';
        }
        echo '</div><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=bubba-hub-3-status' ) ) . '">View system status</a></p></div>';
    }

    public static function render_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'bubba-hub-manager' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Bubba Hub Manager 3.0', 'bubba-hub-manager' ) . '</h1>';
        echo '<p>' . esc_html__( 'Foundation installed. All business modules remain disabled pending like-for-like audit and staging tests.', 'bubba-hub-manager' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Version:', 'bubba-hub-manager' ) . '</strong> ' . esc_html( BUBBA_HUB_MANAGER_VERSION ) . '</p>';
        BH_Activity_Parity_3::render_control();
        echo '<h2>Fresh WordPress database schema</h2>';
        echo '<p>The new tables use the configured WordPress database prefix (normally wp_). No tables are created automatically and existing legacy tables are untouched.</p>';
        echo '<ul>';
        foreach ( BH_Schema_3::table_names() as $table ) { echo '<li><code>' . esc_html( $table ) . '</code></li>'; }
        echo '</ul><p>Installed schema version: <strong>' . esc_html( get_option( 'bh3_schema_version', 'Not installed' ) ) . '</strong></p>';
        if ( isset( $_GET['bh3_schema_result'] ) ) {
            $result = sanitize_key( wp_unslash( $_GET['bh3_schema_result'] ) );
            echo '<div class="notice ' . ( $result === 'success' ? 'notice-success' : 'notice-error' ) . '"><p>' .
                esc_html( $result === 'success' ? 'Schema installation verified.' : 'Schema installation failed. Check database logs.' ) . '</p></div>';
        }
        if ( self::is_staging() ) {
            echo '<p>Back up staging first. This creates empty tables and does not import legacy records.</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bh3_install_schema">';
            wp_nonce_field( 'bh3_install_schema' );
            echo '<label for="bh3_confirm">Type INSTALL: </label><input id="bh3_confirm" name="bh3_confirm" required autocomplete="off">';
            echo ' <button class="button button-primary" type="submit">Install tables on staging</button></form>';
        } else {
            echo '<p>Installation is disabled outside staging.bubbahub.co.uk.</p>';
        }
        if ( self::is_staging() ) {
            echo '<h2>Booking tables: safe additive upgrade</h2>';
            echo '<p>This checks only booking-related tables. Existing tables are never altered; the installed schema version is not changed.</p>';
            echo '<ul>';
            foreach ( BH_Schema_3::booking_table_audit() as $item ) {
                echo '<li><code>' . esc_html( $item['table'] ) . '</code>: ' . esc_html( $item['exists'] ? 'Exists — will be preserved' : 'Missing — can be created' ) . '</li>';
            }
            echo '</ul>';
            echo '<h3>Booking table structure (read-only)</h3><ul>';
            foreach ( BH_Schema_3::booking_structure_audit() as $item ) {
                $missing = array_merge(
                    array_map( function ( $name ) { return 'column: ' . $name; }, $item['missing_columns'] ),
                    array_map( function ( $name ) { return 'index: ' . $name; }, $item['missing_indexes'] )
                );
                echo '<li><code>' . esc_html( $item['table'] ) . '</code>: ';
                echo esc_html( ! $item['exists'] ? 'Table missing' : ( $missing ? 'Review needed — ' . implode( ', ', $missing ) : 'Expected columns and indexes present' ) );
                echo '</li>';
            }
            echo '</ul>';
            $message = get_transient( 'bh3_booking_schema_result_' . get_current_user_id() );
            if ( $message ) {
                echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
                delete_transient( 'bh3_booking_schema_result_' . get_current_user_id() );
            }
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bh3_install_missing_booking_tables">';
            wp_nonce_field( 'bh3_install_missing_booking_tables' );
            echo '<p><label><input type="checkbox" name="bh3_backup_confirmed" value="1" required> I have completed and verified a staging database backup.</label></p>';
            echo '<p><label>Type CREATE MISSING: <input name="bh3_booking_confirm" required autocomplete="off"></label></p>';
            echo '<p><button type="submit" class="button button-secondary">Create missing booking tables only</button></p>';
            echo '</form>';
        }
        echo '<h2>' . esc_html__( 'Feature readiness', 'bubba-hub-manager' ) . '</h2>';
        echo '<ul>';
        foreach ( array( 'Directory' => self::FEATURE_DIRECTORY, 'Leader portal' => self::FEATURE_LEADER, 'My Hub' => self::FEATURE_FAMILY, 'Bookings' => self::FEATURE_BOOKINGS ) as $label => $enabled ) {
            echo '<li>' . esc_html( $label ) . ': ' . esc_html( $enabled ? __( 'Enabled', 'bubba-hub-manager' ) : __( 'Disabled — awaiting parity tests', 'bubba-hub-manager' ) ) . '</li>';
        }
        echo '</ul>';
        echo '<p>' . esc_html__( 'No database tables, user roles, rewrite rules, REST endpoints or scheduled tasks are created automatically by this version.', 'bubba-hub-manager' ) . '</p></div>';
    }
}
