<?php
/**
 * Staging-only specialist reply submission and moderator review.
 * Replies are never sent to parents or published by this module.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Replies_3 {
    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_action('admin_post_bh3_ask_submit_reply', [__CLASS__, 'submit']);
        add_action('admin_post_bh3_ask_review_reply', [__CLASS__, 'review']);
        add_action('admin_menu', [__CLASS__, 'menu'], 33);
    }

    public static function submit(): void {
        if (!is_user_logged_in()) wp_die('Sign in required.', '', ['response' => 403]);
        $question_id = absint($_POST['question_id'] ?? 0);
        if (!$question_id) wp_die('Invalid question.', '', ['response' => 400]);
        check_admin_referer('bh3_ask_reply_' . $question_id);
        if (!BH_Ask_Support_Storage_3::can_specialist_access($question_id, get_current_user_id())) {
            wp_die('Not authorised for this question.', '', ['response' => 403]);
        }
        $body = isset($_POST['reply_body']) && is_string($_POST['reply_body'])
            ? trim(sanitize_textarea_field(wp_unslash($_POST['reply_body']))) : '';
        if (mb_strlen($body) < 10 || mb_strlen($body) > 5000) wp_die('Reply must be 10–5000 characters.', '', ['response' => 400]);
        global $wpdb;
        $tables = BH_Ask_Support_Storage_3::tables();
        $ok = $wpdb->insert($tables['replies'], [
            'question_id' => $question_id,
            'author_user_id' => get_current_user_id(),
            'organiser_id' => absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true)),
            'body' => $body,
            'moderation_status' => 'pending_review',
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%d','%s','%s','%s']);
        if (!$ok) wp_die('Could not save reply.', '', ['response' => 500]);
        $wpdb->insert($tables['audit'], [
            'question_id' => $question_id, 'actor_user_id' => get_current_user_id(),
            'event' => 'specialist_reply_submitted', 'occurred_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s']);
        wp_safe_redirect(add_query_arg('ask_reply_saved', '1', home_url('/leader/support/')));
        exit;
    }

    public static function menu(): void {
        add_submenu_page('bh3-support-help', 'Ask Support Replies', 'Ask Support Replies', 'manage_options', 'bh3-ask-replies', [__CLASS__, 'render']);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', '', ['response' => 403]);
        echo '<div class="wrap"><h1>Ask Support · Reply moderation</h1><p>Review specialist replies before any future parent delivery. Approval here does not send emails or publish replies.</p>';
        if (!BH_Ask_Support_Storage_3::installed()) { echo '<p>Storage is not installed.</p></div>'; return; }
        global $wpdb;
        $table = BH_Ask_Support_Storage_3::tables()['replies'];
        $rows = $wpdb->get_results("SELECT id,question_id,author_user_id,body,moderation_status,created_at FROM `{$table}` ORDER BY id DESC LIMIT 100", ARRAY_A);
        if (!$rows) { echo '<p>No specialist replies yet.</p></div>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Question</th><th>Specialist ID</th><th>Reply</th><th>Status</th><th>Review</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>#' . esc_html((string) $row['question_id']) . '</td><td>' . esc_html((string) $row['author_user_id']) . '</td><td>' . esc_html($row['body']) . '</td><td>' . esc_html($row['moderation_status']) . '</td><td>';
            if ($row['moderation_status'] === 'pending_review') {
                foreach (['approved' => 'Approve (hold)', 'rejected' => 'Reject'] as $decision => $label) {
                    echo '<form method="post" style="display:inline-block;margin-right:8px" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="bh3_ask_review_reply"><input type="hidden" name="reply_id" value="' . esc_attr((string) $row['id']) . '"><input type="hidden" name="decision" value="' . esc_attr($decision) . '">';
                    wp_nonce_field('bh3_ask_review_reply_' . (int) $row['id']);
                    submit_button($label, 'secondary small', 'submit', false);
                    echo '</form>';
                }
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function review(): void {
        if (!current_user_can('manage_options') ||
            strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') wp_die('Not authorised.', '', ['response' => 403]);
        $id = absint($_POST['reply_id'] ?? 0);
        $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        if (!$id || !in_array($decision, ['approved', 'rejected'], true)) wp_die('Invalid review.', '', ['response' => 400]);
        check_admin_referer('bh3_ask_review_reply_' . $id);
        if (!BH_Ask_Support_Storage_3::installed()) wp_die('Storage unavailable.', '', ['response' => 503]);
        global $wpdb;
        $tables = BH_Ask_Support_Storage_3::tables();
        $reply = $wpdb->get_row($wpdb->prepare("SELECT question_id FROM `{$tables['replies']}` WHERE id=%d AND moderation_status='pending_review'", $id), ARRAY_A);
        if (!$reply) wp_die('Reply is no longer pending.', '', ['response' => 409]);
        $ok = $wpdb->update($tables['replies'], ['moderation_status' => $decision], ['id' => $id, 'moderation_status' => 'pending_review'], ['%s'], ['%d','%s']);
        if ($ok !== 1) wp_die('Review could not be saved.', '', ['response' => 409]);
        $wpdb->insert($tables['audit'], [
            'question_id' => (int) $reply['question_id'], 'actor_user_id' => get_current_user_id(),
            'event' => 'moderator_reply_' . $decision, 'occurred_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s']);
        wp_safe_redirect(admin_url('admin.php?page=bh3-ask-replies&reviewed=1'));
        exit;
    }
}
BH_Ask_Support_Replies_3::register();
