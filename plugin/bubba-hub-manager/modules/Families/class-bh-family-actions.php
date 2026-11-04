<?php
if (!defined('ABSPATH')) { exit; }
/** Staging-only, opt-in family favourites and planner changes. */
final class BH_Family_Actions_3 {
    public static function enabled() {
        return wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk'
            && defined('BH3_FAMILY_ACTIONS') && BH3_FAMILY_ACTIONS === true
            && defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true;
    }
    /** Fail closed if a staging table lacks the composite key used for duplicate prevention. */
    private static function has_composite_key($table) {
        global $wpdb;
        if (!in_array($table, array('bh_planner', 'bh_saved_activities'), true)) return false;
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'", ARRAY_A);
        if (!is_array($indexes)) return false;
        $columns = array();
        foreach ($indexes as $index) $columns[(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        ksort($columns);
        return array_values($columns) === array('user_id', 'activity_id');
    }
    public static function register() {
        add_action('admin_post_bh3_family_activity_action', array(__CLASS__, 'handle'));
    }
    public static function handle() {
        if (!self::enabled() || !is_user_logged_in() || !isset($_POST['_wpnonce'], $_POST['bh3_activity_id'], $_POST['bh3_action'])) {
            wp_die('Family actions are unavailable.', '', array('response' => 403));
        }
        $id = absint(wp_unslash($_POST['bh3_activity_id']));
        $action = sanitize_key(wp_unslash($_POST['bh3_action']));
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        $result = self::apply($id, $action, $nonce);
        $status = is_wp_error($result) ? 'error' : 'saved';
        wp_safe_redirect(add_query_arg('bh3_activity_status', $status, wp_get_referer() ?: home_url('/')));
        exit;
    }
    public static function apply($activity_id, $action, $nonce) {
        if (!self::enabled() || !is_user_logged_in() || !wp_verify_nonce($nonce, 'bh3_family_activity_action')) {
            return new WP_Error('family_action_denied', 'Action not permitted.');
        }
        $activity_id = absint($activity_id);
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1 || $activity_id < 1) return new WP_Error('family_not_verified', 'Verified family account required.');
        $tables = array('unsave' => 'bh_saved_activities', 'unplan' => 'bh_planner', 'visited' => 'bh_planner', 'not_visited' => 'bh_planner', 'plan_saved' => 'bh_planner', 'visit_saved' => 'bh_planner');
        if (!isset($tables[$action])) return new WP_Error('family_unknown_action', 'Unknown action.');
        global $wpdb;
        $table = $tables[$action];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return new WP_Error('family_table_missing', 'Activity records unavailable.');
        $where = array('user_id' => $family_id, 'activity_id' => $activity_id);
        if (in_array($action, array('plan_saved', 'visit_saved'), true) && (!self::has_composite_key('bh_planner') || !self::has_composite_key('bh_saved_activities'))) {
            return new WP_Error('family_schema_mismatch', 'Planner schema verification failed.');
        }
        if ($action === 'plan_saved' || $action === 'visit_saved') {
            // Only allow IDs that are already saved by this verified family.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like('bh_saved_activities'))) !== 'bh_saved_activities') {
                return new WP_Error('family_saved_missing', 'Saved activities are unavailable.');
            }
            $is_saved = $wpdb->get_var($wpdb->prepare('SELECT activity_id FROM bh_saved_activities WHERE user_id = %d AND activity_id = %d LIMIT 1', $family_id, $activity_id));
            if (!$is_saved) return new WP_Error('family_not_saved', 'Save the activity before adding it to your planner.');
            // Do not guess whether legacy activity IDs map to new WordPress records.
            if (!defined('BH3_FAMILY_LEGACY_ACTIVITY_STATUS_VERIFIED') || BH3_FAMILY_LEGACY_ACTIVITY_STATUS_VERIFIED !== true) {
                return new WP_Error('family_activity_unverified', 'Activity publication status has not been verified.');
            }
            $legacy_table = 'bh_activities';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_table))) !== $legacy_table) {
                return new WP_Error('family_activity_table_missing', 'Activity records are unavailable.');
            }
            $published = $wpdb->get_var($wpdb->prepare("SELECT id FROM bh_activities WHERE id = %d AND status IN ('published','publish') LIMIT 1", $activity_id));
            if (!$published) return new WP_Error('family_activity_unpublished', 'This activity is not currently published.');
            $existing = $wpdb->get_var($wpdb->prepare('SELECT activity_id FROM bh_planner WHERE user_id = %d AND activity_id = %d LIMIT 1', $family_id, $activity_id));
            if ($existing) {
                if ($action === 'plan_saved') return true;
                $result = $wpdb->update('bh_planner', array('visited' => 1), $where, array('%d'), array('%d', '%d'));
            } else {
                $result = $wpdb->insert('bh_planner', array('user_id' => $family_id, 'activity_id' => $activity_id, 'visited' => $action === 'visit_saved' ? 1 : 0), array('%d', '%d', '%d'));
            }
        } elseif ($action === 'unsave' || $action === 'unplan') {
            $result = $wpdb->delete($table, $where, array('%d', '%d'));
        } else {
            $result = $wpdb->update($table, array('visited' => $action === 'visited' ? 1 : 0), $where, array('%d'), array('%d', '%d'));
        }
        if ($result === false) return new WP_Error('family_write_failed', 'Could not update the activity.');
        return true;
    }
}
