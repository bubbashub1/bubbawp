<?php
/**
 * Family Hub 3.0 presentation-only foundation.
 * No legacy auth assumptions, schema writes, or personal data reads.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Family_Hub_3 {
    public static function register() {
        add_shortcode( 'bubbahub_family_hub', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
        add_action( 'template_redirect', array( __CLASS__, 'private_cache_headers' ), 0 );
    }

    public static function private_cache_headers() {
        if (!defined('BH3_FAMILY_PRIVATE_PREVIEW') || BH3_FAMILY_PRIVATE_PREVIEW !== true || !is_singular()) return;
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'bubbahub_family_hub')) nocache_headers();
    }

    public static function styles() {
        if ( ! is_singular() ) { return; }
        $post = get_post();
        if ( ! $post || ! has_shortcode( $post->post_content, 'bubbahub_family_hub' ) ) { return; }
        wp_enqueue_style(
            'bh3-family-hub',
            plugins_url( 'assets/family-hub.css', dirname( __DIR__, 2 ) . '/bubba-hub-manager.php' ),
            array(),
            defined( 'BUBBA_HUB_MANAGER_VERSION' ) ? BUBBA_HUB_MANAGER_VERSION : '0.1.0'
        );
    }

    public static function render() {
        // FluentAuth should establish the standard WordPress session; no plugin-specific hooks assumed.
        // This is an authentication UX gate, NOT permission to access legacy bh_users records.
        $wp_signed_in = is_user_logged_in();
        // Do not query legacy identity or data until the family module passes staging QA.
        // BH_Family_Identity_3 is available for future gated integration.
        $return_url = is_singular() ? get_permalink() : home_url( '/' );
        $login_url = wp_login_url( $return_url );
        $preview_enabled = defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true;
        // Admins can inspect the complete front-end layout without impersonating a family
        // or reading or changing any real family records.
        $admin_demo = $preview_enabled && $wp_signed_in && current_user_can('manage_options');
        $verified_family = $preview_enabled && $wp_signed_in && !$admin_demo && BH_Family_Identity_3::current_legacy_id() > 0;
        $private_preview = $preview_enabled && $wp_signed_in;
        $preferences = BH_Family_Preferences_3::read();

        $saved = $verified_family ? BH_Family_Read_Model_3::saved_ids() : array();
        $planner = $verified_family ? BH_Family_Read_Model_3::planner_items() : array();
        // Native WordPress family profiles are owned by the signed-in WP account.
        // Read only that account's rows; do not rely on legacy identity mapping.
        $profiles = array('children' => array(), 'bumps' => array());
        $native_profile_error = false;
        if ($wp_signed_in) {
            global $wpdb;
            $native_table = $wpdb->prefix . 'bh_family_profiles';
            $native_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($native_table))) === $native_table;
            if ($native_exists) {
                $native_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, profile_type, display_name, profile_date, gender FROM `{$native_table}` WHERE user_id = %d ORDER BY id DESC LIMIT 70",
                    get_current_user_id()
                ), ARRAY_A);
                if (is_array($native_rows)) {
                    foreach ($native_rows as $row) {
                        if (($row['profile_type'] ?? '') === 'child') {
                            $profiles['children'][] = array(
                                'id' => absint($row['id']),
                                'name' => (string) $row['display_name'],
                                'date_of_birth' => (string) $row['profile_date'],
                                'gender' => (string) ($row['gender'] ?? ''),
                            );
                        } elseif (($row['profile_type'] ?? '') === 'bump') {
                            $profiles['bumps'][] = array(
                                'id' => absint($row['id']),
                                'nickname' => (string) $row['display_name'],
                                'due_date' => (string) $row['profile_date'],
                            );
                        }
                    }
                } else {
                    $native_profile_error = true;
                }
            }
        }
        $family_read_error = ($verified_family && (is_wp_error($saved) || is_wp_error($planner))) || $native_profile_error;
        $saved = is_array($saved) ? $saved : array();
        $planner = is_array($planner) ? $planner : array();
        $planned_ids = array_map('absint', array_column($planner, 'activity_id'));
        $visited_count = count(array_filter($planner, static function($item) { return !empty($item['visited']); }));
        $profiles = is_array($profiles) && isset($profiles['children'], $profiles['bumps']) && is_array($profiles['children']) && is_array($profiles['bumps']) ? $profiles : array('children' => array(), 'bumps' => array());
        $suggestions = $private_preview ? BH_Family_Recommendations_3::fetch($preferences, $verified_family ? $profiles : array()) : array();
        $show_saved = $private_preview && isset($_GET['bh3_show_saved']) && is_scalar($_GET['bh3_show_saved']) && (string) $_GET['bh3_show_saved'] === '1';
        $show_planner = $private_preview && isset($_GET['bh3_show_planner']) && is_scalar($_GET['bh3_show_planner']) && (string) $_GET['bh3_show_planner'] === '1';
        $planner_filter = isset($_GET['bh3_planner_filter']) && is_scalar($_GET['bh3_planner_filter']) ? sanitize_key(wp_unslash($_GET['bh3_planner_filter'])) : 'all';
        if (!in_array($planner_filter, array('all', 'to_visit', 'visited'), true)) $planner_filter = 'all';
        $planner_search = isset($_GET['bh3_planner_search']) && is_scalar($_GET['bh3_planner_search']) ? substr(sanitize_text_field(wp_unslash($_GET['bh3_planner_search'])), 0, 80) : '';
        $filtered_planner = array_values(array_filter($planner, static function($item) use ($planner_filter) {
            return $planner_filter === 'all' || ($planner_filter === 'visited' ? !empty($item['visited']) : empty($item['visited']));
        }));
        $saved_search = isset($_GET['bh3_saved_search']) && is_scalar($_GET['bh3_saved_search']) ? substr(sanitize_text_field(wp_unslash($_GET['bh3_saved_search'])), 0, 80) : '';
        $activity_ids = array_merge($saved, array_column($planner, 'activity_id'));
        $activity_titles = $verified_family ? BH_Family_Read_Model_3::activity_titles($activity_ids) : array();
        if (is_wp_error($activity_titles)) {
            $family_read_error = true;
            $activity_titles = array();
        }
        $activity_titles = is_array($activity_titles) ? $activity_titles : array();
        $activity_links = $verified_family ? BH_Family_Read_Model_3::activity_links($activity_ids) : array();
        $planner_sort = isset($_GET['bh3_planner_sort']) && is_scalar($_GET['bh3_planner_sort']) ? sanitize_key(wp_unslash($_GET['bh3_planner_sort'])) : 'recent';
        if (!in_array($planner_sort, array('recent', 'name'), true)) $planner_sort = 'recent';
        if ($planner_sort === 'name') usort($filtered_planner, static function($a, $b) use ($activity_titles) {
            $left = $activity_titles[absint($a['activity_id'] ?? 0)] ?? '';
            $right = $activity_titles[absint($b['activity_id'] ?? 0)] ?? '';
            return strcasecmp($left, $right);
        });
        if ($planner_search !== '') $filtered_planner = array_values(array_filter($filtered_planner, static function($item) use ($activity_titles, $planner_search) {
            $title = $activity_titles[absint($item['activity_id'] ?? 0)] ?? '';
            return $title !== '' && stripos($title, $planner_search) !== false;
        }));
        $saved_sort = isset($_GET['bh3_saved_sort']) && is_scalar($_GET['bh3_saved_sort']) ? sanitize_key(wp_unslash($_GET['bh3_saved_sort'])) : 'recent';
        if (!in_array($saved_sort, array('recent', 'name'), true)) $saved_sort = 'recent';
        $filtered_saved = $saved_search === '' ? $saved : array_values(array_filter($saved, static function($id) use ($activity_titles, $saved_search) {
            $title = $activity_titles[absint($id)] ?? '';
            return $title !== '' && stripos($title, $saved_search) !== false;
        }));
        if ($saved_sort === 'name') usort($filtered_saved, static function($a, $b) use ($activity_titles) {
            return strcasecmp($activity_titles[absint($a)] ?? '', $activity_titles[absint($b)] ?? '');
        });
        $sections = array(
            array( 'My family', 'Family profiles will appear here once account mapping is verified.' ),
            array( 'Saved activities', 'Your favourites will connect to existing activity IDs.' ),
            array( 'Family planner', 'Calendar and recurring sessions are coming after planner parity testing.' ),
            array( 'Discover', 'Find age-suitable activities without exposing family details.' ),
        );
        ob_start();
        ?>
        <section class="bh3-family bh3-family--sidebar" aria-labelledby="bh3-family-heading">
          <div class="bh3-family__shell">
            <nav class="bh3-family__side" aria-label="Family Hub navigation">
              <strong>My Hub</strong>
              <a href="#bh3-family-heading">Overview</a>
              <a href="#bh3-my-family">My family</a>
              <a href="#bh3-saved">Favourites</a>
              <a href="#bh3-planner">Planner</a>
              <a href="#bh3-suggestions">Discover</a>
              <a href="<?php echo esc_url( home_url( '/family/settings/' ) ); ?>">Settings</a>
              <a href="<?php echo esc_url( home_url( '/support/' ) ); ?>">Help &amp; Support</a>
            </nav>
            <div class="bh3-family__body">
          <div class="bh3-family__hero">
            <p class="bh3-family__eyebrow"><?php echo esc_html__( 'Bubba Hub · My Hub', 'bubba-hub-manager' ); ?></p>
            <h1 id="bh3-family-heading"><?php echo esc_html__( 'Your family, all in one place', 'bubba-hub-manager' ); ?></h1>
            <p><?php echo esc_html__( 'Plan days out, keep favourite activities together and make family time easier.', 'bubba-hub-manager' ); ?></p>
          </div>
          <?php if ( ! $wp_signed_in ) : ?>
            <div class="bh3-family__notice" role="status">
              <p><?php echo esc_html__( 'Sign in to access your Family Hub. Your existing family data will be connected after account verification.', 'bubba-hub-manager' ); ?></p>
              <p><a class="bh3-family__login" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Sign in', 'bubba-hub-manager' ); ?></a></p>
            </div>
          <?php else : ?>
            <div class="bh3-family__notice" role="status"><?php echo esc_html__( 'Signed in. Family Hub is in preview mode; changes require staging verification.', 'bubba-hub-manager' ); ?></div>
          <?php endif; ?>
          <?php if ($admin_demo) : ?>
            <div class="bh3-family__notice" role="status"><strong><?php echo esc_html__('Administrator layout preview', 'bubba-hub-manager'); ?></strong> — <?php echo esc_html__('No family account is linked. This is a read-only preview with empty sections; profile changes and activity actions are disabled.', 'bubba-hub-manager'); ?></div>
          <?php endif; ?>
          <?php if ($family_read_error) : ?>
            <div class="bh3-family__notice" role="alert"><?php echo esc_html__('Your family information is temporarily unavailable. Please try again later.', 'bubba-hub-manager'); ?></div>
          <?php endif; ?>
          <?php if ($private_preview && !$family_read_error) : ?>
            <div class="bh3-family__notice" role="status">
              <?php echo esc_html(sprintf('Your Hub: %d saved activities, %d planned activities, %d child profiles.', count($saved), count($planner), count($profiles['children']))); ?>
            </div>
          <?php endif; ?>
          <?php if ($private_preview && !$family_read_error) : ?>
            <nav class="bh3-family__quick-nav" aria-label="<?php echo esc_attr__('Family Hub sections', 'bubba-hub-manager'); ?>">
              <a href="#bh3-my-family"><?php echo esc_html__('My family', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-saved"><?php echo esc_html__('Favourites', 'bubba-hub-manager'); ?></a>
              <a href="<?php echo esc_url(home_url('/family/settings/')); ?>"><?php echo esc_html__('Settings', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-suggestions"><?php echo esc_html__('Suggested', 'bubba-hub-manager'); ?></a>
              <a href="#bh3-planner"><?php echo esc_html__('Planner', 'bubba-hub-manager'); ?></a>
            </nav>
            <div class="bh3-family__grid bh3-family__private" aria-label="<?php echo esc_attr__('Your private family overview', 'bubba-hub-manager'); ?>">
              <article id="bh3-my-family" class="bh3-family__card bh3-family__card--family">
                <div class="bh3-family__section-heading"><div><span class="bh3-family__kicker"><?php echo esc_html__('YOUR HOUSEHOLD', 'bubba-hub-manager'); ?></span><h2><?php echo esc_html__('My family', 'bubba-hub-manager'); ?></h2></div><span class="bh3-family__count"><?php echo esc_html(sprintf(__('%d profiles', 'bubba-hub-manager'), count($profiles['children']) + count($profiles['bumps']))); ?></span></div>
                <p><?php echo esc_html(sprintf(__('%1$d child profiles · %2$d pregnancy profiles', 'bubba-hub-manager'), count($profiles['children']), count($profiles['bumps']))); ?></p>
                <div class="bh3-family__profiles">
                  <h3><?php echo esc_html__('Children', 'bubba-hub-manager'); ?></h3>
                  <?php if (empty($profiles['children'])) : ?>
                    <div class="bh3-family__empty-profile"><span class="bh3-family__avatar" aria-hidden="true">👶</span><div><strong><?php echo esc_html__('Your children', 'bubba-hub-manager'); ?></strong><p><?php echo esc_html__('Add a child profile to see them here.', 'bubba-hub-manager'); ?></p></div></div>
                  <?php else : ?>
                    <ul class="bh3-family__profile-list">
                      <?php foreach ($profiles['children'] as $child) : ?>
                        <li><span class="bh3-family__avatar" aria-hidden="true">👶</span><strong><?php echo esc_html($child['name'] ?: __('Child profile', 'bubba-hub-manager')); ?></strong>
                        <?php if (!empty($child['date_of_birth'])) : ?><span><?php echo esc_html(sprintf(__('Born %s', 'bubba-hub-manager'), wp_date(get_option('date_format'), strtotime($child['date_of_birth'])))); ?></span><?php endif; ?><?php if (class_exists('BH_Family_Native_Profiles_3')) echo BH_Family_Native_Profiles_3::render_reminders($child['id'],'child'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                  <h3><?php echo esc_html__('Pregnancy profiles', 'bubba-hub-manager'); ?></h3>
                  <?php if (empty($profiles['bumps'])) : ?>
                    <div class="bh3-family__empty-profile"><span class="bh3-family__avatar" aria-hidden="true">🍼</span><div><strong><?php echo esc_html__('Expecting a baby?', 'bubba-hub-manager'); ?></strong><p><?php echo esc_html__('Add a pregnancy profile to see due dates here.', 'bubba-hub-manager'); ?></p></div></div>
                  <?php else : ?>
                    <ul class="bh3-family__profile-list">
                      <?php foreach ($profiles['bumps'] as $bump) : ?>
                        <li><span class="bh3-family__avatar" aria-hidden="true">🍼</span><strong><?php echo esc_html($bump['nickname'] ?: __('Pregnancy profile', 'bubba-hub-manager')); ?></strong>
                        <?php if (!empty($bump['due_date'])) : ?><span><?php echo esc_html(sprintf(__('Due %s', 'bubba-hub-manager'), wp_date(get_option('date_format'), strtotime($bump['due_date'])))); ?></span><?php endif; ?><?php if (class_exists('BH_Family_Native_Profiles_3')) echo BH_Family_Native_Profiles_3::render_reminders($bump['id'],'bump'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
                <?php if ($wp_signed_in) : ?><p class="bh3-family__manage-profiles"><a href="<?php echo esc_url(home_url('/family/profile/')); ?>"><?php echo esc_html__('Manage family profiles', 'bubba-hub-manager'); ?> →</a></p><?php endif; ?>
                <?php if (!$admin_demo && class_exists('BH_Family_Native_Profiles_3') && BH_Family_Native_Profiles_3::allowed()) : ?>
                  <div class="bh3-family__create-profiles">
                    <h3><?php echo esc_html__('Add a family profile', 'bubba-hub-manager'); ?></h3>
                    <?php echo BH_Family_Native_Profiles_3::form(); // Escaped fields and nonce generated by shortcode. ?>
                  </div>
                <?php endif; ?>
                <?php if (!$admin_demo && !BH_Family_Native_Profiles_3::allowed() && BH_Family_Profile_Editor_3::creation_enabled()) : ?>
                  <div class="bh3-family__create-profiles">
                    <h3><?php echo esc_html__('Add a family profile', 'bubba-hub-manager'); ?></h3>
                    <?php foreach (array('child' => array(__('Add child', 'bubba-hub-manager'), __('Child name', 'bubba-hub-manager'), __('Date of birth', 'bubba-hub-manager')), 'bump' => array(__('Add pregnancy', 'bubba-hub-manager'), __('Nickname', 'bubba-hub-manager'), __('Due date', 'bubba-hub-manager'))) as $profile_type => $profile_fields) : ?>
                      <form class="bh3-family__edit" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <h4><?php echo esc_html($profile_fields[0]); ?></h4>
                        <input type="hidden" name="action" value="bh3_family_create_profile">
                        <input type="hidden" name="bh3_profile_type" value="<?php echo esc_attr($profile_type); ?>">
                        <?php wp_nonce_field('bh3_family_profile_create'); ?>
                        <label for="bh3-create-<?php echo esc_attr($profile_type); ?>-name"><?php echo esc_html($profile_fields[1]); ?></label>
                        <input id="bh3-create-<?php echo esc_attr($profile_type); ?>-name" name="bh3_profile_name" type="text" maxlength="100" required>
                        <label for="bh3-create-<?php echo esc_attr($profile_type); ?>-date"><?php echo esc_html($profile_fields[2]); ?></label>
                        <input id="bh3-create-<?php echo esc_attr($profile_type); ?>-date" name="bh3_profile_date" type="date" required>
                        <button type="submit"><?php echo esc_html($profile_fields[0]); ?></button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!$admin_demo && BH_Family_Profile_Editor_3::enabled()) : ?>
                  <?php if (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'saved') : ?>
                    <p role="status"><?php echo esc_html__('Profile updated.', 'bubba-hub-manager'); ?></p>
                  <?php elseif (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'error') : ?>
                    <p role="alert"><?php echo esc_html__('Profile could not be updated.', 'bubba-hub-manager'); ?></p>
                  <?php endif; ?>
                  <?php foreach (array('child' => $profiles['children'], 'bump' => $profiles['bumps']) as $date_type => $date_profiles) : ?>
                    <?php foreach ($date_profiles as $date_profile) : ?>
                      <?php $date_field = $date_type === 'child' ? 'date_of_birth' : 'due_date'; ?>
                      <form class="bh3-family__edit" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="bh3_family_profile_date">
                        <input type="hidden" name="bh3_profile_type" value="<?php echo esc_attr($date_type); ?>">
                        <input type="hidden" name="bh3_profile_id" value="<?php echo esc_attr(absint($date_profile['id'])); ?>">
                        <?php wp_nonce_field('bh3_family_profile_edit'); ?>
                        <label for="bh3-date-<?php echo esc_attr($date_type . '-' . absint($date_profile['id'])); ?>">
                          <?php echo esc_html(sprintf($date_type === 'child' ? __('Birth date for %s', 'bubba-hub-manager') : __('Due date for %s', 'bubba-hub-manager'), $date_type === 'child' ? $date_profile['name'] : $date_profile['nickname'])); ?>
                        </label>
                        <input id="bh3-date-<?php echo esc_attr($date_type . '-' . absint($date_profile['id'])); ?>" name="bh3_profile_date" type="date" required value="<?php echo esc_attr($date_profile[$date_field] ?? ''); ?>">
                        <button type="submit"><?php echo esc_html__('Save date', 'bubba-hub-manager'); ?></button>
                      </form>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                  <?php foreach ($profiles['children'] as $child) : ?>
                    <form class="bh3-family__edit" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                      <input type="hidden" name="action" value="bh3_family_child_name">
                      <input type="hidden" name="bh3_child_id" value="<?php echo esc_attr(absint($child['id'])); ?>">
                      <?php wp_nonce_field('bh3_family_profile_edit'); ?>
                      <label for="bh3-child-<?php echo esc_attr(absint($child['id'])); ?>"><?php echo esc_html__('Child profile name', 'bubba-hub-manager'); ?></label>
                      <input id="bh3-child-<?php echo esc_attr(absint($child['id'])); ?>" name="bh3_child_name" type="text" maxlength="100" required value="<?php echo esc_attr($child['name']); ?>">
                      <button type="submit"><?php echo esc_html__('Save name', 'bubba-hub-manager'); ?></button>
                    </form>
                  <?php endforeach; ?>
                  <?php foreach ($profiles['bumps'] as $bump) : ?>
                    <form class="bh3-family__edit" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                      <input type="hidden" name="action" value="bh3_family_bump_name">
                      <input type="hidden" name="bh3_bump_id" value="<?php echo esc_attr(absint($bump['id'])); ?>">
                      <?php wp_nonce_field('bh3_family_profile_edit'); ?>
                      <label for="bh3-bump-<?php echo esc_attr(absint($bump['id'])); ?>"><?php echo esc_html__('Pregnancy profile nickname', 'bubba-hub-manager'); ?></label>
                      <input id="bh3-bump-<?php echo esc_attr(absint($bump['id'])); ?>" name="bh3_bump_name" type="text" maxlength="100" required value="<?php echo esc_attr($bump['nickname']); ?>">
                      <button type="submit"><?php echo esc_html__('Save nickname', 'bubba-hub-manager'); ?></button>
                    </form>
                  <?php endforeach; ?>
                <?php else : ?>
                  <p><?php echo esc_html__('Profile editing is not enabled in this preview.', 'bubba-hub-manager'); ?></p>
                <?php endif; ?>
              </article>
            </div>
          <?php endif; ?>
          <?php if ($verified_family && BH_Family_Actions_3::enabled() && isset($_GET['bh3_activity_status'])) : ?>
            <p class="bh3-family__notice" role="status"><?php echo esc_html($_GET['bh3_activity_status'] === 'saved' ? __('Activity updated.', 'bubba-hub-manager') : __('Activity could not be updated.', 'bubba-hub-manager')); ?></p>
          <?php endif; ?>
          <div class="bh3-family__grid">
            <?php foreach ( $sections as $section ) : ?>
              <?php if ($private_preview && in_array($section[0], array('My family', 'Saved activities', 'Family planner'), true)) continue; ?>
              <article id="bh3-suggestions" class="bh3-family__card bh3-family__card--suggestions">
                <div class="bh3-family__section-heading"><div><span class="bh3-family__kicker"><?php echo esc_html__('DISCOVER', 'bubba-hub-manager'); ?></span><h2><?php echo esc_html__('Suggested activities for your family', 'bubba-hub-manager'); ?></h2></div></div>
                <p><?php echo esc_html__('Published activities matched against your chosen interests, days and area.', 'bubba-hub-manager'); ?></p>
                <?php if ($admin_demo) : ?><p class="bh3-family__preview-note"><?php echo esc_html__('Admin preview: showing recently published activities without personal preferences.', 'bubba-hub-manager'); ?></p><?php endif; ?>
                <?php if (empty($suggestions)) : ?>
                  <p class="bh3-family__suggestions-empty"><?php echo esc_html__('No matching activities yet. Try changing your interests, days or area.', 'bubba-hub-manager'); ?></p>
                <?php else : ?>
                  <div class="bh3-family__suggestion-grid">
                    <?php foreach ($suggestions as $suggestion) : ?>
                      <article class="bh3-family__suggestion">
                        <span class="bh3-family__suggestion-category"><?php echo esc_html($suggestion['category'] ?: __('Family activity', 'bubba-hub-manager')); ?></span>
                        <h3><?php echo esc_html($suggestion['title']); ?></h3>
                        <?php if (!empty($suggestion['town'])) : ?><p><?php echo esc_html($suggestion['town']); ?></p><?php endif; ?>
                        <?php if (!empty($suggestion['is_free'])) : ?><span class="bh3-family__free-tag"><?php echo esc_html__('Free', 'bubba-hub-manager'); ?></span><?php endif; ?>
                        <?php if (!empty($suggestion['slug'])) : ?><a href="<?php echo esc_url(home_url('/activity/' . rawurlencode($suggestion['slug']) . '/')); ?>"><?php echo esc_html__('View activity', 'bubba-hub-manager'); ?> →</a><?php endif; ?>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <p class="bh3-family__matching-note"><?php echo esc_html__('Child ages are matched when profiles are available. Travel radius applies only when your postcode can be matched to a unique venue location.', 'bubba-hub-manager'); ?></p>
              </article>
              <?php if (!$admin_demo && class_exists('BH_Family_Native_Favourites_3') && BH_Family_Native_Favourites_3::allowed()) : ?>
                <article id="bh3-native-favourites" class="bh3-family__card bh3-family__card--saved">
                  <?php echo BH_Family_Native_Favourites_3::render(); ?>
                </article>
              <?php endif; ?>
              <?php if (!class_exists('BH_Family_Native_Favourites_3') || !BH_Family_Native_Favourites_3::allowed()) : ?>
              <article id="bh3-saved" class="bh3-family__card bh3-family__card--saved">
                <h2><?php echo esc_html__('Saved activities', 'bubba-hub-manager'); ?></h2>
                <?php if (!empty($saved) || $saved_search !== '') : ?>
                <form class="bh3-family__search" method="get" action="<?php echo esc_url($return_url); ?>">
                  <label for="bh3-saved-search"><?php echo esc_html__('Search saved activities', 'bubba-hub-manager'); ?></label>
                  <input id="bh3-saved-search" type="search" name="bh3_saved_search" maxlength="80" value="<?php echo esc_attr($saved_search); ?>" placeholder="<?php echo esc_attr__('Activity name', 'bubba-hub-manager'); ?>">
                  <input type="hidden" name="bh3_saved_sort" value="<?php echo esc_attr($saved_sort); ?>">
                  <button type="submit"><?php echo esc_html__('Search', 'bubba-hub-manager'); ?></button>
                  <?php if ($saved_search !== '') : ?><a href="<?php echo esc_url(add_query_arg('bh3_saved_sort', $saved_sort, $return_url)); ?>"><?php echo esc_html__('Clear', 'bubba-hub-manager'); ?></a><?php endif; ?>
                </form>
                <form class="bh3-family__sort" method="get" action="<?php echo esc_url($return_url); ?>">
                  <label for="bh3-saved-sort"><?php echo esc_html__('Sort favourites', 'bubba-hub-manager'); ?></label>
                  <select id="bh3-saved-sort" name="bh3_saved_sort">
                    <option value="recent" <?php selected($saved_sort, 'recent'); ?>><?php echo esc_html__('Recently saved', 'bubba-hub-manager'); ?></option>
                    <option value="name" <?php selected($saved_sort, 'name'); ?>><?php echo esc_html__('Activity name A–Z', 'bubba-hub-manager'); ?></option>
                  </select>
                  <input type="hidden" name="bh3_saved_search" value="<?php echo esc_attr($saved_search); ?>">
                  <button type="submit"><?php echo esc_html__('Apply', 'bubba-hub-manager'); ?></button>
                </form>
                <?php endif; ?>
                <?php if (empty($filtered_saved)) : ?>
                  <p><?php echo esc_html($saved_search !== '' ? __('No favourites match your search.', 'bubba-hub-manager') : __('Keep your favourite classes and days out together here.', 'bubba-hub-manager')); ?></p>
                <?php else : ?>
                  <ul class="bh3-family__items">
                    <?php foreach (array_slice($filtered_saved, 0, $show_saved ? 200 : 8) as $activity_id) : ?>
                      <li>
                        <span><?php $saved_label = $activity_titles[absint($activity_id)] ?? sprintf(__('Activity #%d', 'bubba-hub-manager'), absint($activity_id)); ?><?php if (!empty($activity_links[absint($activity_id)])) : ?><a href="<?php echo esc_url($activity_links[absint($activity_id)]); ?>"><?php echo esc_html($saved_label); ?></a><?php else : ?><?php echo esc_html($saved_label); ?><?php endif; ?></span>
                        <?php if (!$admin_demo && BH_Family_Actions_3::enabled()) : ?>
                          <?php foreach (array('plan_saved' => __('Add to planner', 'bubba-hub-manager'), 'visit_saved' => __('Mark visited', 'bubba-hub-manager')) as $add_action => $add_label) : ?>
                            <?php if ($add_action !== 'plan_saved' || !in_array(absint($activity_id), $planned_ids, true)) : ?>
                              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="bh3_family_activity_action">
                                <input type="hidden" name="bh3_action" value="<?php echo esc_attr($add_action); ?>">
                                <input type="hidden" name="bh3_activity_id" value="<?php echo esc_attr(absint($activity_id)); ?>">
                                <?php wp_nonce_field('bh3_family_activity_action'); ?>
                                <button type="submit" class="bh3-family__action"><?php echo esc_html($add_label); ?></button>
                              </form>
                            <?php endif; ?>
                          <?php endforeach; ?>
                          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="bh3_family_activity_action">
                            <input type="hidden" name="bh3_action" value="unsave">
                            <input type="hidden" name="bh3_activity_id" value="<?php echo esc_attr(absint($activity_id)); ?>">
                            <?php wp_nonce_field('bh3_family_activity_action'); ?>
                            <button type="submit" class="bh3-family__action"><?php echo esc_html__('Remove', 'bubba-hub-manager'); ?></button>
                          </form>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                  <?php if (count($filtered_saved) > 8) : ?><p><a href="<?php echo esc_url(add_query_arg(array('bh3_show_saved' => $show_saved ? '0' : '1', 'bh3_saved_search' => $saved_search, 'bh3_saved_sort' => $saved_sort), $return_url)); ?>"><?php echo esc_html($show_saved ? __('Show fewer favourites', 'bubba-hub-manager') : sprintf(__('View all %d favourites', 'bubba-hub-manager'), count($filtered_saved))); ?></a></p><?php endif; ?>
                <?php endif; ?>
              </article>
              <?php endif; ?>
              <article id="bh3-planner" class="bh3-family__card bh3-family__card--planner">
                <h2><?php echo esc_html__('Family planner', 'bubba-hub-manager'); ?></h2>
                <p class="bh3-family__planner-summary"><?php echo esc_html(sprintf(__('%1$d planned · %2$d visited', 'bubba-hub-manager'), count($planner), $visited_count)); ?></p>
                <?php if (!empty($planner) || $planner_search !== '' || $planner_filter !== 'all') : ?>
                <nav class="bh3-family__filters" aria-label="<?php echo esc_attr__('Filter your planner', 'bubba-hub-manager'); ?>">
                  <?php foreach (array('all' => __('All', 'bubba-hub-manager'), 'to_visit' => __('To visit', 'bubba-hub-manager'), 'visited' => __('Visited', 'bubba-hub-manager')) as $filter_key => $filter_label) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array('bh3_planner_filter' => $filter_key, 'bh3_show_planner' => '0'), $return_url)); ?>" <?php if ($planner_filter === $filter_key) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($filter_label); ?></a>
                  <?php endforeach; ?>
                </nav>
                <form class="bh3-family__search" method="get" action="<?php echo esc_url($return_url); ?>">
                  <label for="bh3-planner-search"><?php echo esc_html__('Search planned activities', 'bubba-hub-manager'); ?></label>
                  <input id="bh3-planner-search" type="search" name="bh3_planner_search" maxlength="80" value="<?php echo esc_attr($planner_search); ?>" placeholder="<?php echo esc_attr__('Activity name', 'bubba-hub-manager'); ?>">
                  <input type="hidden" name="bh3_planner_filter" value="<?php echo esc_attr($planner_filter); ?>">
                  <input type="hidden" name="bh3_planner_sort" value="<?php echo esc_attr($planner_sort); ?>">
                  <button type="submit"><?php echo esc_html__('Search', 'bubba-hub-manager'); ?></button>
                  <?php if ($planner_search !== '') : ?><a href="<?php echo esc_url(add_query_arg(array('bh3_planner_filter' => $planner_filter, 'bh3_planner_sort' => $planner_sort), $return_url)); ?>"><?php echo esc_html__('Clear', 'bubba-hub-manager'); ?></a><?php endif; ?>
                </form>
                <form class="bh3-family__sort" method="get" action="<?php echo esc_url($return_url); ?>">
                  <label for="bh3-planner-sort"><?php echo esc_html__('Sort planner', 'bubba-hub-manager'); ?></label>
                  <select id="bh3-planner-sort" name="bh3_planner_sort">
                    <option value="recent" <?php selected($planner_sort, 'recent'); ?>><?php echo esc_html__('Recently added', 'bubba-hub-manager'); ?></option>
                    <option value="name" <?php selected($planner_sort, 'name'); ?>><?php echo esc_html__('Activity name A–Z', 'bubba-hub-manager'); ?></option>
                  </select>
                  <input type="hidden" name="bh3_planner_filter" value="<?php echo esc_attr($planner_filter); ?>">
                  <input type="hidden" name="bh3_planner_search" value="<?php echo esc_attr($planner_search); ?>">
                  <button type="submit"><?php echo esc_html__('Apply', 'bubba-hub-manager'); ?></button>
                </form>
                <?php endif; ?>
                <?php if (empty($filtered_planner)) : ?>
                  <p><?php echo esc_html(($planner_search !== '' || $planner_filter !== 'all') ? __('No planned activities match these filters.', 'bubba-hub-manager') : __('Plan your next family adventure here.', 'bubba-hub-manager')); ?></p>
                <?php else : ?>
                  <ul class="bh3-family__items">
                    <?php foreach (array_slice($filtered_planner, 0, $show_planner ? 200 : 8) as $item) : ?>
                      <li>
                        <?php $planner_id = absint($item['activity_id'] ?? 0); $planner_label = $activity_titles[$planner_id] ?? sprintf(__('Activity #%d', 'bubba-hub-manager'), $planner_id); ?>
                        <?php if (!empty($activity_links[$planner_id])) : ?><a href="<?php echo esc_url($activity_links[$planner_id] . '#timetable'); ?>"><?php echo esc_html($planner_label); ?></a> <a class="bh3-family__timetable-link" href="<?php echo esc_url($activity_links[$planner_id] . '#timetable'); ?>"><?php echo esc_html__('Timetable & booking options', 'bubba-hub-manager'); ?></a><?php else : ?><?php echo esc_html($planner_label); ?><?php endif; ?>
                        <span class="bh3-family__status"><?php echo esc_html(!empty($item['visited']) ? __('Visited', 'bubba-hub-manager') : __('Not visited yet', 'bubba-hub-manager')); ?></span>
                        <?php if (!$admin_demo && BH_Family_Actions_3::enabled()) : ?>
                          <?php foreach (array((!empty($item['visited']) ? 'not_visited' : 'visited') => (!empty($item['visited']) ? __('Mark not visited', 'bubba-hub-manager') : __('Mark visited', 'bubba-hub-manager')), 'unplan' => __('Remove', 'bubba-hub-manager')) as $action_key => $action_label) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                              <input type="hidden" name="action" value="bh3_family_activity_action">
                              <input type="hidden" name="bh3_action" value="<?php echo esc_attr($action_key); ?>">
                              <input type="hidden" name="bh3_activity_id" value="<?php echo esc_attr(absint($item['activity_id'] ?? 0)); ?>">
                              <?php wp_nonce_field('bh3_family_activity_action'); ?>
                              <button type="submit" class="bh3-family__action"><?php echo esc_html($action_label); ?></button>
                            </form>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                  <?php if (count($filtered_planner) > 8) : ?><p><a href="<?php echo esc_url(add_query_arg(array('bh3_show_planner' => $show_planner ? '0' : '1', 'bh3_planner_filter' => $planner_filter, 'bh3_planner_search' => $planner_search), $return_url)); ?>"><?php echo esc_html($show_planner ? __('Show fewer planned activities', 'bubba-hub-manager') : sprintf(__('View all %d matching activities', 'bubba-hub-manager'), count($filtered_planner))); ?></a></p><?php endif; ?>
                <?php endif; ?>
              </article>
              <article class="bh3-family__card">
                <h2><?php echo esc_html( $section[0] ); ?></h2>
                <p><?php echo esc_html( $section[1] ); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
            </div>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
