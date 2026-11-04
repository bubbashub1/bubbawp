<?php
if (!defined('ABSPATH')) exit;
/** Staging-only, explicitly opted-in WordPress family profile creation. */
final class BH_Family_Native_Profiles_3 {
    public static function register() {
        add_shortcode('bubbahub_family_add_profile',array(__CLASS__,'form'));
        add_action('admin_post_bh3_native_family_profile',array(__CLASS__,'save'));
    }
    public static function allowed() {
        return BH_Family_Preferences_3::can_edit()
            && defined('BH3_FAMILY_NATIVE_PROFILE_WRITES') && BH3_FAMILY_NATIVE_PROFILE_WRITES === true;
    }
    public static function form() {
        if (!self::allowed()) return '<p>Family profile creation is not yet enabled for this account.</p>';
        ob_start(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="bh3_native_family_profile">
          <?php wp_nonce_field('bh3_native_family_profile'); ?>
          <p><label>Profile type <select name="profile_type" required><option value="child">Child</option><option value="bump">Pregnancy</option></select></label></p>
          <p><label>Name or nickname <input name="display_name" maxlength="100" required></label></p>
          <p><label>Birth or due date <input type="date" name="profile_date" required></label></p>
          <p><button type="submit">Add family profile</button></p>
        </form>
        <?php return ob_get_clean();
    }
    public static function save() {
        if (!self::allowed() || !isset($_POST['_wpnonce']) || !is_scalar($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),'bh3_native_family_profile')) {
            wp_die('Family profile creation unavailable.','',array('response'=>403));
        }
        $type = isset($_POST['profile_type']) && is_scalar($_POST['profile_type']) ? sanitize_key(wp_unslash($_POST['profile_type'])) : '';
        $name = isset($_POST['display_name']) && is_scalar($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $date = isset($_POST['profile_date']) && is_scalar($_POST['profile_date']) ? sanitize_text_field(wp_unslash($_POST['profile_date'])) : '';
        if (!in_array($type,array('child','bump'),true) || !$name || strlen($name)>100) wp_die('Invalid profile.','',array('response'=>400));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
        if (!$parsed || $parsed->format('Y-m-d')!==$date) wp_die('Invalid date.','',array('response'=>400));
        $today = new DateTimeImmutable('today',wp_timezone());
        if ($type==='child' && ($parsed>$today || (int)$parsed->format('Y')<1900)) wp_die('Invalid birth date.','',array('response'=>400));
        if ($type==='bump' && ($parsed<$today->modify('-300 days') || $parsed>$today->modify('+600 days'))) wp_die('Invalid due date.','',array('response'=>400));
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) wp_die('Family profile table missing.','',array('response'=>503));
        $user=get_current_user_id();
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d AND profile_type=%s",$user,$type));
        if ($count>=($type==='child'?50:20)) wp_die('Profile limit reached.','',array('response'=>400));
        $duplicate=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE user_id=%d AND profile_type=%s AND display_name=%s AND profile_date=%s LIMIT 1",$user,$type,$name,$date));
        if ($duplicate) wp_die('This profile already exists.','',array('response'=>409));
        $now=current_time('mysql');
        $saved=$wpdb->insert($table,array('user_id'=>$user,'profile_type'=>$type,'display_name'=>$name,'profile_date'=>$date,'created_at'=>$now,'updated_at'=>$now),array('%d','%s','%s','%s','%s','%s'));
        if ($saved===false) wp_die('Could not save profile.','',array('response'=>500));
        wp_safe_redirect(add_query_arg('bh3_profile_status','saved',home_url('/family/')).'#bh3-my-family');
        exit;
    }
}
