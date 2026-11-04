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

    public static function planner_items() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_planner')) !== 'bh_planner') return array();
        return $wpdb->get_results($wpdb->prepare('SELECT activity_id, visited FROM bh_planner WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id), ARRAY_A);
    }
    public static function family_profiles() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        $out = array('children' => array(), 'bumps' => array());
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_children')) === 'bh_children') {
            $out['children'] = $wpdb->get_results($wpdb->prepare('SELECT id, name, date_of_birth FROM bh_children WHERE user_id = %d ORDER BY date_of_birth LIMIT 50', $family_id), ARRAY_A);
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_bumps')) === 'bh_bumps') {
            $out['bumps'] = $wpdb->get_results($wpdb->prepare('SELECT id, nickname, due_date FROM bh_bumps WHERE user_id = %d ORDER BY due_date LIMIT 20', $family_id), ARRAY_A);
        }
        return $out;
    }
}
