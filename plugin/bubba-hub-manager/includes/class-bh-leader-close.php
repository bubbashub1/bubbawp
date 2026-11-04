<?php
if (!defined('ABSPATH')) { exit; }
final class BH_Leader_Close_3 {
    public static function save() {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'POST required', '', array( 'response' => 405 ) ); }
        nocache_headers();
        $id = absint($_POST['activity_id'] ?? 0);
        if (!BH_Leader_Access_3::is_staging() || !current_user_can('read') || !$id) { wp_die('Not permitted', '', array('response'=>403)); }
        check_admin_referer('bh3_close_' . $id);
        if (!BH_Leader_Access_3::owns_activity($id) || sanitize_key( wp_unslash( $_POST['confirm_close'] ?? '' ) ) !== 'yes') { wp_die('Not permitted', '', array('response'=>403)); }
        if ( ! BH_Leader_Access_3::table_exists( 'activities' ) ) { wp_die( 'Activity table unavailable', '', array( 'response' => 503 ) ); }
        global $wpdb;
        $result = $wpdb->update($wpdb->prefix . 'activities', array('status'=>'draft','updated_at'=>current_time('mysql')), array('id'=>$id,'organiser_id'=>BH_Leader_Access_3::organiser_id(),'status'=>'published'));
        if ($result !== 1) { wp_die('Activity not updated', '', array('response'=>409)); }
        wp_safe_redirect(admin_url('admin.php?page=bh3-leader-dashboard&closed=1'));
        exit;
    }
}
