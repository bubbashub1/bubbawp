<?php
/**
 * Staging-only Ask Support inbox shell. Fail closed until moderated question storage is ready.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Inbox_3 {
    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_shortcode('bh_leader_ask_inbox', [__CLASS__, 'render']);
    }

    public static function render(): string {
        if (!is_user_logged_in()) return '<p>Please sign in to access Ask Support.</p>';
        $organiser_id = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        if (!$organiser_id) return '<p>Link your organiser profile before joining Ask Support.</p>';
        global $wpdb;
        $organisers = $wpdb->prefix . 'organisers';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($organisers))) !== $organisers
            || !(int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `$organisers` WHERE id=%d", $organiser_id))) {
            return '<p>Your organiser profile could not be verified.</p>';
        }
        $prefs = (array) get_user_meta(get_current_user_id(), 'bh3_ask_support_preferences', true);
        $opted_in = !empty($prefs['opted_in']);
        $categories = array_intersect_key(BH_Ask_Support_Optin_3::categories(), array_flip((array) ($prefs['categories'] ?? [])));
        ob_start(); ?>
        <section id="leader-ask-inbox" class="bh-ask-inbox" style="margin:24px 0;padding:clamp(18px,3vw,28px);border:1px solid #dbe5df;border-radius:16px;background:#fff;color:#203c31">
          <h2 style="margin:0 0 8px">Ask Support · Questions</h2>
          <p>Questions will appear here when the specialist verification, moderation and private-message safeguards are enabled.</p>
          <?php if (!$opted_in || !$categories) : ?>
            <p><strong>Not opted in.</strong> Choose your areas of expertise below to register your interest.</p>
          <?php else : ?>
            <p><strong>Your selected topics:</strong> <?php echo esc_html(implode(', ', array_values($categories))); ?></p>
            <p role="status">Your interest is saved. Specialist approval is required before any questions can be viewed or answered.</p>
          <?php endif; ?>
          <p style="font-size:.9rem;color:#52685b">Public and private questions are not exposed through this preview. A matching category alone never grants access.</p>
        </section>
        <?php return (string) ob_get_clean();
    }
}
BH_Ask_Support_Inbox_3::register();
