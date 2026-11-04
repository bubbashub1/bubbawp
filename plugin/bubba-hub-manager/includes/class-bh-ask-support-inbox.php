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
          <?php
          // No question data is queried unless the complete schema exists.
          if (BH_Ask_Support_Storage_3::installed() && $opted_in && $categories) {
              $tables = BH_Ask_Support_Storage_3::tables();
              $user_id = get_current_user_id();
              $approved = $wpdb->get_col($wpdb->prepare(
                  "SELECT category_slug FROM `{$tables['specialists']}` WHERE user_id=%d AND organiser_id=%d AND opted_in=1 AND approval_status='approved'",
                  $user_id, $organiser_id
              ));
              $approved = array_values(array_intersect((array) $approved, array_keys($categories)));
              if ($approved) {
                  $placeholders = implode(',', array_fill(0, count($approved), '%s'));
                  $query = "SELECT id,category_slug,question_text,created_at FROM `{$tables['questions']}` WHERE status='open' AND category_slug IN ($placeholders) ORDER BY created_at DESC LIMIT 30";
                  $questions = $wpdb->get_results($wpdb->prepare($query, $approved), ARRAY_A);
                  echo '<h3>Questions awaiting specialist replies</h3>';
                  if (!$questions) echo '<p>No approved questions in your topics yet.</p>';
                  foreach ((array) $questions as $question) {
                      // Revalidate each record individually; never expose parent contacts.
                      if (!BH_Ask_Support_Storage_3::can_specialist_access((int) $question['id'], $user_id)) continue;
                      echo '<article style="border:1px solid #dbe5df;border-radius:12px;padding:16px;margin:12px 0">';
                      echo '<p style="font-size:.85rem;color:#52685b">Question #' . esc_html((string) $question['id']) . ' · ' . esc_html($categories[$question['category_slug']] ?? $question['category_slug']) . '</p>';
                      echo '<p style="white-space:pre-wrap">' . esc_html($question['question_text']) . '</p>';
                      echo '<p style="font-size:.85rem">Replies will be enabled after moderation and notification safeguards are tested.</p>';
                      echo '</article>';
                  }
              } else {
                  echo '<p role="status">Your selected categories are awaiting administrator approval.</p>';
              }
          }
          ?>
          <p style="font-size:.9rem;color:#52685b">Only moderator-reviewed questions are shown to verified specialists. Parent contact details are never included.</p>
        </section>
        <?php return (string) ob_get_clean();
    }
}
BH_Ask_Support_Inbox_3::register();
