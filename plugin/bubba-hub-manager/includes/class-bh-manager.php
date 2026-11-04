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
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-workstream-settings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-field-manager.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-builder.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-audit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory-preview.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-directory.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-detail.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-modules.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activities.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-schema.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-parity.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-create.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-edit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-activity-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-organisers.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-sessions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-my-listings.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-dashboard.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-edit.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-sessions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-venues.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-leader-ui.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-identity.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-read-model.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-profile-editor.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-actions.php';
        require_once BUBBA_HUB_MANAGER_PATH . 'modules/Families/class-bh-family-hub.php';
        BH_Family_Profile_Editor_3::register();
        BH_Family_Actions_3::register();
        BH_Family_Hub_3::register(); // Preview shortcode only; FEATURE_FAMILY stays disabled.
        BH_Directory_Preview_3::register();
        BH_Directory_3::register();
        BH_Activity_Detail_3::register();
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_workstreams_page' ), 30 );
        add_action( 'admin_menu', array( 'BH_Workstream_Settings_3', 'register' ), 30 );
        BH_Activity_Parity_3::register();
        BH_Field_Manager_3::register();
        BH_Directory_Builder_3::register();
        add_action( 'admin_menu', array( 'BH_Settings_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Directory_Audit_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Modules_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activities_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activity_Create_3', 'register' ) );
        add_action( 'admin_menu', array( 'BH_Activity_Edit_3', 'register' ) );
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
    }

    private static function is_staging() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
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
        echo '<h2>' . esc_html__( 'Feature readiness', 'bubba-hub-manager' ) . '</h2>';
        echo '<ul>';
        foreach ( array( 'Directory' => self::FEATURE_DIRECTORY, 'Leader portal' => self::FEATURE_LEADER, 'My Hub' => self::FEATURE_FAMILY, 'Bookings' => self::FEATURE_BOOKINGS ) as $label => $enabled ) {
            echo '<li>' . esc_html( $label ) . ': ' . esc_html( $enabled ? __( 'Enabled', 'bubba-hub-manager' ) : __( 'Disabled — awaiting parity tests', 'bubba-hub-manager' ) ) . '</li>';
        }
        echo '</ul>';
        echo '<p>' . esc_html__( 'No database tables, user roles, rewrite rules, REST endpoints or scheduled tasks are created automatically by this version.', 'bubba-hub-manager' ) . '</p></div>';
    }
}
