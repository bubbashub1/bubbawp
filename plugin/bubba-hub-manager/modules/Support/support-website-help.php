<?php
/**
 * Public, accessible website-help FAQs. No login or personal data required.
 */
defined('ABSPATH') || exit;

add_shortcode('bh_website_help', static function (): string {
    $sections = [
        __('Family accounts', 'bubbahub') => [
            [__('How do I access My Hub?', 'bubbahub'), __('Sign in to your Bubba Hub account, then open My Hub from the site navigation. If you cannot sign in, use the password-reset option on the login screen.', 'bubbahub')],
            [__('How do I save an activity?', 'bubbahub'), __('Open an activity listing and choose Save or Favourite. Sign in when prompted so your saved activities can be linked to your account.', 'bubbahub')],
            [__('How do I add an activity to my planner?', 'bubbahub'), __('On an activity listing, select Add to Planner and follow the on-screen steps. Check the organiser’s current timetable before travelling.', 'bubbahub')],
        ],
        __('Class leaders', 'bubbahub') => [
            [__('How do I claim an existing listing?', 'bubbahub'), __('Open the activity listing and use Claim this listing. Submit your request using your class leader account; the Bubba Hub team reviews claims before granting editing access.', 'bubbahub')],
            [__('How do I edit my class details?', 'bubbahub'), __('Sign in to your class leader account and open the leader dashboard. Find your assigned activity and choose Edit. Changes may be subject to review.', 'bubbahub')],
            [__('How do I correct a timetable?', 'bubbahub'), __('Open your class listing from the leader dashboard and review the available schedule-editing options. If the schedule cannot be changed, contact the Bubba Hub team with the corrected dates and times.', 'bubbahub')],
        ],
        __('Troubleshooting', 'bubbahub') => [
            [__('I cannot see my saved activities. What should I do?', 'bubbahub'), __('Check that you are signed in to the same account used to save them. Refresh the page and try again. If the problem continues, contact the team with the page URL and a description of the issue, but never share your password.', 'bubbahub')],
            [__('A listing is incorrect or out of date. How do I report it?', 'bubbahub'), __('Use the report or suggest-a-change option on the listing when available. Include the correct details and a source so the team can verify the update.', 'bubbahub')],
            [__('Where can I get further help?', 'bubbahub'), __('Use the contact information on the Bubba Hub website. Include which page you were using and what happened, but do not send passwords or sensitive personal information.', 'bubbahub')],
        ],
    ];
    ob_start();
    ?>
    <section class="bh-website-help" aria-label="<?php esc_attr_e('Website help and frequently asked questions', 'bubbahub'); ?>">
      <div class="bh-website-help__intro">
        <h2><?php esc_html_e('Website help & FAQs', 'bubbahub'); ?></h2>
        <p><?php esc_html_e('Quick answers for families and class leaders using Bubba Hub.', 'bubbahub'); ?></p>
      </div>
      <?php foreach ($sections as $heading => $questions) : ?>
        <section class="bh-website-help__section">
          <h3><?php echo esc_html($heading); ?></h3>
          <?php foreach ($questions as $question) : ?>
            <details class="bh-website-help__item">
              <summary><?php echo esc_html($question[0]); ?></summary>
              <div class="bh-website-help__answer"><p><?php echo esc_html($question[1]); ?></p></div>
            </details>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_singular() || !has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'bh_website_help')) {
        return;
    }
    wp_register_style('bh-website-help', false, [], '1.0.0');
    wp_enqueue_style('bh-website-help');
    wp_add_inline_style('bh-website-help', '
      .bh-website-help{max-width:1100px;margin:0 auto;color:#213d30}
      .bh-website-help__intro{padding:22px 0}
      .bh-website-help__intro h2{font-size:clamp(1.7rem,3vw,2.3rem);margin:0 0 8px}
      .bh-website-help__section{margin:24px 0 36px}
      .bh-website-help__section h3{font-size:1.3rem;margin:0 0 14px}
      .bh-website-help__item{border:1px solid #d6e7db;background:#f8fbf8;border-radius:14px;margin-bottom:10px;overflow:hidden}
      .bh-website-help__item summary{cursor:pointer;padding:18px 22px;font-weight:700;color:#174b36}
      .bh-website-help__item summary:focus-visible{outline:3px solid #226a4d;outline-offset:-3px}
      .bh-website-help__item[open] summary{border-bottom:1px solid #d6e7db}
      .bh-website-help__answer{padding:4px 22px 16px;line-height:1.65}
      @media(max-width:600px){.bh-website-help__item summary{padding:16px}.bh-website-help__answer{padding:4px 16px 14px}}
    ');
});
