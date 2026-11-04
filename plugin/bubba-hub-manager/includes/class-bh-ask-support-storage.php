<?php
/**
 * Ask Support storage foundation. Staging-only; no automatic migrations or public endpoints.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Storage_3 {
    public static function tables(): array {
        global $wpdb;
        return [
            'questions' => $wpdb->prefix . 'bh_ask_questions',
            'contacts' => $wpdb->prefix . 'bh_ask_private_contacts',
            'specialists' => $wpdb->prefix . 'bh_ask_leader_categories',
            'replies' => $wpdb->prefix . 'bh_ask_replies',
            'audit' => $wpdb->prefix . 'bh_ask_audit',
        ];
    }

    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
        add_action('admin_post_bh3_ask_install_schema', [__CLASS__, 'install']);
    }

    public static function admin_menu(): void {
        add_submenu_page('bh3-support-help', 'Ask Support Setup', 'Ask Support Setup', 'manage_options', 'bh3-ask-support-setup', [__CLASS__, 'setup_page']);
    }

    public static function schema_sql(): array {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t = self::tables();
        return [
            "CREATE TABLE {$t['questions']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                owner_user_id bigint(20) unsigned DEFAULT NULL,
                form_submission_id bigint(20) unsigned DEFAULT NULL,
                category_slug varchar(80) NOT NULL,
                visibility varchar(10) NOT NULL DEFAULT 'private',
                status varchar(24) NOT NULL DEFAULT 'pending_review',
                question_text longtext NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY category_status (category_slug,status),
                KEY owner_user (owner_user_id),
                KEY visibility_status (visibility,status)
            ) $charset;",
            "CREATE TABLE {$t['contacts']} (
                question_id bigint(20) unsigned NOT NULL,
                parent_email varchar(254) NOT NULL,
                parent_name varchar(190) NOT NULL DEFAULT '',
                token_hash varchar(255) NOT NULL DEFAULT '',
                notifications_enabled tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY  (question_id)
            ) $charset;",
            "CREATE TABLE {$t['specialists']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                organiser_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                category_slug varchar(80) NOT NULL,
                opted_in tinyint(1) NOT NULL DEFAULT 0,
                approval_status varchar(20) NOT NULL DEFAULT 'pending',
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY specialist_category (user_id,category_slug),
                KEY matching (category_slug,opted_in,approval_status)
            ) $charset;",
            "CREATE TABLE {$t['replies']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                question_id bigint(20) unsigned NOT NULL,
                author_user_id bigint(20) unsigned NOT NULL,
                organiser_id bigint(20) unsigned DEFAULT NULL,
                body longtext NOT NULL,
                moderation_status varchar(24) NOT NULL DEFAULT 'pending_review',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY thread (question_id,created_at)
            ) $charset;",
            "CREATE TABLE {$t['audit']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                question_id bigint(20) unsigned NOT NULL,
                actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                event varchar(80) NOT NULL,
                occurred_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY question_time (question_id,occurred_at)
            ) $charset;",
        ];
    }

    public static function installed(): bool {
        global $wpdb;
        foreach (self::tables() as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return false;
        }
        return true;
    }

    public static function setup_page(): void {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', '', ['response' => 403]);
        echo '<div class="wrap"><h1>Ask Support · Storage setup</h1>';
        echo '<p>This creates five dedicated Ask Support tables. It does not import Fluent Forms responses, enable specialist access, or publish questions.</p>';
        echo '<p><strong>Status:</strong> ' . (self::installed() ? 'Tables installed' : 'Tables not installed') . '</p>';
        if (isset($_GET['bh_ask_schema']) && $_GET['bh_ask_schema'] === 'installed') echo '<div class="notice notice-success"><p>Schema installation completed. Verify tables and permissions before proceeding.</p></div>';
        if (!self::installed()) {
            echo '<p>Take and verify a staging database backup first. Type INSTALL to confirm.</p>';
            echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
            echo '<input type="hidden" name="action" value="bh3_ask_install_schema">';
            wp_nonce_field('bh3_ask_install_schema');
            echo '<label for="bh3-ask-confirm">Confirmation</label> <input id="bh3-ask-confirm" name="confirmation" required autocomplete="off"> ';
            submit_button('Install Ask Support tables', 'primary', 'submit', false);
            echo '</form>';
        }
        echo '</div>';
    }

    public static function install(): void {
        if (!current_user_can('manage_options') || strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') {
            wp_die('Not authorised.', '', ['response' => 403]);
        }
        check_admin_referer('bh3_ask_install_schema');
        $confirmation = sanitize_text_field(wp_unslash($_POST['confirmation'] ?? ''));
        if ($confirmation !== 'INSTALL') wp_die('Type INSTALL to confirm.', '', ['response' => 400]);
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (self::schema_sql() as $sql) dbDelta($sql);
        if (!self::installed()) wp_die('Installation could not be verified. Check the database before retrying.', '', ['response' => 500]);
        wp_safe_redirect(admin_url('admin.php?page=bh3-ask-support-setup&bh_ask_schema=installed'));
        exit;
    }

    /**
     * This is the sole read authorisation predicate for specialist questions.
     * Caller must still verify question visibility/status and moderate the content.
     */
    public static function can_specialist_access(int $question_id, int $user_id): bool {
        if (!$question_id || !$user_id || !self::installed()) return false;
        global $wpdb;
        $t = self::tables();
        $organiser_id = absint(get_user_meta($user_id, 'bh3_organiser_id', true));
        if (!$organiser_id) return false;
        $prefs = (array) get_user_meta($user_id, 'bh3_ask_support_preferences', true);
        if (empty($prefs['opted_in'])) return false;
        $category = $wpdb->get_var($wpdb->prepare("SELECT category_slug FROM `{$t['questions']}` WHERE id=%d AND status='open' LIMIT 1", $question_id));
        if (!$category || !in_array($category, (array) ($prefs['categories'] ?? []), true)) return false;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$t['specialists']}` WHERE user_id=%d AND organiser_id=%d AND category_slug=%s AND opted_in=1 AND approval_status='approved' LIMIT 1",
            $user_id, $organiser_id, $category
        ));
    }
}
BH_Ask_Support_Storage_3::register();
