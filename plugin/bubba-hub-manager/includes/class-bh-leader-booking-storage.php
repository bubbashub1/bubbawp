<?php
/**
 * Booking storage contract, version 1.
 *
 * No hooks, automatic migrations, public endpoints, or writes on inclusion.
 * Installation must be invoked explicitly by a staging administrator after backup.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Leader_Booking_Storage_3 {
    public const VERSION = '1.0.0';

    public static function register() {
        add_action( 'admin_post_bh3_booking_install_schema', array( __CLASS__, 'install_from_admin' ) );
    }

    public static function install_from_admin() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Administrator permission required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_booking_install_schema' );
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            wp_die( 'Booking schema installation is staging-only.', '', array( 'response' => 403 ) );
        }
        if ( sanitize_text_field( wp_unslash( $_POST['booking_confirm'] ?? '' ) ) !== 'INSTALL' ) {
            wp_die( 'Type INSTALL to confirm.', '', array( 'response' => 400 ) );
        }
        $result = self::install();
        $url = add_query_arg( array(
            'booking_tab' => 'setup',
            'booking_schema_notice' => is_wp_error( $result ) ? 'failed' : 'installed',
        ), home_url( '/leader/bookings/' ) );
        wp_safe_redirect( $url );
        exit;
    }

    public static function tables() {
        global $wpdb;
        return array(
            'reservations' => $wpdb->prefix . 'bh3_booking_reservations',
            'events'       => $wpdb->prefix . 'bh3_booking_events',
        );
    }

    /**
     * Staging-only opt-in schema creation. Never called during plugin bootstrap.
     * Each reservation refers to an existing activity and optional session.
     * A snapshot of session start avoids losing historic booking context.
     */
    public static function install() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'bh_booking_permission', 'Administrator permission required.' );
        }
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return new WP_Error( 'bh_booking_environment', 'Booking schema installation is staging-only.' );
        }
        global $wpdb;
        $tables = self::tables();
        $collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$tables['reservations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organiser_id bigint(20) unsigned NOT NULL,
            activity_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned DEFAULT NULL,
            session_starts_at datetime DEFAULT NULL,
            family_user_id bigint(20) unsigned DEFAULT NULL,
            parent_first_name varchar(100) NOT NULL DEFAULT '',
            parent_last_name varchar(100) NOT NULL DEFAULT '',
            parent_email varchar(190) NOT NULL DEFAULT '',
            parent_phone varchar(50) NOT NULL DEFAULT '',
            child_first_name varchar(100) NOT NULL DEFAULT '',
            child_age_months smallint(5) unsigned DEFAULT NULL,
            status varchar(24) NOT NULL DEFAULT 'pending',
            source varchar(24) NOT NULL DEFAULT 'manual',
            notes text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            confirmed_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY organiser_status (organiser_id,status),
            KEY activity_session (activity_id,session_id),
            KEY session_start (session_starts_at),
            KEY family_user (family_user_id)
        ) {$collate};
        CREATE TABLE {$tables['events']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reservation_id bigint(20) unsigned NOT NULL,
            organiser_id bigint(20) unsigned NOT NULL,
            actor_user_id bigint(20) unsigned DEFAULT NULL,
            event_type varchar(48) NOT NULL,
            event_detail text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY reservation_time (reservation_id,created_at),
            KEY organiser_time (organiser_id,created_at)
        ) {$collate};";
        dbDelta( $sql );
        foreach ( $tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            if ( $exists !== $table ) {
                return new WP_Error( 'bh_booking_schema', 'Booking table creation could not be verified.' );
            }
        }
        // Verify the columns required by the leader's confirm/cancel and audit actions.
        $required = array(
            'reservations' => array( 'id', 'organiser_id', 'activity_id', 'session_id', 'session_starts_at', 'status', 'updated_at', 'confirmed_at', 'cancelled_at' ),
            'events' => array( 'reservation_id', 'organiser_id', 'actor_user_id', 'event_type', 'event_detail', 'created_at' ),
        );
        foreach ( $required as $kind => $columns ) {
            $actual = $wpdb->get_col( "SHOW COLUMNS FROM {$tables[$kind]}", 0 );
            if ( array_diff( $columns, (array) $actual ) ) {
                return new WP_Error( 'bh_booking_columns', 'Booking table columns could not be verified.' );
            }
        }
        update_option( 'bh3_booking_schema_version', self::VERSION, false );
        return true;
    }

    public static function installed() {
        if ( get_option( 'bh3_booking_schema_version' ) !== self::VERSION ) { return false; }
        global $wpdb;
        foreach ( self::tables() as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return false;
            }
        }
        return true;
    }

    /** Leader identity is always derived from authenticated WordPress user meta. */
    public static function current_organiser_id() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) { return 0; }
        return absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
    }

    /**
     * Returns the small, actionable queue, not all personal data.
     * Pending = requires a placement decision; waitlisted = capacity workflow.
     * No booking reads until explicitly installed.
     */
    public static function attention_counts( $organiser_id ) {
        $empty = array( 'pending' => 0, 'waitlisted' => 0 );
        if ( ! self::installed() || ! $organiser_id || self::current_organiser_id() !== absint( $organiser_id ) ) {
            return $empty;
        }
        global $wpdb;
        $table = self::tables()['reservations'];
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status,COUNT(*) AS total FROM {$table} WHERE organiser_id=%d AND status IN ('pending','waitlisted') GROUP BY status",
                absint( $organiser_id )
            ),
            ARRAY_A
        );
        foreach ( (array) $rows as $row ) {
            if ( isset( $empty[ $row['status'] ] ) ) {
                $empty[ $row['status'] ] = (int) $row['total'];
            }
        }
        return $empty;
    }
}
