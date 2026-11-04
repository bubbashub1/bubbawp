<?php
if (!defined('ABSPATH')) exit;
/** Read-only staging diagnostics; never returns member records. */
final class BH_Family_Schema_Audit_3 {
    public static function register() { add_shortcode('bubbahub_family_schema_audit',array(__CLASS__,'render')); }
    public static function render() {
        if (!current_user_can('manage_options') || wp_parse_url(home_url(),PHP_URL_HOST) !== 'staging.bubbahub.co.uk') return '';
        global $wpdb;
        $tables = array(
            'bh_children'=>array('id','user_id','name','date_of_birth'),
            'bh_bumps'=>array('id','user_id','nickname','due_date'),
            'bh_saved_activities'=>array('user_id','activity_id'),
            'bh_planner'=>array('user_id','activity_id','visited'),
            'bh_user_preferences'=>array('user_id','region','town','max_price'),
            $wpdb->prefix.'activities'=>array('id','slug','status')
        );
        $html = '<section class="bh3-family"><h2>Family data readiness (read only)</h2><p>No family names, account details or records are displayed.</p><table class="widefat striped"><thead><tr><th>Table</th><th>Available</th><th>Expected columns</th></tr></thead><tbody>';
        foreach ($tables as $table=>$columns) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) === $table;
            $found = array();
            if ($exists) {
                $rows = $wpdb->get_col('SHOW COLUMNS FROM `'.str_replace('`','``',$table).'`');
                $found = is_array($rows) ? $rows : array();
            }
            $missing = array_diff($columns,$found);
            $html .= '<tr><td>'.esc_html($table).'</td><td>'.($exists ? 'Yes' : 'Missing').'</td><td>'.esc_html(!$exists ? 'Not checked' : (!$missing ? 'All expected columns present' : 'Missing: '.implode(', ',$missing))).'</td></tr>';
        }
        $html .= '</tbody></table><p>Legacy identity bridge: '.(function_exists('bh_wp_resolve_legacy_identity') ? 'Installed' : 'Not detected').'.</p><p>The original bridge requires one unique bh_legacy_user_id WordPress user-meta link, an active legacy account, matching email, and a bh_family role. Simple Membership and FluentAuth login linkage must still be verified. This report does not link accounts.</p></section>';
        return $html;
    }
}
