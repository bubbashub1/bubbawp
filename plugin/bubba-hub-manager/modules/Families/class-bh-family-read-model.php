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
        $rows = $wpdb->get_col($wpdb->prepare('SELECT activity_id FROM bh_saved_activities WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id));
        return is_array($rows) ? array_map('absint', $rows) : array();
    }

    public static function planner_items() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_planner')) !== 'bh_planner') return array();
        $rows = $wpdb->get_results($wpdb->prepare('SELECT activity_id, visited FROM bh_planner WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }
    public static function family_profiles() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        $out = array('children' => array(), 'bumps' => array());
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_children')) === 'bh_children') {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT id, name, date_of_birth FROM bh_children WHERE user_id = %d ORDER BY date_of_birth LIMIT 50', $family_id), ARRAY_A);
            $out['children'] = is_array($rows) ? $rows : array();
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_bumps')) === 'bh_bumps') {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT id, nickname, due_date FROM bh_bumps WHERE user_id = %d ORDER BY due_date LIMIT 20', $family_id), ARRAY_A);
            $out['bumps'] = is_array($rows) ? $rows : array();
        }
        return $out;
    }
    /**
     * Link only when an exact legacy slug matches one published WordPress activity.
     * No assumptions about numeric IDs and no links to drafts.
     */
    public static function activity_links($ids) {
        if (BH_Family_Identity_3::current_legacy_id() < 1) return array();
        if (!defined('BH3_FAMILY_ACTIVITY_LINKS') || BH3_FAMILY_ACTIVITY_LINKS !== true) return array();
        if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'staging.bubbahub.co.uk') return array();
        $ids = is_array($ids) ? array_slice(array_values(array_unique(array_filter(array_map('absint', $ids)))), 0, 200) : array();
        if (!$ids) return array();
        global $wpdb;
        $new_table = $wpdb->prefix . 'activities';
        foreach (array('bh_activities', $new_table) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return array();
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT id, slug FROM bh_activities WHERE id IN ({$placeholders}) AND status = 'published'";
        $legacy = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);
        if (!is_array($legacy) || !$legacy) return array();
        $slugs = array_values(array_unique(array_filter(array_map(static function($row) {
            return isset($row['slug']) ? (string) $row['slug'] : '';
        }, $legacy))));
        if (!$slugs) return array();
        $slug_placeholders = implode(',', array_fill(0, count($slugs), '%s'));
        $sql = "SELECT slug, COUNT(*) AS matches FROM `{$new_table}` WHERE slug IN ({$slug_placeholders}) AND status IN ('published','publish') GROUP BY slug";
        $matches = $wpdb->get_results($wpdb->prepare($sql, $slugs), ARRAY_A);
        $unique = array();
        foreach ((array) $matches as $row) if ((int) $row['matches'] === 1) $unique[(string) $row['slug']] = true;
        $links = array();
        foreach ($legacy as $row) {
            $slug = (string) $row['slug'];
            if (!empty($unique[$slug]) && sanitize_title($slug) === $slug) {
                $links[(int) $row['id']] = home_url('/activity/' . rawurlencode($slug) . '/');
            }
        }
        return $links;
    }

    /**
     * Resolve legacy saved/planned activity names from their original ID space.
     * New WordPress activity IDs are deliberately not assumed to match.
     */
    public static function activity_titles($ids) {
        if (BH_Family_Identity_3::current_legacy_id() < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        if (!is_array($ids)) return array();
        $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', $ids)))), 0, 200);
        if (!$ids) return array();
        global $wpdb;
        $table = 'bh_activities';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return array();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT id,title FROM bh_activities WHERE id IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);
        $titles = array();
        foreach ((array) $rows as $row) $titles[(int) $row['id']] = (string) $row['title'];
        return $titles;
    }

}
