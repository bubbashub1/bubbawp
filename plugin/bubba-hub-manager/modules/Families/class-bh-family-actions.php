<?php
if (!defined('ABSPATH')) { exit; }
/** Staging-only, opt-in family favourites and planner changes. */
final class BH_Family_Actions_3 {
    public static function enabled() {
        return wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk'
            && defined('BH3_FAMILY_ACTIONS') && BH3_FAMILY_ACTIONS === true
            && defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true;
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
        $family_id = BH_Family_Identity_3::current_legacy_id();
        if ($family_id < 1 || $activity_id < 1) return new WP_Error('family_not_verified', 'Verified family account required.');
        $tables = array('unsave' => 'bh_saved_activities', 'unplan' => 'bh_planner', 'visited' => 'bh_planner', 'not_visited' => 'bh_planner');
        if (!isset($tables[$action])) return new WP_Error('family_unknown_action', 'Unknown action.');
        global $wpdb;
        $table = $tables[$action];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return new WP_Error('family_table_missing', 'Activity records unavailable.');
        $where = array('user_id' => $family_id, 'activity_id' => $activity_id);
        if ($action === 'unsave' || $action === 'unplan') {
            $result = $wpdb->delete($table, $where, array('%d', '%d'));
        } else {
            $result = $wpdb->update($table, array('visited' => $action === 'visited' ? 1 : 0), $where, array('%d'), array('%d', '%d'));
        }
        if ($result === false) return new WP_Error('family_write_failed', 'Could not update the activity.');
        return true;
    }
}
