<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Family profile edits. Explicitly disabled until staging verification.
 * No public REST route, no legacy session mutation, no schema changes.
 */
final class BH_Family_Profile_Editor_3 {
    public static function enabled() {
        return defined('BH3_FAMILY_PROFILE_EDITING') && BH3_FAMILY_PROFILE_EDITING === true
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
