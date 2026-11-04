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
        add_action('admin_post_bh3_family_reminder_save',array(__CLASS__,'save_reminder'));
        add_action('admin_post_bh3_family_reminder_delete',array(__CLASS__,'delete_reminder'));
        add_filter('the_content',array(__CLASS__,'replace_family_profile_page'),30);
        add_action('admin_init',array(__CLASS__,'maybe_add_gender_column'));
    }
    /** Render the native manager on the existing /family/profile/ page. */
    public static function replace_family_profile_page($content) {
        if (is_admin() || !is_page()) return $content;
        $page_id=get_queried_object_id();
        if (!$page_id || get_page_uri($page_id)!=='family/profile') return $content;
        return self::dashboard();
    }
    public static function maybe_add_gender_column() {
        if (!current_user_can('manage_options') || strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST))!=='staging.bubbahub.co.uk') return;
        global $wpdb; $table=$wpdb->prefix.'bh_family_profiles';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return;
        $columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);
        if (is_array($columns) && !in_array('gender',$columns,true)) $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN gender varchar(40) NOT NULL DEFAULT '' AFTER profile_date");
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
          <p class="bh3-family-gender-field"><label>Gender (optional, child profiles only)
            <select name="gender"><option value="">Prefer not to say</option><option value="girl">Girl</option><option value="boy">Boy</option><option value="another">Another identity</option></select>
          </label></p>
          <p><button type="submit">Add family profile</button></p>
          <script>(function(){var f=document.currentScript.closest('form');if(!f)return;var t=f.querySelector('[name="profile_type"]'),g=f.querySelector('.bh3-family-gender-field');function sync(){g.hidden=t.value!=='child';}t.addEventListener('change',sync);sync();})();</script>
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
        $records=$wpdb->get_results($wpdb->prepare("SELECT id,profile_type,display_name,profile_date,gender FROM `{$table}` WHERE user_id=%d ORDER BY id DESC LIMIT 70",get_current_user_id()),ARRAY_A);
        if (!is_array($records)) return '<p>Unable to load profiles.</p>';
        ob_start(); ?>
        <style>.bh3-profile-reminders{margin:12px 0 4px;padding:12px;border-radius:12px}.bh3-profile-reminders--school{background:#e8f2ec;color:#205b49}.bh3-profile-reminders--pregnancy{background:#fff0df;color:#86552f}.bh3-profile-reminder{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid currentColor}.bh3-profile-reminder span{font-size:13px;opacity:.85}.bh3-profile-reminder button{background:transparent!important;color:inherit!important;padding:2px 8px!important;min-height:30px!important}.bh3-profile-reminder-add{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:10px;align-items:end;margin-top:10px}.bh3-profile-reminder-add label{font-size:12px}.bh3-profile-reminder-add button{white-space:nowrap}.bh3-profile-reminder-add input{min-width:0}@media(max-width:720px){.bh3-profile-reminder-add{grid-template-columns:1fr}.bh3-profile-reminder-add button{width:100%}}</style><style>.bh3-family-profile-manager{display:block;width:100%;max-width:100%;min-width:0}.bh3-family-profile-manager>div{display:grid;grid-template-columns:minmax(190px,230px) minmax(0,1fr);gap:26px;align-items:start;width:100%;min-width:0}.bh3-family-profile-manager>div>section{min-width:0;width:100%;box-sizing:border-box}.bh3-family-profile-manager>div>nav{min-width:0;box-sizing:border-box}.bh3-family-profile-manager nav,.bh3-family-profile-manager section{background:#fff;border:1px solid #dce8e1;border-radius:20px;padding:24px;box-shadow:0 4px 16px rgba(22,62,51,.05)}.bh3-family-profile-manager nav a{display:block;padding:10px 12px;border-radius:10px;color:#245b49;text-decoration:none}.bh3-family-profile-manager nav a[aria-current=page]{background:#e8f2ed;font-weight:600}.bh3-family-profile-manager h2,.bh3-family-profile-manager h3{color:#174f40}.bh3-family-profile-manager .bh3-family-profile-entry{padding:16px;margin:12px 0;border:1px solid #e0e9e3;border-radius:14px;background:#fbfdfb}.bh3-family-profile-manager form.bh3-family-profile-add{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:20px;border:1px solid #dce8e1;border-radius:16px;background:#fbfdfb}.bh3-family-profile-manager form p{margin:0;min-width:0}.bh3-family-profile-manager label{display:flex;flex-direction:column;gap:7px;font-weight:600;color:#244b40}.bh3-family-profile-manager input,.bh3-family-profile-manager select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #b8cec1;border-radius:10px;background:#fff;font:inherit;box-sizing:border-box}.bh3-family-profile-manager button{border:0;border-radius:10px;background:#205b49;color:#fff;padding:12px 18px;font:inherit;font-weight:600;cursor:pointer;min-height:46px;margin-top:8px}.bh3-family-profile-manager .bh3-family__edit button{align-self:end}.bh3-family-profile-manager .bh3-family-profile-entry>form:not(.bh3-family__edit){margin-top:12px}.bh3-family-profile-manager .bh3-family-profile-entry>form:not(.bh3-family__edit) button{margin-top:0}.bh3-family-profile-manager .bh3-family-profile-add-top{margin:0 0 28px;padding:18px 20px;border:1px solid #dce8e1;border-radius:16px;background:#fbfdfb}.bh3-family-profile-manager .bh3-family-profile-add-top h3{margin-top:0}.bh3-family-profile-manager .bh3-family-profile-entry .bh3-family__edit{row-gap:16px}.bh3-family-profile-manager .bh3-family-profile-add p:last-child{grid-column:1/-1}.bh3-family-profile-manager .bh3-family__edit{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:end}@media(max-width:720px){.bh3-family-profile-manager>div{grid-template-columns:1fr}.bh3-family-profile-manager form.bh3-family-profile-add,.bh3-family-profile-manager .bh3-family__edit{grid-template-columns:1fr}.bh3-family-profile-manager .bh3-family-profile-add p:last-child{grid-column:auto}}</style>
        <div class="bh3-family-profile-preview bh3-family-profile-manager">
          <div>
            <nav aria-label="My Hub sections"><strong>My Hub</strong>
              <p><a href="<?php echo esc_url(home_url('/family/')); ?>">Overview</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/profile/')); ?>" aria-current="page">My family</a></p>
              <p><a href="<?php echo esc_url(home_url('/family/settings/')); ?>">Settings</a></p>
            </nav>
            <section><h2>My family</h2>
              <?php if (isset($_GET['bh3_profile_status']) && in_array(sanitize_key(wp_unslash($_GET['bh3_profile_status'])),array('saved','removed'),true)) : ?><p role="status"><?php echo esc_html($_GET['bh3_profile_status']==='removed'?'Profile removed.':'Profile saved.'); ?></p><?php endif; ?>
              <div class="bh3-family-profile-add-top"><h3>Add a family profile</h3><?php echo self::form(); ?></div>
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

            </section>
          </div>
        </div>
        <?php return ob_get_clean();
    }
    /** Render profile-specific school or pregnancy reminders. */
    public static function render_reminders($profile_id,$profile_type) {
        if (!self::allowed()) return '';
        global $wpdb;
        $table=$wpdb->prefix.'bh_family_reminders';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return '<p class="bh3-reminder-note">Reminders will be available after the family database is updated.</p>';
        $profile_id=absint($profile_id); $user_id=get_current_user_id();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,title,reminder_date FROM `{$table}` WHERE user_id=%d AND profile_id=%d AND profile_type=%s ORDER BY reminder_date ASC LIMIT 30",$user_id,$profile_id,$profile_type),ARRAY_A);
        $kind=$profile_type==='child'?'school':'pregnancy';
        $default=$profile_type==='child'?'Primary School Application':'Antenatal class reminder';
        ob_start(); ?>
        <style>.bh3-profile-reminders{margin:12px 0 4px;padding:12px;border-radius:12px}.bh3-profile-reminders--school{background:#e8f2ec;color:#205b49}.bh3-profile-reminders--pregnancy{background:#fff0df;color:#86552f}.bh3-profile-reminder{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid currentColor}.bh3-profile-reminder span{font-size:13px;opacity:.85}.bh3-profile-reminder button{background:transparent!important;color:inherit!important;padding:2px 8px!important;min-height:30px!important}.bh3-profile-reminder-add{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:10px;align-items:end;margin-top:10px}.bh3-profile-reminder-add label{font-size:12px}.bh3-profile-reminder-add button{white-space:nowrap}.bh3-profile-reminder-add input{min-width:0}@media(max-width:720px){.bh3-profile-reminder-add{grid-template-columns:1fr}.bh3-profile-reminder-add button{width:100%}}</style>
        <div class="bh3-profile-reminders bh3-profile-reminders--<?php echo esc_attr($kind); ?>">
          <?php if ($rows) : ?><div class="bh3-profile-reminder-list">
            <?php foreach ($rows as $row) :
              $date=DateTimeImmutable::createFromFormat('!Y-m-d',$row['reminder_date'],wp_timezone());
              $today=new DateTimeImmutable('today',wp_timezone());
              $days=$date?(int)$today->diff($date)->format('%r%a'):0;
              if (!$date) { $countdown=''; } elseif ($days<0) { $countdown=abs($days).'d overdue'; } elseif ($days===0) { $countdown='Due today'; } elseif ($days>=365) { $countdown=intdiv($days,365).'y '.($days%365).'d left'; } elseif ($days>=30) { $countdown=intdiv($days,30).'m '.($days%30).'d left'; } else { $countdown=$days.'d left'; }
            ?>
              <div class="bh3-profile-reminder">
                <div><strong><?php echo esc_html(($kind==='school'?'🏫 ':'🤰 ').$row['title']); ?></strong><br><span><?php echo esc_html($countdown.' ('.wp_date(get_option('date_format'),$date?$date->getTimestamp():time()).')'); ?></span></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this reminder?');">
                  <input type="hidden" name="action" value="bh3_family_reminder_delete"><input type="hidden" name="reminder_id" value="<?php echo esc_attr(absint($row['id'])); ?>">
                  <?php wp_nonce_field('bh3_family_reminder_delete_'.absint($row['id'])); ?><button type="submit" aria-label="Remove reminder">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div><?php endif; ?>
          <form class="bh3-profile-reminder-add" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bh3_family_reminder_save"><input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>"><input type="hidden" name="profile_type" value="<?php echo esc_attr($profile_type); ?>">
            <?php wp_nonce_field('bh3_family_reminder_save_'.$profile_id); ?>
            <label><?php echo esc_html($profile_type==='child'?'School milestone':'Pregnancy reminder'); ?><input name="title" maxlength="160" required value="<?php echo esc_attr($rows?'':$default); ?>" placeholder="<?php echo esc_attr($default); ?>"></label>
            <label>Deadline or date<input type="date" name="reminder_date" required></label>
            <button type="submit">+ Add reminder</button>
          </form>
        </div>
        <?php return ob_get_clean();
    }
    public static function save_reminder() {
        if (!self::allowed()) wp_die('Reminders unavailable.','',array('response'=>403));
        $id=isset($_POST['profile_id'])?absint($_POST['profile_id']):0;
        $type=isset($_POST['profile_type'])?sanitize_key(wp_unslash($_POST['profile_type'])):'';
        if (!$id || !in_array($type,array('child','bump'),true)) wp_die('Invalid profile.','',array('response'=>400));
        check_admin_referer('bh3_family_reminder_save_'.$id);
        global $wpdb; $profiles=$wpdb->prefix.'bh_family_profiles'; $table=$wpdb->prefix.'bh_family_reminders';
        $owns=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$profiles}` WHERE id=%d AND user_id=%d AND profile_type=%s",$id,get_current_user_id(),$type));
        if (!$owns) wp_die('Profile not found.','',array('response'=>404));
        $title=isset($_POST['title'])?sanitize_text_field(wp_unslash($_POST['title'])):'';
        $date=isset($_POST['reminder_date'])?sanitize_text_field(wp_unslash($_POST['reminder_date'])):'';
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
        if (!$title || strlen($title)>160 || !$parsed || $parsed->format('Y-m-d')!==$date) wp_die('Enter a reminder title and valid date.','',array('response'=>400));
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d AND profile_id=%d",get_current_user_id(),$id));
        if ($count>=30) wp_die('This profile has reached its reminder limit.','',array('response'=>400));
        $now=current_time('mysql');
        if ($wpdb->insert($table,array('user_id'=>get_current_user_id(),'profile_id'=>$id,'profile_type'=>$type,'title'=>$title,'reminder_date'=>$date,'created_at'=>$now,'updated_at'=>$now),array('%d','%d','%s','%s','%s','%s','%s'))===false) wp_die('Unable to save reminder.','',array('response'=>500));
        wp_safe_redirect(home_url('/family/')); exit;
    }
    public static function delete_reminder() {
        if (!self::allowed()) wp_die('Reminders unavailable.','',array('response'=>403));
        $id=isset($_POST['reminder_id'])?absint($_POST['reminder_id']):0;
        check_admin_referer('bh3_family_reminder_delete_'.$id);
        global $wpdb; $table=$wpdb->prefix.'bh_family_reminders';
        $deleted=$wpdb->delete($table,array('id'=>$id,'user_id'=>get_current_user_id()),array('%d','%d'));
        if ($deleted===false) wp_die('Unable to remove reminder.','',array('response'=>500));
        wp_safe_redirect(home_url('/family/')); exit;
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
          <?php if ($type==='child') : ?><label>Gender (optional) <select name="gender"><option value="">Prefer not to say</option><option value="girl" <?php selected($record['gender'] ?? '', 'girl'); ?>>Girl</option><option value="boy" <?php selected($record['gender'] ?? '', 'boy'); ?>>Boy</option><option value="another" <?php selected($record['gender'] ?? '', 'another'); ?>>Another identity</option></select></label><?php endif; ?>
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
        $gender=isset($_POST['gender']) && is_scalar($_POST['gender']) ? sanitize_key(wp_unslash($_POST['gender'])) : '';
        if (!in_array($gender,array('','girl','boy','another'),true) || $record['profile_type']==='bump') $gender='';
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
        $gender=isset($_POST['gender']) && is_scalar($_POST['gender']) ? sanitize_key(wp_unslash($_POST['gender'])) : '';
        if (!in_array($gender,array('','girl','boy','another'),true) || $type==='bump') $gender='';
        $saved=$wpdb->insert($table,array('user_id'=>$user,'profile_type'=>$type,'display_name'=>$name,'profile_date'=>$date,'gender'=>$gender,'created_at'=>$now,'updated_at'=>$now),array('%d','%s','%s','%s','%s','%s','%s'));
        if ($saved===false) wp_die('Could not save profile.','',array('response'=>500));
        wp_safe_redirect(add_query_arg('bh3_profile_status','saved',home_url('/family/profile/')));
        exit;
    }
}
