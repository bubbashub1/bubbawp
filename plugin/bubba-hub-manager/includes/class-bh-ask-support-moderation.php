<?php
/**
 * Staging-only Ask Support specialist approval screen.
 * Does not grant access to Fluent Forms or expose any question content.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Moderation_3 {
    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_action('admin_menu', [__CLASS__, 'menu'], 31);
        add_action('admin_post_bh3_ask_specialist_review', [__CLASS__, 'review']);
    }

    public static function menu(): void {
        add_submenu_page('bh3-support-help', 'Ask Support Specialists', 'Ask Support Specialists', 'manage_options', 'bh3-ask-specialists', [__CLASS__, 'render']);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', '', ['response' => 403]);
        echo '<div class="wrap"><h1>Ask Support · Specialist approvals</h1>';
        if (!BH_Ask_Support_Storage_3::installed()) {
            echo '<p>Install the Ask Support tables after taking a verified staging backup before reviewing specialists.</p></div>';
            return;
        }
        global $wpdb;
        $table = BH_Ask_Support_Storage_3::tables()['specialists'];
        $rows = $wpdb->get_results("SELECT id,organiser_id,user_id,category_slug,opted_in,approval_status FROM `{$table}` ORDER BY updated_at DESC LIMIT 100", ARRAY_A);
        if (!$rows) {
            echo '<p>No specialist requests yet. Leader opt-ins are currently stored as preferences; a future synchronisation step will create approval requests after organiser verification.</p></div>';
            return;
        }
        echo '<p>Approve only after independently checking the leader, organisation and relevant expertise. An approval is restricted to the selected category.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Leader</th><th>Organiser ID</th><th>Category</th><th>Opted in</th><th>Status</th><th>Review</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $user = get_userdata((int) $row['user_id']);
            echo '<tr><td>' . esc_html($user ? $user->display_name : 'Unknown user') . '</td><td>' . esc_html((string) $row['organiser_id']) . '</td><td>' . esc_html($row['category_slug']) . '</td><td>' . ($row['opted_in'] ? 'Yes' : 'No') . '</td><td>' . esc_html($row['approval_status']) . '</td><td>';
            foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $status => $label) {
                echo '<form style="display:inline-block;margin-right:8px" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bh3_ask_specialist_review"><input type="hidden" name="specialist_id" value="' . esc_attr((string) $row['id']) . '"><input type="hidden" name="decision" value="' . esc_attr($status) . '">';
                wp_nonce_field('bh3_ask_review_' . (int) $row['id']);
                submit_button($label, 'secondary small', 'submit', false);
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function review(): void {
        if (!current_user_can('manage_options') || strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') {
            wp_die('Not authorised.', '', ['response' => 403]);
        }
        $id = absint($_POST['specialist_id'] ?? 0);
        $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        if (!$id || !in_array($decision, ['approved', 'rejected'], true)) wp_die('Invalid request.', '', ['response' => 400]);
        check_admin_referer('bh3_ask_review_' . $id);
        if (!BH_Ask_Support_Storage_3::installed()) wp_die('Storage not installed.', '', ['response' => 503]);
        global $wpdb;
        $table = BH_Ask_Support_Storage_3::tables()['specialists'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id=%d", $id), ARRAY_A);
        if (!$row) wp_die('Specialist not found.', '', ['response' => 404]);
        if ($decision === 'approved') {
            $prefs = (array) get_user_meta((int) $row['user_id'], 'bh3_ask_support_preferences', true);
            $linked = absint(get_user_meta((int) $row['user_id'], 'bh3_organiser_id', true));
            if (!$row['opted_in'] || !$linked || $linked !== (int) $row['organiser_id']
                || empty($prefs['opted_in']) || !in_array($row['category_slug'], (array) ($prefs['categories'] ?? []), true)
                || !array_key_exists($row['category_slug'], BH_Ask_Support_Optin_3::categories())) {
                wp_die('The leader is no longer eligible for this category.', '', ['response' => 409]);
            }
        }
        $result = $wpdb->update($table, ['approval_status' => $decision, 'updated_at' => current_time('mysql', true)], ['id' => $id], ['%s','%s'], ['%d']);
        if ($result === false) wp_die('Could not save the review.', '', ['response' => 500]);
        wp_safe_redirect(admin_url('admin.php?page=bh3-ask-specialists&reviewed=1'));
        exit;
    }
}
BH_Ask_Support_Moderation_3::register();
