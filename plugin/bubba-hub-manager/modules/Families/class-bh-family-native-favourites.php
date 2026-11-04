<?php
if (!defined('ABSPATH')) exit;
/** Staging-only WP activity favourites. No legacy ID assumptions. */
final class BH_Family_Native_Favourites_3 {
    public static function register() {
        add_shortcode('bubbahub_family_favourites',array(__CLASS__,'render'));
        add_shortcode('bubbahub_save_activity',array(__CLASS__,'save_button'));
        add_action('admin_post_bh3_native_favourite',array(__CLASS__,'handle'));
    }
    public static function allowed() {
        return BH_Family_Preferences_3::can_edit()
            && defined('BH3_FAMILY_NATIVE_FAVOURITES') && BH3_FAMILY_NATIVE_FAVOURITES === true;
    }
    /** Activity page control; requires an explicit WP-native activity ID. */
    public static function save_button($atts = array()) {
        if (!self::allowed()) return '';
        $atts=shortcode_atts(array('id'=>0),$atts,'bubbahub_save_activity');
        $id=absint($atts['id']);
        if (!$id) return '';
        global $wpdb;
        $activities=$wpdb->prefix.'activities';
        $saved=$wpdb->prefix.'bh_saved_activities';
        foreach (array($activities,$saved) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return '';
        }
        $published=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$activities}` WHERE id=%d AND status IN ('published','publish') LIMIT 1",$id));
        if (!$published) return '';
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$saved}` WHERE user_id=%d AND activity_id=%d LIMIT 1",get_current_user_id(),$id));
        $operation=$existing?'remove':'add';
        ob_start(); ?>
        <form class="bh3-family-save-control" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="bh3_native_favourite">
          <input type="hidden" name="activity_id" value="<?php echo esc_attr($id); ?>">
          <input type="hidden" name="operation" value="<?php echo esc_attr($operation); ?>">
          <?php wp_nonce_field('bh3_native_favourite_'.$id); ?>
          <button type="submit" aria-pressed="<?php echo $existing?'true':'false'; ?>"><?php echo esc_html($existing?'Remove from favourites':'Save activity'); ?></button>
        </form>
        <?php return ob_get_clean();
    }
    public static function render() {
        if (current_user_can('manage_options')) return '<p>Administrator preview: family favourites are private.</p>';
        if (!self::allowed()) return '<p>Sign in with a verified family account to view favourites.</p>';
        global $wpdb;
        $saved=$wpdb->prefix.'bh_saved_activities';
        $activities=$wpdb->prefix.'activities';
        foreach (array($saved,$activities) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return '<p>Favourites are not available.</p>';
        }
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT a.id,a.title,a.slug FROM `{$saved}` f INNER JOIN `{$activities}` a ON a.id=f.activity_id WHERE f.user_id=%d AND a.status IN ('published','publish') ORDER BY f.created_at DESC LIMIT 200",
            get_current_user_id()
        ),ARRAY_A);
        if (!is_array($rows)) return '<p>Unable to load favourites.</p>';
        ob_start(); ?>
        <section class="bh3-family-favourites"><h2>My favourites</h2>
        <?php if (!$rows) : ?><p>No saved activities yet.</p><?php endif; ?>
        <?php foreach ($rows as $row) : ?>
          <article><a href="<?php echo esc_url(home_url('/activity/'.rawurlencode($row['slug']).'/')); ?>"><?php echo esc_html($row['title']); ?></a>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bh3_native_favourite">
            <input type="hidden" name="activity_id" value="<?php echo esc_attr(absint($row['id'])); ?>">
            <input type="hidden" name="operation" value="remove">
            <?php wp_nonce_field('bh3_native_favourite_'.absint($row['id'])); ?>
            <button type="submit">Remove favourite</button>
          </form></article>
        <?php endforeach; ?></section>
        <?php return ob_get_clean();
    }
    public static function handle() {
        if (!self::allowed()) wp_die('Favourites unavailable.','',array('response'=>403));
        $id=isset($_POST['activity_id']) && is_scalar($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;
        $operation=isset($_POST['operation']) && is_scalar($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $nonce=isset($_POST['_wpnonce']) && is_scalar($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$id || !in_array($operation,array('add','remove'),true) || !wp_verify_nonce($nonce,'bh3_native_favourite_'.$id)) wp_die('Invalid request.','',array('response'=>403));
        global $wpdb;
        $table=$wpdb->prefix.'bh_saved_activities';
        $activities=$wpdb->prefix.'activities';
        foreach (array($table,$activities) as $name) if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($name)))!==$name) wp_die('Storage unavailable.','',array('response'=>503));
        $user=get_current_user_id();
        if ($operation==='add') {
            $published=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$activities}` WHERE id=%d AND status IN ('published','publish') LIMIT 1",$id));
            if (!$published) wp_die('Activity unavailable.','',array('response'=>404));
            $result=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$table}` (user_id,activity_id,created_at) VALUES (%d,%d,%s)",$user,$id,current_time('mysql')));
        } else {
            $result=$wpdb->delete($table,array('user_id'=>$user,'activity_id'=>$id),array('%d','%d'));
        }
        if ($result===false) wp_die('Could not update favourite.','',array('response'=>500));
        wp_safe_redirect(home_url('/family/'));
        exit;
    }
}
