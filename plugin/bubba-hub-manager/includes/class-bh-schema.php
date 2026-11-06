<?php
/** Fresh BH3 schema, intentionally never installed automatically. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Schema_3 {
    public const VERSION = '0.7.0';

    public static function table_names() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        return array(
            'organisers' => $prefix . 'organisers',
            'venues' => $prefix . 'venues',
            'activities' => $prefix . 'activities',
            'activity_venues' => $prefix . 'activity_venues',
            'sessions' => $prefix . 'sessions',
            'session_listing_links' => $prefix . 'session_listing_links',
            'bookings' => $prefix . 'bh3_bookings',
            'stripe_events' => $prefix . 'bh3_stripe_events',
            'booking_consents' => $prefix . 'bh3_booking_consents',
            'booking_attendees' => $prefix . 'bh3_booking_attendees',
        );
    }

    public static function statements() {
        global $wpdb;
        $t = self::table_names();
        $charset = $wpdb->get_charset_collate();
        return array(
            "CREATE TABLE {$t['organisers']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(200) NOT NULL,
                description longtext NULL,
                website_url varchar(2048) NULL,
                contact_email varchar(254) NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY slug (slug)
            ) $charset;",
            "CREATE TABLE {$t['venues']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                address_line1 varchar(255) NULL,
                town varchar(150) NULL,
                postcode varchar(20) NULL,
                region varchar(100) NULL,
                latitude decimal(10,7) NULL,
                longitude decimal(10,7) NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY postcode (postcode),
                KEY town (town)
            ) $charset;",
            "CREATE TABLE {$t['activities']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                organiser_id bigint(20) unsigned NULL,
                title varchar(255) NOT NULL,
                slug varchar(200) NOT NULL,
                description longtext NULL,
                status varchar(30) NOT NULL DEFAULT 'draft',
                category varchar(150) NULL,
                age_min_months smallint(5) unsigned NULL,
                age_max_months smallint(5) unsigned NULL,
                price_from decimal(10,2) NULL,
                is_free tinyint(1) NOT NULL DEFAULT 0,
                booking_url varchar(2048) NULL,
                image_id bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY organiser_id (organiser_id),
                KEY slug (slug),
                KEY status (status)
            ) $charset;",
            "CREATE TABLE {$t['activity_venues']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                activity_id bigint(20) unsigned NOT NULL,
                venue_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY activity_venue (activity_id,venue_id),
                KEY venue_id (venue_id)
            ) $charset;",
            "CREATE TABLE {$t['session_listing_links']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                session_id bigint(20) unsigned NOT NULL,
                activity_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_listing (session_id,activity_id),
                KEY activity_id (activity_id)
            ) $charset;",
            "CREATE TABLE {$t['stripe_events']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                stripe_event_id varchar(255) NOT NULL,
                event_type varchar(100) NOT NULL,
                checkout_session_id varchar(255) NULL,
                processing_status varchar(30) NOT NULL DEFAULT 'received',
                received_at datetime NOT NULL,
                processed_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY stripe_event_id (stripe_event_id),
                KEY checkout_session_id (checkout_session_id),
                KEY processing_status (processing_status)
            ) $charset;",
            "CREATE TABLE {$t['bookings']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_reference char(36) NOT NULL,
                session_id bigint(20) unsigned NOT NULL,
                occurrence_date date NOT NULL,
                activity_id bigint(20) unsigned NOT NULL,
                organiser_id bigint(20) unsigned NOT NULL,
                customer_user_id bigint(20) unsigned NULL,
                booking_form_id bigint(20) unsigned NULL,
                booking_submission_id bigint(20) unsigned NULL,
                places smallint(5) unsigned NOT NULL,
                currency char(3) NOT NULL DEFAULT 'gbp',
                unit_price_pence bigint(20) unsigned NOT NULL,
                gross_pence bigint(20) unsigned NOT NULL,
                commission_pence bigint(20) unsigned NOT NULL,
                leader_before_fees_pence bigint(20) unsigned NOT NULL,
                commission_rate_percent decimal(5,2) NOT NULL,
                booking_status varchar(30) NOT NULL DEFAULT 'reserved',
                payment_status varchar(30) NOT NULL DEFAULT 'pending',
                reservation_expires_at datetime NULL,
                stripe_checkout_session_id varchar(255) NULL,
                stripe_payment_intent_id varchar(255) NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_reference (public_reference),
                UNIQUE KEY booking_submission_id (booking_submission_id),
                UNIQUE KEY stripe_checkout_session_id (stripe_checkout_session_id),
                KEY session_occurrence (session_id,occurrence_date),
                KEY booking_status (booking_status),
                KEY payment_status (payment_status),
                KEY organiser_id (organiser_id)
            ) $charset;",
            "CREATE TABLE {$t['booking_attendees']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                attendee_number smallint(5) unsigned NOT NULL,
                family_profile_id bigint(20) unsigned NULL,
                attendee_type varchar(20) NOT NULL,
                attendee_name varchar(100) NOT NULL DEFAULT '',
                gender varchar(40) NOT NULL DEFAULT '',
                year_of_birth smallint(5) unsigned NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY booking_attendee (booking_id,attendee_number),
                KEY booking_id (booking_id)
            ) $charset;",
            "CREATE TABLE {$t['booking_consents']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                consent_key varchar(80) NOT NULL,
                consent_version varchar(40) NOT NULL,
                response varchar(20) NOT NULL,
                response_at datetime NOT NULL,
                actor_user_id bigint(20) unsigned NULL,
                source varchar(20) NOT NULL DEFAULT 'booking',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY booking_consent_version (booking_id,consent_key,consent_version),
                KEY booking_id (booking_id)
            ) $charset;",
            "CREATE TABLE {$t['sessions']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                activity_id bigint(20) unsigned NOT NULL,
                venue_id bigint(20) unsigned NULL,
                day_of_week tinyint(3) unsigned NULL,
                start_time time NULL,
                end_time time NULL,
                start_date date NULL,
                end_date date NULL,
                frequency varchar(40) NOT NULL DEFAULT 'weekly',
                term_time_only tinyint(1) NOT NULL DEFAULT 0,
                status varchar(30) NOT NULL DEFAULT 'scheduled',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY activity_id (activity_id),
                KEY venue_id (venue_id),
                KEY day_of_week (day_of_week)
            ) $charset;",
        );
    }

    /** Booking-only additive migration. Existing tables are NEVER passed to dbDelta. */
    public static function booking_table_keys() {
        return array( 'session_listing_links', 'bookings', 'stripe_events', 'booking_consents', 'booking_attendees' );
    }

    public static function booking_table_audit() {
        global $wpdb;
        $names = self::table_names();
        $result = array();
        foreach ( self::booking_table_keys() as $key ) {
            $table = $names[ $key ];
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
            $result[ $key ] = array( 'table' => $table, 'exists' => $exists );
        }
        return $result;
    }

    /** Read-only comparison against the declared booking table columns and indexes. */
    public static function booking_structure_audit() {
        global $wpdb;
        $names = self::table_names();
        $definitions = self::statements();
        $results = array();
        foreach ( self::booking_table_keys() as $key ) {
            $table = $names[ $key ];
            $definition = '';
            foreach ( $definitions as $statement ) {
                if ( strpos( $statement, "CREATE TABLE {$table} (" ) === 0 ) {
                    $definition = $statement;
                    break;
                }
            }
            $expected_columns = array();
            $expected_indexes = array();
            foreach ( explode( "\n", $definition ) as $line ) {
                $line = trim( $line );
                if ( preg_match( '/^([a-z_]+)\\s+(?:bigint|smallint|tinyint|varchar|char|decimal|datetime|date|time|longtext)\\b/i', $line, $match ) ) {
                    $expected_columns[] = $match[1];
                } elseif ( preg_match( '/^(?:UNIQUE\\s+)?KEY\\s+([a-z_]+)\\s*\\(/i', $line, $match ) ) {
                    $expected_indexes[] = $match[1];
                } elseif ( strpos( $line, 'PRIMARY KEY' ) === 0 ) {
                    $expected_indexes[] = 'PRIMARY';
                }
            }
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
            $actual_columns = array();
            $actual_indexes = array();
            if ( $exists ) {
                // Table identifiers come only from the plugin's fixed schema map.
                $actual_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
                $actual_indexes = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
            }
            $results[ $key ] = array(
                'table' => $table,
                'exists' => $exists,
                'missing_columns' => array_values( array_diff( $expected_columns, (array) $actual_columns ) ),
                'missing_indexes' => array_values( array_diff( $expected_indexes, (array) $actual_indexes ) ),
            );
        }
        return $results;
    }

    /** Must be invoked only by the explicit staging administrator action. */
    public static function install_missing_booking_tables() {
        if ( ! current_user_can( 'manage_options' )
            || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return new WP_Error( 'bh3_forbidden', 'Staging administrator permission required.' );
        }
        global $wpdb;
        $audit = self::booking_table_audit();
        $statements = self::statements();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $created = array();
        foreach ( $audit as $item ) {
            if ( $item['exists'] ) { continue; }
            $table = $item['table'];
            $sql = null;
            foreach ( $statements as $statement ) {
                if ( strpos( $statement, "CREATE TABLE {$table} (" ) === 0 ) {
                    $sql = $statement;
                    break;
                }
            }
            if ( ! $sql ) {
                return new WP_Error( 'bh3_missing_definition', 'Booking table definition missing.' );
            }
            // Never change or rebuild a pre-existing table.
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { continue; }
            dbDelta( $sql );
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return new WP_Error( 'bh3_booking_table_failed', 'Could not create booking table: ' . $table );
            }
            $created[] = $table;
        }
        // Do not mark the whole legacy schema as 0.7.0: existing core tables were not migrated.
        return $created;
    }

    /** Admin-only, explicitly invoked later; not called during plugin activation or page load. */
    public static function install() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'bh3_forbidden', 'Administrator permission required.' );
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( self::statements() as $statement ) {
            dbDelta( $statement );
        }
        global $wpdb;
        foreach ( self::table_names() as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return new WP_Error( 'bh3_schema_failed', 'A required BH3 table was not created.' );
            }
        }
        update_option( 'bh3_schema_version', self::VERSION, false );
        return true;
    }
}
