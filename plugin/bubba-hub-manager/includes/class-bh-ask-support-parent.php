<?php
/**
 * Staging-only private parent question view.
 * Authenticated owners only; no email links, public routes or notifications.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Parent_3 {
    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_shortcode('bh_my_support_questions', [__CLASS__, 'render']);
    }

    public static function render(): string {
        if (!is_user_logged_in()) return '<p>Sign in to see your Ask Support questions.</p>';
        if (!BH_Ask_Support_Storage_3::installed()) return '<p>Your support questions are not available yet.</p>';
        global $wpdb;
        $t = BH_Ask_Support_Storage_3::tables();
        $user_id = get_current_user_id();
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT id,category_slug,visibility,status,question_text,created_at FROM `{$t['questions']}` WHERE owner_user_id=%d ORDER BY id DESC LIMIT 50",
            $user_id
        ), ARRAY_A);
        ob_start();
        echo '<section class="bh-my-support-questions" style="max-width:900px;margin:20px auto;color:#203c31">';
        echo '<h2>My support questions</h2><p>Only you and authorised Bubba Hub staff can access your question history here. Approved specialists can see moderator-reviewed question text in their matching topics.</p>';
        if (!$questions) echo '<p>No questions are linked to your account yet. Questions sent without signing in will remain in the support team inbox.</p>';
        foreach ((array) $questions as $question) {
            $id = (int) $question['id'];
            echo '<article style="background:#fff;border:1px solid #dbe5df;border-radius:14px;padding:20px;margin:14px 0">';
            echo '<h3 style="margin:0 0 10px">Question #' . esc_html((string) $id) . '</h3>';
            echo '<p><strong>Topic:</strong> ' . esc_html(BH_Ask_Support_Optin_3::categories()[$question['category_slug']] ?? $question['category_slug']) . ' · <strong>Privacy:</strong> ' . esc_html($question['visibility']) . ' · <strong>Status:</strong> ' . esc_html($question['status']) . '</p>';
            // The stored question is the redacted version after moderation. Original is retained only in Fluent Forms.
            echo '<p style="white-space:pre-wrap">' . esc_html($question['question_text']) . '</p>';
            if ($question['status'] === 'open') {
                $replies = $wpdb->get_results($wpdb->prepare(
                    "SELECT body,created_at FROM `{$t['replies']}` WHERE question_id=%d AND moderation_status='approved' ORDER BY id ASC LIMIT 100",
                    $id
                ), ARRAY_A);
                if ($replies) {
                    echo '<h4>Reviewed specialist responses</h4>';
                    foreach ($replies as $reply) {
                        echo '<div style="border-left:3px solid #28764c;background:#f4f8f5;padding:12px;margin:10px 0"><p style="white-space:pre-wrap">' . esc_html($reply['body']) . '</p></div>';
                    }
                } else echo '<p>There are no reviewed responses yet.</p>';
            }
            echo '</article>';
        }
        echo '<p style="font-size:.9rem;color:#52685b">This page is available to signed-in account holders only. No email notifications are enabled.</p></section>';
        return (string) ob_get_clean();
    }
}
BH_Ask_Support_Parent_3::register();
