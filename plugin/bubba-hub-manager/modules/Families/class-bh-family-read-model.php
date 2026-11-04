<?php
if (!defined('ABSPATH')) { exit; }
/** Read-only access to legacy family records; never called from public preview. */
final class BH_Family_Read_Model_3 {
    public static function saved_ids() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        $table = 'bh_saved_activities';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return new WP_Error('family_saved_unavailable', 'Saved activities table unavailable');
        $rows = $wpdb->get_col($wpdb->prepare('SELECT activity_id FROM bh_saved_activities WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id));
        return is_array($rows) ? array_map('absint', $rows) : new WP_Error('family_saved_query_failed', 'Unable to load saved activities');
    }

    public static function planner_items() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_planner')) !== 'bh_planner') return new WP_Error('family_planner_unavailable', 'Planner table unavailable');
        $rows = $wpdb->get_results($wpdb->prepare('SELECT activity_id, visited FROM bh_planner WHERE user_id = %d ORDER BY created_at DESC LIMIT 200', $family_id), ARRAY_A);
        return is_array($rows) ? $rows : new WP_Error('family_planner_query_failed', 'Unable to load planner');
    }
    public static function family_profiles() {
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1) return new WP_Error('family_not_verified', 'Verified family login required');
        global $wpdb;
        // Prefer the new WP-owned profiles when present. Never mix WP and legacy IDs.
        $native = $wpdb->prefix . 'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($native))) === $native) {
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT id, profile_type, display_name, profile_date, gender FROM `{$native}` WHERE user_id = %d AND profile_type IN ('child','bump') ORDER BY id ASC LIMIT 70",
                get_current_user_id()
            ), ARRAY_A);
            if (!is_array($records)) return new WP_Error('family_native_profiles_failed', 'Unable to load family profiles');
            $result = array('children'=>array(), 'bumps'=>array());
            foreach ($records as $record) {
                if ($record['profile_type'] === 'child' && count($result['children']) < 50) {
                    $result['children'][] = array('id'=>(int)$record['id'], 'name'=>$record['display_name'], 'date_of_birth'=>$record['profile_date'], 'gender'=>isset($record['gender']) ? $record['gender'] : '');
                } elseif ($record['profile_type'] === 'bump' && count($result['bumps']) < 20) {
                    $result['bumps'][] = array('id'=>(int)$record['id'], 'nickname'=>$record['display_name'], 'due_date'=>$record['profile_date']);
                }
            }
            return $result;
        }
        $out = array('children' => array(), 'bumps' => array());
        $children_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_children'));
        if ($children_table !== 'bh_children') return new WP_Error('family_children_unavailable', 'Child profiles table unavailable');
        {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT id, name, date_of_birth FROM bh_children WHERE user_id = %d ORDER BY date_of_birth LIMIT 50', $family_id), ARRAY_A);
            if (!is_array($rows)) return new WP_Error('family_children_query_failed', 'Unable to load child profiles');
            $out['children'] = $rows;
        }
        $bumps_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_bumps'));
        if ($bumps_table !== 'bh_bumps') return new WP_Error('family_bumps_unavailable', 'Bump profiles table unavailable');
        {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT id, nickname, due_date FROM bh_bumps WHERE user_id = %d ORDER BY due_date LIMIT 20', $family_id), ARRAY_A);
            if (!is_array($rows)) return new WP_Error('family_bumps_query_failed', 'Unable to load bump profiles');
            $out['bumps'] = $rows;
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
        // A slug shared by multiple published legacy activities is ambiguous,
        // even if the new WordPress table has only one matching record.
        $legacy_slug_placeholders = implode(',', array_fill(0, count($slugs), '%s'));
        $legacy_sql = "SELECT slug, COUNT(*) AS matches FROM bh_activities WHERE slug IN ({$legacy_slug_placeholders}) AND status = 'published' GROUP BY slug";
        $legacy_counts = $wpdb->get_results($wpdb->prepare($legacy_sql, $slugs), ARRAY_A);
        if (!is_array($legacy_counts)) return array();
        $legacy_unique = array();
        foreach ($legacy_counts as $row) if ((int) $row['matches'] === 1) $legacy_unique[(string) $row['slug']] = true;
        $links = array();
        foreach ($legacy as $row) {
            $slug = (string) $row['slug'];
            if (!empty($unique[$slug]) && !empty($legacy_unique[$slug]) && sanitize_title($slug) === $slug) {
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
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return new WP_Error('family_activity_table_unavailable', 'Activity titles table unavailable');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT id,title FROM bh_activities WHERE id IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);
        if (!is_array($rows)) return new WP_Error('family_activity_titles_failed', 'Unable to load activity titles');
        $titles = array();
        foreach ($rows as $row) $titles[(int) $row['id']] = (string) $row['title'];
        return $titles;
    }

}
