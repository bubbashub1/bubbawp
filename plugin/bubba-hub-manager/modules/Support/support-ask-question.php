<?php
/**
 * Public Ask a Question page using Fluent Forms Free for secure form handling.
 * Submission storage, email delivery and spam controls stay with Fluent Forms.
 */
defined('ABSPATH') || exit;

add_shortcode('bh_ask_question', static function ($atts): string {
    $atts = shortcode_atts(['form_id' => 0], (array) $atts, 'bh_ask_question');
    $form_id = absint($atts['form_id']);
    if (!$form_id) {
        $form_id = absint(get_option('bh_support_question_form_id', 0));
    }
    $can_manage = current_user_can('manage_options');
    ob_start();
    ?>
    <section class="bh-ask-question" aria-label="<?php esc_attr_e('Ask Bubba Hub a question', 'bubbahub'); ?>">
      <div class="bh-ask-question__intro">
        <span class="bh-ask-question__eyebrow"><?php esc_html_e('Bubba Hub Support', 'bubbahub'); ?></span>
        <h2 class="screen-reader-text"><?php esc_html_e('Ask us a question', 'bubbahub'); ?></h2>
        <p><?php esc_html_e('Need help using Bubba Hub or have a question about a listing? Send us a message and our team can review it.', 'bubbahub'); ?></p>
      </div>
      <div class="bh-ask-question__notice">
        <strong><?php esc_html_e('Please keep your message safe', 'bubbahub'); ?></strong>
        <p><?php esc_html_e('Do not include passwords, medical records or other sensitive personal information. This form is not monitored for emergencies or urgent health concerns.', 'bubbahub'); ?></p>
      </div>
      <?php if ($form_id && shortcode_exists('fluentform')) : ?>
        <div class="bh-ask-question__form"><?php echo do_shortcode('[fluentform id="' . $form_id . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted Fluent Forms shortcode markup. ?></div>
      <?php elseif ($can_manage) : ?>
        <p class="bh-ask-question__setup"><?php esc_html_e('Admin setup: create an Ask a Question form in Fluent Forms, then set its ID in [bh_ask_question form_id="123"]. The public form is not enabled yet.', 'bubbahub'); ?></p>
      <?php else : ?>
        <p class="bh-ask-question__setup"><?php esc_html_e('The question form is being prepared. Please use the website contact details in the meantime.', 'bubbahub'); ?></p>
      <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_singular() || !has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'bh_ask_question')) {
        return;
    }
    wp_register_style('bh-ask-question', false, [], '1.0.0');
    wp_enqueue_style('bh-ask-question');
    wp_add_inline_style('bh-ask-question', '
      .bh-ask-question{max-width:960px;margin:0 auto;color:#213d30}
      body.page .bh-ask-question{margin-top:0}
      body.page:has(.bh-ask-question) .wp-block-post-title{margin-bottom:12px}
      body.page:has(.bh-ask-question) .entry-content{margin-top:0}
      body.page:has(.bh-ask-question) .wp-block-spacer:empty{display:none}
      body.page:has(.bh-ask-question) .wp-block-post-content{padding-top:0}
      .bh-ask-question .screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
      .bh-ask-question__intro{padding:4px 0 12px}
      .bh-ask-question__eyebrow{font-size:.8rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#276345}
      .bh-ask-question__intro h2{font-size:clamp(1.8rem,4vw,2.6rem);margin:12px 0}
      .bh-ask-question__intro p{font-size:1.05rem;line-height:1.65}
      .bh-ask-question__notice{background:#f3f8f3;border-left:4px solid #276345;padding:16px 20px;border-radius:10px;margin-bottom:24px}
      .bh-ask-question__notice p{margin:6px 0 0}
      .bh-ask-question__form{background:#fff;border:1px solid #d9e9dd;border-radius:16px;padding:clamp(16px,3vw,28px);box-shadow:0 8px 28px rgba(23,75,54,.05)}\n      .bh-ask-question__form .ff-el-group{margin-bottom:18px}\n      .bh-ask-question__form .ff-el-input--label label{font-weight:650;color:#213d30}\n      .bh-ask-question__form .ff-el-form-control{min-height:46px;border-radius:9px;border-color:#c9d8ce}\n      .bh-ask-question__form textarea.ff-el-form-control{min-height:140px}
      .bh-ask-question__form input,.bh-ask-question__form select,.bh-ask-question__form textarea{max-width:100%}
      .bh-ask-question__form button[type=submit]{background:#174b36;color:#fff;border-radius:10px;padding:12px 22px}
      .bh-ask-question__setup{padding:20px;border:1px dashed #bfd7c6;border-radius:12px}
    ');
});
