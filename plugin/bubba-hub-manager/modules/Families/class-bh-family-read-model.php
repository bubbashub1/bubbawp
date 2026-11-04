<?php
if (!defined('ABSPATH')) { exit; }
/** Read-only access to legacy family records; never called from public preview. */
final class BH_Family_Read_Model_3 {
    public static function saved_ids() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        $table = 'bh_saved_activities';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return array();
        return $wpdb->get_col($wpdb->prepare('SELECT activity_id FROM bh_saved_activities WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id));
    }
}
