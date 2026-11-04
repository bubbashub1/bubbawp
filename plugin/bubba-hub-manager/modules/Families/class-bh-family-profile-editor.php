<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Family profile edits. Explicitly disabled until staging verification.
 * No public REST route, no legacy session mutation, no schema changes.
 */
final class BH_Family_Profile_Editor_3 {
    public static function register() {
        add_action('admin_post_bh3_family_child_name', array(__CLASS__, 'handle_child_name'));
        add_action('admin_post_bh3_family_bump_name', array(__CLASS__, 'handle_bump_name'));
        add_action('admin_post_bh3_family_create_profile', array(__CLASS__, 'handle_create_profile'));
        add_action('admin_post_bh3_family_profile_date', array(__CLASS__, 'handle_profile_date'));
    }

    public static function handle_child_name() {
        if (!self::enabled() || !is_user_logged_in() || !isset($_POST['bh3_child_id'], $_POST['bh3_child_name'], $_POST['_wpnonce'])) {
            wp_die(esc_html__('Family profile editing is unavailable.', 'bubba-hub-manager'), '', array('response' => 403));
        }
        $id = absint(wp_unslash($_POST['bh3_child_id']));
        $name = sanitize_text_field(wp_unslash($_POST['bh3_child_name']));
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        $result = self::update_child_name($id, $name, $nonce);
        $status = is_wp_error($result) ? 'error' : 'saved';
        wp_safe_redirect(add_query_arg('bh3_profile_status', $status, wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function handle_bump_name() {
        if (!self::enabled() || !is_user_logged_in() || !isset($_POST['bh3_bump_id'], $_POST['bh3_bump_name'], $_POST['_wpnonce']) || !is_scalar($_POST['bh3_bump_name'])) {
            wp_die(esc_html__('Family profile editing is unavailable.', 'bubba-hub-manager'), '', array('response' => 403));
        }
        $result = self::update_bump_name(absint(wp_unslash($_POST['bh3_bump_id'])), sanitize_text_field(wp_unslash($_POST['bh3_bump_name'])), sanitize_text_field(wp_unslash($_POST['_wpnonce'])));
        wp_safe_redirect(add_query_arg('bh3_profile_status', is_wp_error($result) ? 'error' : 'saved', wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function update_bump_name($bump_id, $name, $nonce) {
        if (!self::enabled() || !is_user_logged_in() || !wp_verify_nonce($nonce, 'bh3_family_profile_edit')) {
            return new WP_Error('family_edit_denied', 'Profile editing is unavailable.');
        }
        $family_id = BH_Family_Identity_3::current_legacy_id();
        $name = sanitize_text_field($name);
        if ($family_id < 1 || absint($bump_id) < 1 || $name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
            return new WP_Error('family_invalid_profile', 'Enter a valid profile name.');
        }
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like('bh_bumps'))) !== 'bh_bumps') {
            return new WP_Error('family_table_missing', 'Pregnancy profiles are unavailable.');
        }
        $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM bh_bumps WHERE id = %d AND user_id = %d LIMIT 1', absint($bump_id), $family_id));
        if (!$exists) return new WP_Error('family_profile_missing', 'Profile not found.');
        $result = $wpdb->update('bh_bumps', array('nickname' => $name), array('id' => absint($bump_id), 'user_id' => $family_id), array('%s'), array('%d', '%d'));
        return $result === false ? new WP_Error('family_update_failed', 'Could not save profile.') : true;
    }

    public static function handle_profile_date() {
        if (!self::enabled() || !is_user_logged_in() || !isset($_POST['_wpnonce'], $_POST['bh3_profile_type'], $_POST['bh3_profile_id'], $_POST['bh3_profile_date'])
            || !is_scalar($_POST['_wpnonce']) || !is_scalar($_POST['bh3_profile_type']) || !is_scalar($_POST['bh3_profile_id']) || !is_scalar($_POST['bh3_profile_date'])) {
            wp_die(esc_html__('Profile date editing is unavailable.', 'bubba-hub-manager'), '', array('response' => 403));
        }
        $result = self::update_profile_date(
            sanitize_key(wp_unslash($_POST['bh3_profile_type'])),
            absint(wp_unslash($_POST['bh3_profile_id'])),
            sanitize_text_field(wp_unslash($_POST['bh3_profile_date'])),
            sanitize_text_field(wp_unslash($_POST['_wpnonce']))
        );
        wp_safe_redirect(add_query_arg('bh3_profile_status', is_wp_error($result) ? 'error' : 'saved', wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function update_profile_date($type, $id, $date, $nonce) {
        if (!self::enabled() || !is_user_logged_in() || !wp_verify_nonce($nonce, 'bh3_family_profile_edit')) {
            return new WP_Error('family_date_denied', 'Profile editing is disabled.');
        }
        $family_id = BH_Family_Identity_3::current_legacy_id();
        $allowed = array('child' => array('bh_children', 'date_of_birth'), 'bump' => array('bh_bumps', 'due_date'));
        if ($family_id < 1 || $id < 1 || !isset($allowed[$type])) return new WP_Error('family_date_invalid', 'Invalid profile.');
        $parts = explode('-', $date);
        if (strlen($date) !== 10 || count($parts) !== 3 || !ctype_digit(str_replace('-', '', $date))
            || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])
            || (int) $parts[0] < 1900 || (int) $parts[0] > 2100) {
            return new WP_Error('family_date_invalid', 'Enter a valid date.');
        }
        $today = current_time('Y-m-d');
        if ($type === 'child' && $date > $today) return new WP_Error('family_date_future', 'Birth date cannot be in the future.');
        if ($type === 'bump' && ($date < wp_date('Y-m-d', strtotime($today . ' - 300 days'), wp_timezone()) || $date > wp_date('Y-m-d', strtotime($today . ' + 600 days'), wp_timezone()))) {
            return new WP_Error('family_date_range', 'Due date outside the supported range.');
        }
        global $wpdb;
        list($table, $column) = $allowed[$type];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return new WP_Error('family_date_table', 'Profile storage unavailable.');
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE id = %d AND user_id = %d LIMIT 1", $id, $family_id));
        if (!$exists) return new WP_Error('family_date_missing', 'Profile not found.');
        $result = $wpdb->update($table, array($column => $date), array('id' => $id, 'user_id' => $family_id), array('%s'), array('%d', '%d'));
        return $result === false ? new WP_Error('family_date_failed', 'Unable to save date.') : true;
    }

    /** Separate opt-in: creating records is not enabled by the name-edit flag. */
    public static function creation_enabled() {
        return self::enabled() && defined('BH3_FAMILY_PROFILE_CREATION') && BH3_FAMILY_PROFILE_CREATION === true;
    }

    public static function handle_create_profile() {
        if (!self::creation_enabled() || !is_user_logged_in() || !isset($_POST['_wpnonce'], $_POST['bh3_profile_type'], $_POST['bh3_profile_name'], $_POST['bh3_profile_date'])
            || !is_scalar($_POST['_wpnonce']) || !is_scalar($_POST['bh3_profile_type']) || !is_scalar($_POST['bh3_profile_name']) || !is_scalar($_POST['bh3_profile_date'])) {
            wp_die(esc_html__('Profile creation is unavailable.', 'bubba-hub-manager'), '', array('response' => 403));
        }
        $result = self::create_profile(
            sanitize_key(wp_unslash($_POST['bh3_profile_type'])),
            sanitize_text_field(wp_unslash($_POST['bh3_profile_name'])),
            sanitize_text_field(wp_unslash($_POST['bh3_profile_date'])),
            sanitize_text_field(wp_unslash($_POST['_wpnonce']))
        );
        wp_safe_redirect(add_query_arg('bh3_profile_status', is_wp_error($result) ? 'error' : 'saved', wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function create_profile($type, $name, $date, $nonce) {
        if (!self::creation_enabled() || !is_user_logged_in() || !wp_verify_nonce($nonce, 'bh3_family_profile_create')) {
            return new WP_Error('family_create_denied', 'Profile creation is disabled.');
        }
        $family_id = BH_Family_Identity_3::current_legacy_id();
        $name = sanitize_text_field($name);
        if ($family_id < 1 || $name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
            return new WP_Error('family_invalid_name', 'A valid family profile name is required.');
        }
        $config = array(
            'child' => array('table' => 'bh_children', 'name' => 'name', 'date' => 'date_of_birth', 'limit' => 50),
            'bump' => array('table' => 'bh_bumps', 'name' => 'nickname', 'date' => 'due_date', 'limit' => 20),
        );
        if (!isset($config[$type])) return new WP_Error('family_invalid_type', 'Unknown profile type.');
        $parts = explode('-', $date);
        if (count($parts) !== 3 || strlen($date) !== 10 || !ctype_digit(str_replace('-', '', $date))
            || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])
            || (int) $parts[0] < 1900 || (int) $parts[0] > 2100) {
            return new WP_Error('family_invalid_date', 'Enter a valid date.');
        }
        $today = current_time('Y-m-d');
        if ($type === 'child' && $date > $today) return new WP_Error('family_future_birth', 'Birth date cannot be in the future.');
        if ($type === 'bump' && ($date < wp_date('Y-m-d', strtotime($today . ' - 300 days'), wp_timezone()) || $date > wp_date('Y-m-d', strtotime($today . ' + 600 days'), wp_timezone()))) {
            return new WP_Error('family_due_date_range', 'Due date is outside the supported range.');
        }
        global $wpdb;
        $settings = $config[$type];
        $table = $settings['table'];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return new WP_Error('family_table_missing', 'Profile storage is unavailable.');
        }
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d", $family_id));
        if ($count >= $settings['limit']) return new WP_Error('family_profile_limit', 'Profile limit reached.');
        $name_col = $settings['name'];
        $date_col = $settings['date'];
        $duplicate = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE user_id = %d AND `{$name_col}` = %s AND `{$date_col}` = %s LIMIT 1", $family_id, $name, $date));
        if ($duplicate) return new WP_Error('family_duplicate_profile', 'This profile already exists.');
        $result = $wpdb->insert($table, array('user_id' => $family_id, $name_col => $name, $date_col => $date), array('%d', '%s', '%s'));
        return $result === false ? new WP_Error('family_create_failed', 'Unable to create profile.') : true;
    }

    public static function enabled() {
        return wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk'
            && defined('BH3_FAMILY_PROFILE_EDITING') && BH3_FAMILY_PROFILE_EDITING === true
            && defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true;
    }

    public static function update_child_name($child_id, $name, $nonce) {
        if (!self::enabled() || !is_user_logged_in()) {
            return new WP_Error('family_edit_disabled', 'Family profile editing is not enabled.');
        }
        if (!wp_verify_nonce($nonce, 'bh3_family_profile_edit')) {
            return new WP_Error('family_invalid_nonce', 'Please refresh and try again.');
        }
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1 || absint($child_id) < 1) {
            return new WP_Error('family_not_verified', 'Verified family account required.');
        }
        $name = sanitize_text_field($name);
        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
            return new WP_Error('family_invalid_name', 'Enter a name of 1 to 100 characters.');
        }
        global $wpdb;
        $table = 'bh_children';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return new WP_Error('family_table_missing', 'Family profiles are unavailable.');
        }
        $result = $wpdb->update($table, array('name' => $name), array('id' => absint($child_id), 'user_id' => $family_id), array('%s'), array('%d', '%d'));
        if ($result === false) return new WP_Error('family_update_failed', 'Could not save the profile.');
        if ($result === 0) return new WP_Error('family_no_change', 'No matching profile changed.');
        return true;
    }
}
