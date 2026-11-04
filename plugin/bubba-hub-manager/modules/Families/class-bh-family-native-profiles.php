<?php
if (!defined('ABSPATH')) exit;
/** Staging-only, explicitly opted-in WordPress family profile creation. */
final class BH_Family_Native_Profiles_3 {
    public static function register() {
        add_shortcode('bubbahub_family_add_profile',array(__CLASS__,'dashboard'));
        add_action('admin_post_bh3_native_family_profile',array(__CLASS__,'save'));
        add_action('admin_post_bh3_native_family_update',array(__CLASS__,'update'));
        add_action('admin_post_bh3_native_family_delete',array(__CLASS__,'delete'));
        add_action('fluentform/submission_inserted',array(__CLASS__,'fluent_form_submission'),10,3);
        add_action('admin_init',array(__CLASS__,'maybe_upgrade_schema'));
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
        if (!self::allowed()) {
            return '<p>Please sign in to manage your family profiles. Profile editing is currently available on the staging site only.</p>';
        }
        if (shortcode_exists('fluentform')) return do_shortcode('[fluentform id="5"]');
        return '<p>Family profile form is not available yet. Please contact support.</p>';
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
    /** Add optional gender column without replacing existing family records. */
    public static function maybe_upgrade_schema() {
        if (!current_user_can('manage_options') || wp_parse_url(home_url(),PHP_URL_HOST)!=='staging.bubbahub.co.uk') return;
        if (get_option('bh3_family_profiles_gender_schema')==='1') return;
        global $wpdb; $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $sql="CREATE TABLE {$table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL,profile_type varchar(20) NOT NULL,display_name varchar(100) NOT NULL DEFAULT '',profile_date date NULL,gender varchar(40) NOT NULL DEFAULT '',created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),KEY user_id (user_id)) ".$wpdb->get_charset_collate().";";
        dbDelta($sql);
        $columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);
        if (is_array($columns) && in_array('gender',$columns,true)) update_option('bh3_family_profiles_gender_schema','1',false);
    }
    /** Store authenticated submissions from Fluent Forms form 5. */
    public static function fluent_form_submission($entry_id,$form_data,$form) {
        $form_id=is_object($form) && isset($form->id) ? absint($form->id) : (is_array($form) && isset($form['id']) ? absint($form['id']) : 0);
        if ($form_id!==5 || !self::allowed() || !is_user_logged_in() || !is_array($form_data)) return;
        $type=isset($form_data['profile_type']) && is_scalar($form_data['profile_type']) ? sanitize_key($form_data['profile_type']) : '';
        $name=isset($form_data['display_name']) && is_scalar($form_data['display_name']) ? sanitize_text_field($form_data['display_name']) : '';
        $date=isset($form_data['profile_date']) && is_scalar($form_data['profile_date']) ? sanitize_text_field($form_data['profile_date']) : '';
        $gender=isset($form_data['gender']) && is_scalar($form_data['gender']) ? sanitize_text_field($form_data['gender']) : '';
        if (!in_array($type,array('child','bump'),true) || $name==='' || strlen($name)>100) return;
        if ($type==='bump') $gender=''; if (strlen($gender)>40) $gender=substr($gender,0,40);
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
        if (!$parsed || $parsed->format('Y-m-d')!==$date) return;
        $today=new DateTimeImmutable('today',wp_timezone());
        if ($type==='child' && ($parsed>$today || (int)$parsed->format('Y')<1900)) return;
        if ($type==='bump' && ($parsed<$today->modify('-300 days') || $parsed>$today->modify('+600 days')) ) return;
        global $wpdb; $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return;
        $user=get_current_user_id();
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d AND profile_type=%s",$user,$type));
        if ($count>=($type==='child'?50:20)) return;
        $duplicate=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE user_id=%d AND profile_type=%s AND display_name=%s AND profile_date=%s LIMIT 1",$user,$type,$name,$date));
        if ($duplicate) return;
        $wpdb->insert($table,array('user_id'=>$user,'profile_type'=>$type,'display_name'=>$name,'profile_date'=>$date,'gender'=>$gender,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')),array('%d','%s','%s','%s','%s','%s','%s'));
    }
    public static function dashboard() {
        if (!self::allowed()) return '<p>Please sign in to manage your family profiles. Profile editing is currently available on the staging site only.</p>';
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return '<p>Profile storage is not available.</p>';
        $records=$wpdb->get_results($wpdb->prepare("SELECT id,profile_type,display_name,profile_date,gender FROM `{$table}` WHERE user_id=%d ORDER BY id DESC LIMIT 70",get_current_user_id()),ARRAY_A);
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
              <?php if (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status']==='saved') : ?><p role="status">Profile saved.</p><?php endif; ?>
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
          <?php if ($type==='child') : ?><label>Gender (optional) <input name="gender" maxlength="40" value="<?php echo esc_attr($record['gender'] ?? ''); ?>"></label><?php endif; ?>
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
        $gender=isset($_POST['gender']) && is_scalar($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : '';
        if (strlen($gender)>40) $gender=substr($gender,0,40);
        if ($record['profile_type']==='bump') $gender='';
        $result=$wpdb->update($table,array('display_name'=>$name,'profile_date'=>$date,'gender'=>$gender,'updated_at'=>current_time('mysql')),array('id'=>$id,'user_id'=>get_current_user_id()),array('%s','%s','%s','%s'),array('%d','%d'));
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
        $gender=($type==='child' && isset($_POST['gender']) && is_scalar($_POST['gender'])) ? substr(sanitize_text_field(wp_unslash($_POST['gender'])),0,40) : '';
        $saved=$wpdb->insert($table,array('user_id'=>$user,'profile_type'=>$type,'display_name'=>$name,'profile_date'=>$date,'gender'=>$gender,'created_at'=>$now,'updated_at'=>$now),array('%d','%s','%s','%s','%s','%s','%s'));
        if ($saved===false) wp_die('Could not save profile.','',array('response'=>500));
        wp_safe_redirect(add_query_arg('bh3_profile_status','saved',home_url('/family/')).'#bh3-my-family');
        exit;
    }
}
