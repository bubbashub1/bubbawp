<?php
/**
 * Staging-only Fluent Forms Ask Support intake.
 * New submissions only; no backfill, no automatic specialist visibility.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Intake_3 {
    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_action('fluentform/submission_inserted', [__CLASS__, 'capture'], 10, 3);
        add_action('admin_menu', [__CLASS__, 'menu'], 32);
        add_action('admin_post_bh3_ask_reject_question', [__CLASS__, 'reject']);
        add_action('admin_post_bh3_ask_approve_question', [__CLASS__, 'approve']);
    }

    public static function capture($entry_id, $data, $form): void {
        // Explicit administrator opt-in; default OFF until form field keys and consent are verified.
        if (!defined('BH3_ASK_SUPPORT_INTAKE') || BH3_ASK_SUPPORT_INTAKE !== true) return;
        if ((int) ($form->id ?? 0) !== 4 || !is_array($data) || !BH_Ask_Support_Storage_3::installed()) return;

        // Exact Fluent Forms field names required; never guess or infer consent.
        $category = isset($data['ask_category']) && is_scalar($data['ask_category']) ? sanitize_key((string) $data['ask_category']) : '';
        $visibility = isset($data['ask_visibility']) && is_scalar($data['ask_visibility']) ? sanitize_key((string) $data['ask_visibility']) : '';
        $consent = isset($data['ask_specialist_consent']) && is_scalar($data['ask_specialist_consent'])
            ? sanitize_key((string) $data['ask_specialist_consent']) : '';
        $question = isset($data['ask_question']) && is_string($data['ask_question'])
            ? trim(sanitize_textarea_field($data['ask_question'])) : '';
        $email = isset($data['ask_email']) && is_string($data['ask_email'])
            ? sanitize_email($data['ask_email']) : '';
        $name = isset($data['ask_name']) && is_string($data['ask_name'])
            ? sanitize_text_field($data['ask_name']) : '';

        if (!array_key_exists($category, BH_Ask_Support_Optin_3::categories()) ||
            !in_array($visibility, ['public','private'], true) ||
            $consent !== 'yes' || !$question || strlen($question) > 10000 ||
            !is_email($email) || !$entry_id) return;

        global $wpdb;
        $t = BH_Ask_Support_Storage_3::tables();
        // Avoid duplicate intake on hook retries. Existing form entries are never imported.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$t['questions']}` WHERE form_submission_id=%d LIMIT 1", absint($entry_id)
        ));
        if ($existing) return;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($t['questions'], [
            'owner_user_id' => get_current_user_id() ?: null,
            'form_submission_id' => absint($entry_id),
            'category_slug' => $category,
            'visibility' => $visibility,
            'status' => 'pending_review',
            'question_text' => $question,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%d','%s','%s','%s','%s','%s','%s']);
        if (!$ok) return;
        $id = (int) $wpdb->insert_id;
        $saved = $wpdb->insert($t['contacts'], [
            'question_id' => $id,
            'parent_email' => $email,
            'parent_name' => $name,
            'notifications_enabled' => 0,
        ], ['%d','%s','%s','%d']);
        if (!$saved) {
            // Fail closed: remove the orphaned question rather than exposing incomplete records.
            $wpdb->delete($t['questions'], ['id' => $id], ['%d']);
            return;
        }
        $wpdb->insert($t['audit'], [
            'question_id' => $id,
            'actor_user_id' => get_current_user_id(),
            'event' => 'intake_pending_review',
            'occurred_at' => $now,
        ], ['%d','%d','%s','%s']);
    }

    public static function menu(): void {
        add_submenu_page('bh3-support-help', 'Ask Support Questions', 'Ask Support Questions', 'manage_options', 'bh3-ask-questions', [__CLASS__, 'render']);
    }

    /**
     * Rejection is the only enabled moderation transition at this stage.
     * Approval/sharing must wait for a redaction and access-control review.
     */
    public static function reject(): void {
        if (!current_user_can('manage_options') ||
            strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') {
            wp_die('Not authorised.', '', ['response' => 403]);
        }
        $id = absint($_POST['question_id'] ?? 0);
        if (!$id) wp_die('Invalid question.', '', ['response' => 400]);
        check_admin_referer('bh3_ask_reject_' . $id);
        if (!BH_Ask_Support_Storage_3::installed()) wp_die('Storage unavailable.', '', ['response' => 503]);
        global $wpdb;
        $tables = BH_Ask_Support_Storage_3::tables();
        $updated = $wpdb->update(
            $tables['questions'],
            ['status' => 'rejected', 'updated_at' => current_time('mysql', true)],
            ['id' => $id, 'status' => 'pending_review'],
            ['%s', '%s'], ['%d', '%s']
        );
        if ($updated !== 1) wp_die('Question not pending review or could not be updated.', '', ['response' => 409]);
        $wpdb->insert($tables['audit'], [
            'question_id' => $id,
            'actor_user_id' => get_current_user_id(),
            'event' => 'moderator_rejected',
            'occurred_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s']);
        wp_safe_redirect(admin_url('admin.php?page=bh3-ask-questions&rejected=1'));
        exit;
    }

    /**
     * Moderators must replace the original with a deliberately redacted version.
     * Approval never makes the question publicly visible or searchable.
     */
    public static function approve(): void {
        if (!current_user_can('manage_options') ||
            strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') {
            wp_die('Not authorised.', '', ['response' => 403]);
        }
        $id = absint($_POST['question_id'] ?? 0);
        if (!$id) wp_die('Invalid question.', '', ['response' => 400]);
        check_admin_referer('bh3_ask_approve_' . $id);
        if (!BH_Ask_Support_Storage_3::installed()) wp_die('Storage unavailable.', '', ['response' => 503]);
        if (($_POST['redaction_confirmed'] ?? '') !== '1') wp_die('Confirm privacy review before approving.', '', ['response' => 400]);
        $reviewed = isset($_POST['reviewed_question']) && is_string($_POST['reviewed_question'])
            ? trim(sanitize_textarea_field(wp_unslash($_POST['reviewed_question']))) : '';
        if (strlen($reviewed) < 15 || strlen($reviewed) > 10000) wp_die('Reviewed question must be between 15 and 10000 characters.', '', ['response' => 400]);
        global $wpdb;
        $tables = BH_Ask_Support_Storage_3::tables();
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT id,status FROM `{$tables['questions']}` WHERE id=%d LIMIT 1", $id
        ), ARRAY_A);
        if (!$question || $question['status'] !== 'pending_review') wp_die('Question is no longer pending.', '', ['response' => 409]);
        $updated = $wpdb->update($tables['questions'],
            ['question_text' => $reviewed, 'status' => 'open', 'updated_at' => current_time('mysql', true)],
            ['id' => $id, 'status' => 'pending_review'],
            ['%s','%s','%s'], ['%d','%s']);
        if ($updated !== 1) wp_die('Approval could not be saved.', '', ['response' => 409]);
        $wpdb->insert($tables['audit'], [
            'question_id' => $id, 'actor_user_id' => get_current_user_id(),
            'event' => 'moderator_redacted_and_approved', 'occurred_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s']);
        wp_safe_redirect(admin_url('admin.php?page=bh3-ask-questions&approved=1'));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', '', ['response' => 403]);
        echo '<div class="wrap"><h1>Ask Support · Moderation queue</h1>';
        echo '<p>New questions are held for manual review. Nothing on this screen publishes or shares questions with specialists.</p>';
        if (!BH_Ask_Support_Storage_3::installed()) {
            echo '<p>Storage is not installed. Take a staging backup and complete Ask Support Setup first.</p></div>';
            return;
        }
        global $wpdb;
        $table = BH_Ask_Support_Storage_3::tables()['questions'];
        $rows = $wpdb->get_results("SELECT id,category_slug,visibility,status,created_at,question_text FROM `{$table}` ORDER BY id DESC LIMIT 100", ARRAY_A);
        if (!$rows) { echo '<p>No captured questions. Intake is disabled until explicitly enabled and the form field names are configured.</p></div>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Category</th><th>Visibility requested</th><th>Status</th><th>Submitted (UTC)</th><th>Question (moderators only)</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['id']) . '</td><td>' . esc_html($row['category_slug']) . '</td><td>' . esc_html($row['visibility']) . '</td><td>' . esc_html($row['status']) . '</td><td>' . esc_html($row['created_at']) . '</td><td>' . esc_html(wp_trim_words($row['question_text'], 45)) . '</td><td>';
            if ($row['status'] === 'pending_review') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bh3_ask_reject_question">';
                echo '<input type="hidden" name="question_id" value="' . esc_attr((string) $row['id']) . '">';
                wp_nonce_field('bh3_ask_reject_' . (int) $row['id']);
                submit_button('Reject', 'secondary small', 'submit', false);
                echo '</form>';
                echo '<details><summary>Review and approve for specialists</summary>';
                echo '<p>Rewrite the question to remove names, locations, contact details, identifiable circumstances and sensitive information. Approval makes this text available only to verified matching specialists, not the public.</p>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bh3_ask_approve_question">';
                echo '<input type="hidden" name="question_id" value="' . esc_attr((string) $row['id']) . '">';
                wp_nonce_field('bh3_ask_approve_' . (int) $row['id']);
                echo '<textarea required minlength="15" maxlength="10000" rows="5" style="width:100%" name="reviewed_question" aria-label="Redacted question"></textarea>';
                echo '<label><input type="checkbox" name="redaction_confirmed" value="1" required> I have reviewed this text for personal and sensitive details.</label>';
                submit_button('Approve for matching specialists', 'primary small', 'submit', false);
                echo '</form></details>';
            } else { echo '&mdash;'; }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
BH_Ask_Support_Intake_3::register();
