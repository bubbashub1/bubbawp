<?php
/** Explicit, additive activity-field parity migration. Staging only. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Parity_3 {
    const VERSION = '1.0.0';
    public static function is_staging() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function fields() {
        return array(
            'leader_id' => 'bigint(20) unsigned NULL',
            'booking_required' => 'tinyint(1) NOT NULL DEFAULT 0',
            'price' => 'decimal(10,2) NULL',
            'image_path' => 'varchar(1000) NULL',
            'price_type' => "varchar(40) NOT NULL DEFAULT 'per_session'",
            'currency' => "char(3) NOT NULL DEFAULT 'GBP'",
            'session_length_minutes' => 'smallint(5) unsigned NULL',
            'term_time_only' => 'tinyint(1) NOT NULL DEFAULT 0',
            'contact_email' => 'varchar(255) NULL',
            'contact_phone' => 'varchar(100) NULL',
            'website' => 'varchar(1000) NULL',
            'facebook_url' => 'varchar(500) NULL',
            'instagram_url' => 'varchar(500) NULL',
            'featured' => 'tinyint(1) NOT NULL DEFAULT 0',
            'created_by' => 'bigint(20) unsigned NULL',
            'published_at' => 'datetime NULL',
            'age_range' => 'varchar(255) NULL',
            'accessibility' => 'text NULL',
            'tags' => 'text NULL',
            'weekly_schedule' => 'text NULL',
            'price_per_family' => 'tinyint(1) NOT NULL DEFAULT 0',
            'price_per_session' => 'tinyint(1) NOT NULL DEFAULT 0',
            'drop_in_welcome' => 'tinyint(1) NOT NULL DEFAULT 0',
            'trial_available' => 'tinyint(1) NOT NULL DEFAULT 0',
            'holiday_sessions' => 'tinyint(1) NOT NULL DEFAULT 0',
            'siblings_welcome' => 'tinyint(1) NOT NULL DEFAULT 0',
            'what_to_bring' => 'varchar(250) NULL',
            'good_to_know' => 'varchar(250) NULL',
            'gallery_images' => 'longtext NULL',
            'block_length_weeks' => 'smallint(5) unsigned NULL',
        );
    }
    public static function register() {
        if ( ! self::is_staging() ) { return; }
        add_action( 'admin_post_bh3_activity_parity_migrate', array( __CLASS__, 'migrate' ) );
    }
    public static function migrate() {
        if ( ! self::is_staging() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_activity_parity_migrate' );
        if ( (string) ( $_POST['bh3_confirm'] ?? '' ) !== 'MIGRATE' ) {
            wp_die( 'Type MIGRATE to confirm.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            wp_die( 'Activities table not found. No changes made.', '', array( 'response' => 404 ) );
        }
        $existing = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( ! is_array( $existing ) ) { wp_die( 'Could not inspect activities table.', '', array( 'response' => 500 ) ); }
        $added = array();
        foreach ( self::fields() as $name => $definition ) {
            if ( in_array( $name, $existing, true ) ) { continue; }
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}";
            if ( $wpdb->query( $sql ) === false ) {
                wp_die( 'Migration stopped while adding ' . esc_html( $name ) . ': ' . esc_html( $wpdb->last_error ), '', array( 'response' => 500 ) );
            }
            $added[] = $name;
        }
        $verify = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        foreach ( array_keys( self::fields() ) as $name ) {
            if ( ! in_array( $name, $verify, true ) ) {
                wp_die( 'Migration verification failed for ' . esc_html( $name ), '', array( 'response' => 500 ) );
            }
        }
        update_option( 'bh3_activity_parity_version', self::VERSION, false );
        update_option( 'bh3_activity_parity_added', $added, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bubba-hub-3-status', 'bh3_activity_parity' => 'success' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render_control() {
        if ( ! self::is_staging() || ! current_user_can( 'manage_options' ) ) { return; }
        $version = get_option( 'bh3_activity_parity_version', '' );
        echo '<h2>Activity field compatibility</h2>';
        echo '<p>Legacy activity fields are added to the existing WordPress activities table only. Existing columns and records are not overwritten. Structured sessions, venues, organisers and WordPress media IDs remain separate.</p>';
        echo '<p>Migration version: <strong>' . esc_html( $version ?: 'Not installed' ) . '</strong></p>';
        if ( isset( $_GET['bh3_activity_parity'] ) && 'success' === sanitize_key( wp_unslash( $_GET['bh3_activity_parity'] ) ) ) {
            echo '<div class="notice notice-success"><p>Activity field migration completed and verified.</p></div>';
        }
        if ( $version === self::VERSION ) { return; }
        echo '<p><strong>Back up staging before proceeding.</strong> This is additive and can be safely rerun; it does not copy legacy records or change live.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_activity_parity_migrate">';
        wp_nonce_field( 'bh3_activity_parity_migrate' );
        echo '<label for="bh3_activity_parity_confirm">Type MIGRATE: </label><input id="bh3_activity_parity_confirm" name="bh3_confirm" required autocomplete="off">';
        echo ' <button class="button button-primary" type="submit">Add missing activity fields on staging</button></form>';
    }
}
