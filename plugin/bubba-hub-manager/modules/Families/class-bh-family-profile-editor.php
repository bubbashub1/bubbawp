<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Family profile edits. Explicitly disabled until staging verification.
 * No public REST route, no legacy session mutation, no schema changes.
 */
final class BH_Family_Profile_Editor_3 {
    public static function register() {
        add_action('admin_post_bh3_family_child_name', array(__CLASS__, 'handle_child_name'));
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
