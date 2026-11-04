<?php
if (!defined('ABSPATH')) exit;
/** Front-end family settings. Uses the same user-meta preference storage as My Hub. */
final class BH_Family_Settings_3 {
    public static function register() {
        add_shortcode('bubbahub_family_settings', array(__CLASS__, 'render'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
    }
    public static function styles() {
        if (!is_singular()) return;
        $post = get_post();
        if (!$post || !has_shortcode($post->post_content, 'bubbahub_family_settings')) return;
        wp_enqueue_style('bh3-family-hub', plugins_url('assets/family-hub.css', dirname(__DIR__, 2) . '/bubba-hub-manager.php'), array(), defined('BUBBA_HUB_MANAGER_VERSION') ? BUBBA_HUB_MANAGER_VERSION : '0.1.0');
        nocache_headers();
    }
    public static function render() {
        $preview_enabled = defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true
            && wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk';
        if (!is_user_logged_in()) return '<p>' . esc_html__('Please sign in to manage your family preferences.', 'bubba-hub-manager') . '</p>';
        if (!$preview_enabled) return '<p>' . esc_html__('Family settings are not available yet.', 'bubba-hub-manager') . '</p>';
        $admin_demo = current_user_can('manage_options');
        if (!$admin_demo && BH_Family_Identity_3::current_legacy_id() < 1) return '<p>' . esc_html__('A verified family account is required.', 'bubba-hub-manager') . '</p>';
        $verified_family = !$admin_demo;
        $preferences = BH_Family_Preferences_3::read();
        $account = wp_get_current_user();
        $details = BH_Family_Account_3::details();
        $notifications = BH_Family_Notifications_3::read();
        $privacy = BH_Family_Privacy_3::read();
        ob_start();
        ?>
        <section class="bh3-family bh3-family__settings-page">
          <p><a href="<?php echo esc_url(home_url('/family/')); ?>">← <?php echo esc_html__('Back to My Hub', 'bubba-hub-manager'); ?></a></p>
          <div class="bh3-family__settings-layout">
            <nav class="bh3-family__settings-sidebar" aria-label="<?php echo esc_attr__('Family settings navigation', 'bubba-hub-manager'); ?>">
              <div class="bh3-family__sidebar-heading"><span class="bh3-family__kicker"><?php echo esc_html__('MY HUB', 'bubba-hub-manager'); ?></span><strong><?php echo esc_html__('Family settings', 'bubba-hub-manager'); ?></strong></div>
              <a href="<?php echo esc_url(home_url('/family/')); ?>"><span aria-hidden="true">⌂</span><?php echo esc_html__('Dashboard', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-account"><span aria-hidden="true">◯</span><?php echo esc_html__('My profile', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-preferences"><span aria-hidden="true">☷</span><?php echo esc_html__('Family preferences', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-notifications"><span aria-hidden="true">♧</span><?php echo esc_html__('Notifications', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-privacy">Privacy &amp; consent</a>
              <div class="bh3-family__sidebar-footer"><?php echo esc_html__('More family settings coming soon', 'bubba-hub-manager'); ?></div>
            </nav>
            <div class="bh3-family__settings-content">
          <div class="bh3-family__grid bh3-family__private">
              <article id="bh3-account" class="bh3-family__card bh3-family__card--account">
                <div class="bh3-family__section-heading"><div><span class="bh3-family__kicker"><?php echo esc_html__('YOUR ACCOUNT', 'bubba-hub-manager'); ?></span><h2><?php echo esc_html__('My profile', 'bubba-hub-manager'); ?></h2></div></div>
                <p><?php echo esc_html__('Manage your name here. Your sign-in and security settings remain with your existing account provider.', 'bubba-hub-manager'); ?></p>
                <?php if (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'saved') : ?><p role="status"><?php echo esc_html__('Profile updated.', 'bubba-hub-manager'); ?></p><?php elseif (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'error') : ?><p role="alert"><?php echo esc_html__('Unable to update your profile.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <form class="bh3-family__account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="bh3_family_account">
                  <?php wp_nonce_field('bh3_family_account'); ?>
                  <div class="bh3-family__location-grid">
                    <label><?php echo esc_html__('First name', 'bubba-hub-manager'); ?><input type="text" name="first_name" maxlength="80" value="<?php echo esc_attr($account->first_name); ?>" <?php disabled($admin_demo); ?>></label>
                    <label><?php echo esc_html__('Last name', 'bubba-hub-manager'); ?><input type="text" name="last_name" maxlength="80" value="<?php echo esc_attr($account->last_name); ?>" <?php disabled($admin_demo); ?>></label>
                    <label><?php echo esc_html__('Display name', 'bubba-hub-manager'); ?><input type="text" name="display_name" maxlength="80" value="<?php echo esc_attr($account->display_name); ?>" <?php disabled($admin_demo); ?>></label>
                  </div>
                  <div class="bh3-family__location-grid">
                    <label><?php echo esc_html__('Email address', 'bubba-hub-manager'); ?><input type="email" value="<?php echo esc_attr($account->user_email); ?>" readonly aria-describedby="bh3-account-email-help"></label>
                  </div>
                  <h3>Additional profile details</h3>
                  <p>Optional information for your own account. These details are not shared with class leaders.</p>
                  <div class="bh3-family__location-grid">
                    <label>Date of birth<input type="date" name="date_of_birth" value="<?php echo esc_attr($details['date_of_birth']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>Address line 1<input type="text" name="address_line1" maxlength="160" value="<?php echo esc_attr($details['address_line1']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>Address line 2<input type="text" name="address_line2" maxlength="160" value="<?php echo esc_attr($details['address_line2']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>Town / city<input type="text" name="city" maxlength="100" value="<?php echo esc_attr($details['city']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>County<input type="text" name="county" maxlength="100" value="<?php echo esc_attr($details['county']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>Postcode<input type="text" name="postcode" maxlength="20" value="<?php echo esc_attr($details['postcode']); ?>" <?php disabled($admin_demo); ?>></label>
                    <label>Country<input type="text" name="country" maxlength="80" value="<?php echo esc_attr($details['country']); ?>" <?php disabled($admin_demo); ?>></label>
                  </div>
                  <p id="bh3-account-email-help" class="bh3-family__matching-note"><?php echo esc_html__('Email and password changes are managed through your secure sign-in account.', 'bubba-hub-manager'); ?></p>
                  <?php if (!$admin_demo) : ?><button type="submit"><?php echo esc_html__('Save profile', 'bubba-hub-manager'); ?></button><?php endif; ?>
                </form>
              </article>
              <article id="bh3-preferences" class="bh3-family__card bh3-family__card--preferences">
                <div class="bh3-family__section-heading"><div><span class="bh3-family__kicker"><?php echo esc_html__('PERSONALISE YOUR HUB', 'bubba-hub-manager'); ?></span><h2><?php echo esc_html__('Family preferences', 'bubba-hub-manager'); ?></h2></div></div>
                <p><?php echo esc_html__('Choose the activities, days and areas that suit your family. These will help tailor future recommendations.', 'bubba-hub-manager'); ?></p>
                <?php if ($admin_demo) : ?><p class="bh3-family__preview-note"><?php echo esc_html__('Administrator preview: explore the options below. Saving is available to verified family accounts.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <?php if ($verified_family && isset($_GET['bh3_preferences_saved'])) : ?><p role="status"><?php echo esc_html__('Preferences saved.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <form class="bh3-family__preferences-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="bh3_family_preferences">
                  <?php wp_nonce_field('bh3_family_preferences'); ?>
                  <fieldset><legend><?php echo esc_html__('What interests your family?', 'bubba-hub-manager'); ?></legend>
                    <div class="bh3-family__choice-grid">
                      <?php foreach (BH_Family_Preferences_3::interests() as $key => $label) : ?>
                        <label><input type="checkbox" name="interests[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $preferences['interests'], true)); ?> <?php disabled($admin_demo); ?>><span><?php echo esc_html($label); ?></span></label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                  <fieldset><legend><?php echo esc_html__('Which days work best?', 'bubba-hub-manager'); ?></legend>
                    <div class="bh3-family__choice-grid bh3-family__choice-grid--days">
                      <?php foreach (BH_Family_Preferences_3::days() as $key => $label) : ?>
                        <label><input type="checkbox" name="days[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $preferences['days'], true)); ?> <?php disabled($admin_demo); ?>><span><?php echo esc_html($label); ?></span></label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                  <fieldset><legend><?php echo esc_html__('Where would you like to explore?', 'bubba-hub-manager'); ?></legend>
                    <div class="bh3-family__location-grid">
                      <label>Preferred region<select name="region" <?php disabled($admin_demo); ?>><option value="">Any region</option><?php foreach (array('Devon','Cornwall') as $region) : ?><option value="<?php echo esc_attr($region); ?>" <?php selected($preferences['region'],$region); ?>><?php echo esc_html($region); ?></option><?php endforeach; ?></select></label>
                      <label><?php echo esc_html__('Town or area', 'bubba-hub-manager'); ?><input type="text" name="town" maxlength="80" value="<?php echo esc_attr($preferences['town']); ?>" placeholder="<?php echo esc_attr__('e.g. Torquay', 'bubba-hub-manager'); ?>" <?php disabled($admin_demo); ?>></label>
                      <label><?php echo esc_html__('Postcode (optional)', 'bubba-hub-manager'); ?><input type="text" name="postcode" maxlength="9" value="<?php echo esc_attr($preferences['postcode']); ?>" placeholder="<?php echo esc_attr__('e.g. TQ2', 'bubba-hub-manager'); ?>" <?php disabled($admin_demo); ?>></label>
                      <label><?php echo esc_html__('Travel distance', 'bubba-hub-manager'); ?><select name="radius" <?php disabled($admin_demo); ?>><?php foreach (array(5,10,15,25,50) as $miles) : ?><option value="<?php echo esc_attr($miles); ?>" <?php selected((int)$preferences['radius'], $miles); ?>><?php echo esc_html(sprintf(__('%d miles', 'bubba-hub-manager'), $miles)); ?></option><?php endforeach; ?></select></label>
                    </div>
                  </fieldset>
                  <div class="bh3-family__location-grid">
                    <label>Maximum session price (£, optional)<input type="number" name="max_price" min="0" max="10000" step="0.01" value="<?php echo esc_attr($preferences['max_price'] === null ? '' : $preferences['max_price']); ?>" placeholder="No maximum" <?php disabled($admin_demo); ?>></label>
                  </div>
                  <?php if (isset($_GET['bh3_preferences_error'])) : ?><p role="alert">Please enter a valid maximum price.</p><?php endif; ?>
                  <div class="bh3-family__choice-grid">
                    <label><input type="checkbox" name="free_only" value="1" <?php checked($preferences['free_only']); ?> <?php disabled($admin_demo); ?>><span><?php echo esc_html__('Free activities only', 'bubba-hub-manager'); ?></span></label>
                    <label><input type="checkbox" name="term_time" value="1" <?php checked($preferences['term_time']); ?> <?php disabled($admin_demo); ?>><span><?php echo esc_html__('Term-time activities', 'bubba-hub-manager'); ?></span></label>
                  </div>
                  <?php if (!$admin_demo) : ?><button type="submit"><?php echo esc_html__('Save my preferences', 'bubba-hub-manager'); ?></button><?php endif; ?>
                </form>
              </article>

              <article id="bh3-notifications" class="bh3-family__card bh3-family__card--notifications">
                <div class="bh3-family__section-heading"><div><span class="bh3-family__kicker"><?php echo esc_html__('STAY IN THE LOOP', 'bubba-hub-manager'); ?></span><h2><?php echo esc_html__('Notifications', 'bubba-hub-manager'); ?></h2></div></div>
                <p><?php echo esc_html__('Choose which optional updates you would like to receive. Essential account and booking messages are separate.', 'bubba-hub-manager'); ?></p>
                <?php if ($admin_demo) : ?><p class="bh3-family__preview-note"><?php echo esc_html__('Administrator preview: notification choices cannot be saved here.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <?php if (!$admin_demo && isset($_GET['bh3_notifications_saved'])) : ?><p role="status"><?php echo esc_html__('Notification choices saved.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <form class="bh3-family__notification-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="bh3_family_notifications">
                  <?php wp_nonce_field('bh3_family_notifications'); ?>
                  <div class="bh3-family__notification-list">
                    <?php foreach (BH_Family_Notifications_3::choices() as $key=>$details) : ?>
                      <label class="bh3-family__notification-option">
                        <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($notifications[$key]); ?> <?php disabled($admin_demo); ?>>
                        <span><strong><?php echo esc_html($details[0]); ?></strong><small><?php echo esc_html($details[1]); ?></small></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <h3><?php echo esc_html__('Bubba Hub Round Up', 'bubba-hub-manager'); ?></h3>
                  <label class="bh3-family__notification-option">
                    <input type="checkbox" name="newsletter" value="1" <?php checked($notifications['newsletter']); ?> <?php disabled($admin_demo); ?>>
                    <span><strong><?php echo esc_html__('I would like the Bubba Hub Round Up', 'bubba-hub-manager'); ?></strong><small><?php echo esc_html__('An optional newsletter about local family activities. This preference does not subscribe you until the mailing system is connected.', 'bubba-hub-manager'); ?></small></span>
                  </label>
                  <?php if (!$admin_demo) : ?><button type="submit"><?php echo esc_html__('Save notification choices', 'bubba-hub-manager'); ?></button><?php endif; ?>
                </form>
              </article>
              <article id="bh3-privacy" class="bh3-family__card bh3-family__card--notifications">
                <h2>Privacy &amp; consent</h2>
                <p>Manage optional uses of your information. Essential account messages and data needed for requested services are separate.</p>
                <?php if (isset($_GET['bh3_privacy_saved']) && !$admin_demo) : ?><p role="status">Privacy choices saved.</p><?php endif; ?>
                <form class="bh3-family__notification-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="bh3_family_privacy">
                  <?php wp_nonce_field('bh3_family_privacy'); ?>
                  <div class="bh3-family__notification-list">
                    <?php foreach (BH_Family_Privacy_3::choices() as $key => $details) : ?>
                      <label class="bh3-family__notification-option">
                        <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($privacy[$key]); ?> <?php disabled($admin_demo); ?>>
                        <span><strong><?php echo esc_html($details[0]); ?></strong><small><?php echo esc_html($details[1]); ?></small></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <p>These choices are stored locally; connections to existing services are not enabled yet.</p>
                  <?php if (!$admin_demo) : ?><button type="submit">Save privacy choices</button><?php endif; ?>
                </form>
                <p>For data corrections or removal requests: <a href="mailto:contact@bubbahub.co.uk">contact@bubbahub.co.uk</a></p>
              </article>
          </div>
            </div>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
