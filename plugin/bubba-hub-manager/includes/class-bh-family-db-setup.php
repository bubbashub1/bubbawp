<?php
if (!defined('ABSPATH')) exit;
/** Explicit staging-only setup for new WordPress family tables. No legacy migration. */
final class BH_Family_DB_Setup_3 {
    public static function names() {
        global $wpdb;
        return array(
            'profiles'=>$wpdb->prefix.'bh_family_profiles',
            'preferences'=>$wpdb->prefix.'bh_family_preferences',
            'saved'=>$wpdb->prefix.'bh_saved_activities',
            'planner'=>$wpdb->prefix.'bh_planner',
            'reminders'=>$wpdb->prefix.'bh_family_reminders'
        );
    }
    public static function definitions() {
        global $wpdb;
        $t=self::names(); $collate=$wpdb->get_charset_collate();
        return array(
            'profiles'=>"CREATE TABLE {$t['profiles']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                profile_type varchar(20) NOT NULL,
                display_name varchar(100) NOT NULL DEFAULT '',
                profile_date date NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $collate;",
            'reminders'=>"CREATE TABLE {$t['reminders']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                profile_id bigint(20) unsigned NOT NULL,
                profile_type varchar(20) NOT NULL,
                title varchar(160) NOT NULL,
                reminder_date date NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_profile (user_id,profile_id),
                KEY reminder_date (reminder_date)
            ) $collate;",
            'preferences'=>"CREATE TABLE {$t['preferences']} (
                user_id bigint(20) unsigned NOT NULL,
                preferences_json longtext NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (user_id)
            ) $collate;",
            'saved'=>"CREATE TABLE {$t['saved']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                activity_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_activity (user_id,activity_id)
            ) $collate;",
            'planner'=>"CREATE TABLE {$t['planner']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                activity_id bigint(20) unsigned NOT NULL,
                session_id bigint(20) unsigned NULL,
                planned_date date NULL,
                visited tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY activity_id (activity_id)
            ) $collate;"
        );
    }
    private static function staging() {
        return strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST))==='staging.bubbahub.co.uk';
    }
    public static function register() {
        add_action('admin_menu',array(__CLASS__,'menu'),35);
        add_action('admin_post_bh3_create_family_tables',array(__CLASS__,'create'));
    }
    public static function menu() {
        add_submenu_page('bubba-hub-3-status','Database Setup','Database Setup','manage_options','bh3-family-database',array(__CLASS__,'render'));
    }
    public static function exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;
    }
    public static function render() {
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        echo '<div class="wrap"><h1>Bubba Hub · Database Setup</h1><p>New WordPress-native family tables. This does not migrate or restore legacy family records.</p>';
        if (!self::staging()) { echo '<p>Table creation is disabled outside staging.</p></div>';return; }
        if (isset($_GET['bh3_db_result'])) echo '<div class="notice notice-info"><p>'.esc_html($_GET['bh3_db_result']==='created'?'Missing family tables created. Please review the status below.':'Setup did not complete. Review the table status and server logs.').'</p></div>';
        echo '<table class="widefat striped"><thead><tr><th>Table / field</th><th>Status</th></tr></thead><tbody>';
        $missing=0;
        foreach (self::names() as $key=>$table) {
            $exists=self::exists($table);
            if (!$exists) $missing++;
            echo '<tr><td>'.esc_html($table).'</td><td>'.($exists?'Exists — existing records preserved':'Missing — can create').'</td></tr>';
            if ($key === 'profiles' && $exists) {
                global $wpdb;
                $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);
                $has_gender = is_array($columns) && in_array('gender',$columns,true);
                if (!$has_gender) $missing++;
                echo '<tr><td>'.esc_html($table.'.gender').'</td><td>'.($has_gender?'Exists':'Missing — add optional attendee gender field').'</td></tr>';
            }
        }
        echo '</tbody></table><p><strong>'.esc_html($missing).' schema item(s) need attention.</strong> Existing family records are preserved; only missing tables or the missing gender column will be added.</p>';
        if ($missing) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo '<input type="hidden" name="action" value="bh3_create_family_tables">';
            wp_nonce_field('bh3_create_family_tables');
            echo '<p><label><input type="checkbox" name="confirm" value="1" required> I have a staging database backup and understand this creates empty tables only.</label></p>';
            submit_button('Create missing family tables');
            echo '</form>';
        }
        echo '</div>';
    }
    public static function create() {
        if (!self::staging() || !current_user_can('manage_options')) wp_die('Staging administrator required.','',array('response'=>403));
        check_admin_referer('bh3_create_family_tables');
        if (!isset($_POST['confirm']) || $_POST['confirm']!=='1') wp_die('Confirm backup first.','',array('response'=>400));
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        global $wpdb;
        $failed=false;
        $names = self::names();
        foreach (self::definitions() as $key=>$sql) {
            $table=$names[$key];
            if (self::exists($table)) continue;
            dbDelta($sql);
            if (!self::exists($table)) $failed=true;
        }
        // Additive, staging-only compatibility migration. Never rebuild or
        // overwrite existing family profile rows.
        $profiles = $names['profiles'];
        if (self::exists($profiles)) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$profiles}`",0);
            if (is_array($columns) && !in_array('gender',$columns,true)) {
                $added = $wpdb->query("ALTER TABLE `{$profiles}` ADD COLUMN gender varchar(40) NOT NULL DEFAULT '' AFTER display_name");
                if ($added === false) $failed=true;
            }
        }
        wp_safe_redirect(add_query_arg('bh3_db_result',$failed?'error':'created',admin_url('admin.php?page=bh3-family-database')));
        exit;
    }
}
