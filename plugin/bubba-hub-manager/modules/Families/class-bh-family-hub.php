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
        $private_preview = defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true && $wp_signed_in && BH_Family_Identity_3::current_legacy_id() > 0;
        $saved = $private_preview ? BH_Family_Read_Model_3::saved_ids() : array();
        $planner = $private_preview ? BH_Family_Read_Model_3::planner_items() : array();
        $profiles = $private_preview ? BH_Family_Read_Model_3::family_profiles() : array();
        $activity_ids = array_merge(is_array($saved) ? $saved : array(), is_array($planner) ? array_column($planner, 'activity_id') : array());
        $activity_titles = $private_preview ? BH_Family_Read_Model_3::activity_titles($activity_ids) : array();
        if (is_wp_error($activity_titles)) $activity_titles = array();
        $sections = array(
            array( 'My family', 'Family profiles will appear here once account mapping is verified.' ),
            array( 'Saved activities', 'Your favourites will connect to existing activity IDs.' ),
            array( 'Family planner', 'Calendar and recurring sessions are coming after planner parity testing.' ),
            array( 'Discover', 'Find age-suitable activities without exposing family details.' ),
        );
        ob_start();
        ?>
        <section class="bh3-family" aria-labelledby="bh3-family-heading">
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
            <div class="bh3-family__notice" role="status"><?php echo esc_html__( 'Signed in. Family data remains in preview mode while account compatibility checks are completed.', 'bubba-hub-manager' ); ?></div>
          <?php endif; ?>
          <?php if ($private_preview && !is_wp_error($saved) && !is_wp_error($planner) && !is_wp_error($profiles)) : ?>
            <div class="bh3-family__notice" role="status">
              <?php echo esc_html(sprintf('Your Hub: %d saved activities, %d planned activities, %d child profiles.', count($saved), count($planner), count($profiles['children']))); ?>
            </div>
          <?php endif; ?>
          <?php if ($private_preview && !is_wp_error($saved) && !is_wp_error($planner) && !is_wp_error($profiles)) : ?>
            <div class="bh3-family__grid bh3-family__private" aria-label="<?php echo esc_attr__('Your private family overview', 'bubba-hub-manager'); ?>">
              <article class="bh3-family__card">
                <h2><?php echo esc_html__('Saved activities', 'bubba-hub-manager'); ?></h2>
                <?php if (empty($saved)) : ?>
                  <p><?php echo esc_html__('No saved activities yet.', 'bubba-hub-manager'); ?></p>
                <?php else : ?>
                  <ul class="bh3-family__items">
                    <?php foreach (array_slice($saved, 0, 8) as $activity_id) : ?>
                      <li>
                        <span><?php echo esc_html($activity_titles[absint($activity_id)] ?? sprintf(__('Activity #%d', 'bubba-hub-manager'), absint($activity_id))); ?></span>
                        <?php if (BH_Family_Actions_3::enabled()) : ?>
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
                  <?php if (count($saved) > 8) : ?><p><?php echo esc_html(sprintf(__('%d more saved', 'bubba-hub-manager'), count($saved) - 8)); ?></p><?php endif; ?>
                <?php endif; ?>
              </article>
              <article class="bh3-family__card">
                <h2><?php echo esc_html__('Family planner', 'bubba-hub-manager'); ?></h2>
                <?php if (empty($planner)) : ?>
                  <p><?php echo esc_html__('Your planner is empty.', 'bubba-hub-manager'); ?></p>
                <?php else : ?>
                  <ul class="bh3-family__items">
                    <?php foreach (array_slice($planner, 0, 8) as $item) : ?>
                      <li>
                        <?php echo esc_html($activity_titles[absint($item['activity_id'] ?? 0)] ?? sprintf(__('Activity #%d', 'bubba-hub-manager'), absint($item['activity_id'] ?? 0))); ?>
                        <?php if (!empty($item['visited'])) : ?><span class="bh3-family__status"><?php echo esc_html__('Visited', 'bubba-hub-manager'); ?></span><?php endif; ?>
                        <?php if (BH_Family_Actions_3::enabled()) : ?>
                          <?php foreach (array('visited' => __('Visited', 'bubba-hub-manager'), 'not_visited' => __('Not visited', 'bubba-hub-manager'), 'unplan' => __('Remove', 'bubba-hub-manager')) as $action_key => $action_label) : ?>
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
                <?php endif; ?>
              </article>
              <article class="bh3-family__card">
                <h2><?php echo esc_html__('My family', 'bubba-hub-manager'); ?></h2>
                <p><?php echo esc_html(sprintf(__('%1$d child profiles · %2$d pregnancy profiles', 'bubba-hub-manager'), count($profiles['children']), count($profiles['bumps']))); ?></p>
                <?php if (BH_Family_Profile_Editor_3::enabled()) : ?>
                  <?php if (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'saved') : ?>
                    <p role="status"><?php echo esc_html__('Profile updated.', 'bubba-hub-manager'); ?></p>
                  <?php elseif (isset($_GET['bh3_profile_status']) && $_GET['bh3_profile_status'] === 'error') : ?>
                    <p role="alert"><?php echo esc_html__('Profile could not be updated.', 'bubba-hub-manager'); ?></p>
                  <?php endif; ?>
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
                <?php else : ?>
                  <p><?php echo esc_html__('Profile details are hidden in this initial preview.', 'bubba-hub-manager'); ?></p>
                <?php endif; ?>
              </article>
            </div>
          <?php endif; ?>
          <?php if ($private_preview && BH_Family_Actions_3::enabled() && isset($_GET['bh3_activity_status'])) : ?>
            <p class="bh3-family__notice" role="status"><?php echo esc_html($_GET['bh3_activity_status'] === 'saved' ? __('Activity updated.', 'bubba-hub-manager') : __('Activity could not be updated.', 'bubba-hub-manager')); ?></p>
          <?php endif; ?>
          <div class="bh3-family__grid">
            <?php foreach ( $sections as $section ) : ?>
              <article class="bh3-family__card">
                <h2><?php echo esc_html( $section[0] ); ?></h2>
                <p><?php echo esc_html( $section[1] ); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
