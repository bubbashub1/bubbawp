<?php
if (!defined('ABSPATH')) exit;
/** Staging-only, explicitly opted-in WordPress family profile creation. */
final class BH_Family_Native_Profiles_3 {
    public static function register() {
        add_shortcode('bubbahub_family_add_profile',array(__CLASS__,'dashboard'));
        add_shortcode('bubbahub_family_profile',array(__CLASS__,'dashboard'));
        add_shortcode('bubbahub_family_profiles',array(__CLASS__,'dashboard'));
        add_action('admin_post_bh3_native_family_profile',array(__CLASS__,'save'));
        add_action('admin_post_bh3_native_family_update',array(__CLASS__,'update'));
        add_action('admin_post_bh3_native_family_delete',array(__CLASS__,'delete'));
        add_filter('the_content',array(__CLASS__,'replace_family_profile_page'),30);
    }
    /** Render the native manager on the existing /family/profile/ page. */
    public static function replace_family_profile_page($content) {
        if (is_admin() || !is_page()) return $content;
        $page_id=get_queried_object_id();
        if (!$page_id || get_page_uri($page_id)!=='family/profile') return $content;
        return self::dashboard();
    }
    public static function allowed() {
        // Native profiles belong to the authenticated WordPress account. They do not
        // require the legacy Bubba Hub session bridge used by older family records.
        // Keep writes staging-only until the full Family Hub migration is approved.
        return wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk'
            && is_user_logged_in()
            && defined('BH3_FAMILY_NATIVE_PROFILE_WRITES')
            && BH3_FAMILY_NATIVE_PROFILE_WRITES === true;
    }
    public static function form() {
        if (!self::allowed()) return '<p>Please sign in to manage your family profiles. Profile editing is currently available on the staging site only.</p>';
        ob_start(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bh3-family-profile-add">
          <input type="hidden" name="action" value="bh3_native_family_profile">
          <?php wp_nonce_field('bh3_native_family_profile'); ?>
          <p><label>Profile type
            <select name="profile_type" required>
              <option value="child">Child</option>
              <option value="bump">Pregnancy</option>
            </select>
          </label></p>
          <p><label>Name or nickname <input name="display_name" maxlength="100" required autocomplete="off"></label></p>
          <p><label>Birth or due date <input type="date" name="profile_date" required></label></p>
          <p><button type="submit">Add family profile</button></p>
        </form>
        <?php return ob_get_clean();
    }
    /** Layout-only admin preview: never query or write another family's records. */
    public static function preview() {
        ob_start(); ?>
        <div class="bh3-family-profile-preview">
          <div >
            <nav aria-label="My Hub sections" >
              <strong>My Hub</strong>
              <p><a href="<?php echo esc_url(home_url('/family/')); ?>">Overview</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/profile/')); ?>" aria-current="page">My family</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/settings/')); ?>">Settings</a></p>
            </nav>
            <section >
              <h2>My family</h2>
              <p><strong>Administrator preview</strong> — example layout only. No family data is loaded.</p>
              <h3>Children</h3><p>Child profiles will appear here.</p>
              <h3>Pregnancy profiles</h3><p>Pregnancy profiles will appear here.</p>
              <fieldset disabled><legend>Add a family profile (preview)</legend>
                <p><label>Profile type <select><option>Child</option><option>Pregnancy</option></select></label></p>
                <p><label>Name or nickname <input type="text" placeholder="Profile name"></label></p>
                <p><label>Birth or due date <input type="date"></label></p>
                <button type="button">Add family profile</button>
              </fieldset>
            </section>
          </div>
        </div>
        <?php return ob_get_clean();
    }
    public static function dashboard() {
        if (!self::allowed()) return '<p>Please sign in to manage your family profiles. Profile editing is currently available on the staging site only.</p>';
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return '<p>Profile storage is not available.</p>';
        $records=$wpdb->get_results($wpdb->prepare("SELECT id,profile_type,display_name,profile_date FROM `{$table}` WHERE user_id=%d ORDER BY id DESC LIMIT 70",get_current_user_id()),ARRAY_A);
        if (!is_array($records)) return '<p>Unable to load profiles.</p>';
        ob_start(); ?>
        <div class="bh3-family-profile-preview">
          <div>
            <nav aria-label="My Hub sections"><strong>My Hub</strong>
              <p><a href="<?php echo esc_url(home_url('/family/')); ?>">Overview</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/profile/')); ?>" aria-current="page">My family</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/settings/')); ?>">Settings</a></p>
            </nav>
            <section><h2>My family</h2>
              <?php if (isset($_GET['bh3_profile_status']) && in_array(sanitize_key(wp_unslash($_GET['bh3_profile_status'])),array('saved','removed'),true)) : ?><p role="status"><?php echo esc_html($_GET['bh3_profile_status']==='removed'?'Profile removed.':'Profile saved.'); ?></p><?php endif; ?>
              <?php foreach (array('child'=>'Children','bump'=>'Pregnancy profiles') as $type=>$heading) : ?>
                <h3><?php echo esc_html($heading); ?></h3>
                <?php $found=false; foreach ($records as $record) : if ($record['profile_type']!==$type) continue; $found=true; ?>
                  <div class="bh3-family-profile-entry">
                    <?php echo self::edit_form($record); ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this profile? This cannot be undone.');">
                      <input type="hidden" name="action" value="bh3_native_family_delete">
                      <input type="hidden" name="profile_id" value="<?php echo esc_attr(absint($record['id'])); ?>">
                      <?php wp_nonce_field('bh3_native_family_delete_'.absint($record['id'])); ?>
                      <button type="submit">Remove profile</button>
                    </form>
                  </div>
                <?php endforeach; if (!$found) : ?><p>No profiles added yet.</p><?php endif; ?>
              <?php endforeach; ?>
              <h3>Add a family profile</h3><?php echo self::form(); ?>
            </section>
          </div>
        </div>
        <?php return ob_get_clean();
    }
    public static function delete() {
        if (!self::allowed()) wp_die('Profile removal unavailable.','',array('response'=>403));
        $id=isset($_POST['profile_id']) && is_scalar($_POST['profile_id']) ? absint($_POST['profile_id']) : 0;
        $nonce=isset($_POST['_wpnonce']) && is_scalar($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$id || !wp_verify_nonce($nonce,'bh3_native_family_delete_'.$id)) wp_die('Invalid request.','',array('response'=>403));
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_profiles';
        $deleted=$wpdb->delete($table,array('id'=>$id,'user_id'=>get_current_user_id()),array('%d','%d'));
        if ($deleted===false) wp_die('Unable to remove profile.','',array('response'=>500));
        if ($deleted===0) wp_die('Profile not found.','',array('response'=>404));
        wp_safe_redirect(add_query_arg('bh3_profile_status','removed',home_url('/family/profile/')));
        exit;
    }
    public static function edit_form($record) {
        if (!self::allowed() || !is_array($record) || empty($record['id'])) return '';
        $type = ($record['profile_type'] ?? '') === 'bump' ? 'bump' : 'child';
        ob_start(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bh3-family__edit">
          <input type="hidden" name="action" value="bh3_native_family_update">
          <input type="hidden" name="profile_id" value="<?php echo esc_attr(absint($record['id'])); ?>">
          <?php wp_nonce_field('bh3_native_family_update_' . absint($record['id'])); ?>
          <label>Name or nickname <input name="display_name" maxlength="100" required value="<?php echo esc_attr($record['display_name']); ?>"></label>
          <label><?php echo esc_html($type==='child'?'Date of birth':'Due date'); ?> <input name="profile_date" type="date" required value="<?php echo esc_attr($record['profile_date']); ?>"></label>
          <button type="submit">Save changes</button>
        </form>
        <?php return ob_get_clean();
    }
    public static function update() {
        if (!self::allowed()) wp_die('Profile editing unavailable.','',array('response'=>403));
        $id=isset($_POST['profile_id']) && is_scalar($_POST['profile_id']) ? absint($_POST['profile_id']) : 0;
        $nonce=isset($_POST['_wpnonce']) && is_scalar($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$id || !wp_verify_nonce($nonce,'bh3_native_family_update_'.$id)) wp_die('Invalid request.','',array('response'=>403));
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_profiles';
        $record=$wpdb->get_row($wpdb->prepare("SELECT id,profile_type FROM `{$table}` WHERE id=%d AND user_id=%d LIMIT 1",$id,get_current_user_id()),ARRAY_A);
        if (!$record) wp_die('Profile not found.','',array('response'=>404));
        $name=isset($_POST['display_name']) && is_scalar($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $date=isset($_POST['profile_date']) && is_scalar($_POST['profile_date']) ? sanitize_text_field(wp_unslash($_POST['profile_date'])) : '';
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
        $today=new DateTimeImmutable('today',wp_timezone());
        if (!$name || strlen($name)>100 || !$parsed || $parsed->format('Y-m-d')!==$date) wp_die('Invalid profile details.','',array('response'=>400));
        if ($record['profile_type']==='child' && ($parsed>$today || (int)$parsed->format('Y')<1900)) wp_die('Invalid birth date.','',array('response'=>400));
        if ($record['profile_type']==='bump' && ($parsed<$today->modify('-300 days') || $parsed>$today->modify('+600 days'))) wp_die('Invalid due date.','',array('response'=>400));
        $result=$wpdb->update($table,array('display_name'=>$name,'profile_date'=>$date,'updated_at'=>current_time('mysql')),array('id'=>$id,'user_id'=>get_current_user_id()),array('%s','%s','%s'),array('%d','%d'));
        if ($result===false) wp_die('Unable to update profile.','',array('response'=>500));
        wp_safe_redirect(add_query_arg('bh3_profile_status','saved',home_url('/family/profile/')));
        exit;
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
        wp_safe_redirect(add_query_arg('bh3_profile_status','saved',home_url('/family/profile/')));
        exit;
    }
}
