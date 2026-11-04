<?php
/**
 * Fluent Forms Free enquiry inbox with separate workflow status tracking.
 * Uses Fluent Forms' own submissions table; does not duplicate private answers.
 */
defined('ABSPATH') || exit;


// Workflow metadata is separate from Fluent Forms responses and stores no family message content.
function bh_support_history_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bh_support_enquiry_history';
}

function bh_support_status_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bh_support_enquiry_status';
}

add_action('admin_post_bh3_support_install_schema', static function (): void {
    // Explicit administrator-only schema install, staging only.
    if (!current_user_can('manage_options')) wp_die('Administrator permission required.', '', ['response' => 403]);
    check_admin_referer('bh3_support_install_schema');
    if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') wp_die('Staging only.', '', ['response' => 403]);
    if (!isset($_POST['bh3_support_confirm']) || sanitize_text_field(wp_unslash($_POST['bh3_support_confirm'])) !== 'INSTALL') wp_die('Type INSTALL to confirm.', '', ['response' => 400]);
    if (get_option('bh_support_status_schema_version') === '2') {
        wp_safe_redirect(admin_url('admin.php?page=bh-support-enquiries&bh3_schema=already'));
        exit;
    }
    global $wpdb;
    // Refuse to modify existing tables whose ownership/schema has not been reviewed.
    $status_table = bh_support_status_table();
    $history_table = bh_support_history_table();
    foreach ([$status_table, $history_table] as $existing_table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($existing_table))) === $existing_table) {
            wp_die(esc_html__('A support workflow table already exists. Review its schema and ownership before installation.', 'bubbahub'), '', ['response' => 409]);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = bh_support_status_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        entry_id bigint(20) unsigned NOT NULL,
        form_id bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        updated_at datetime NOT NULL,
        updated_by bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (entry_id),
        KEY form_id (form_id)
    ) $charset;");
    $history = bh_support_history_table();
    dbDelta("CREATE TABLE $history (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        entry_id bigint(20) unsigned NOT NULL,
        form_id bigint(20) unsigned NOT NULL,
        old_status varchar(20) NOT NULL,
        new_status varchar(20) NOT NULL,
        changed_at datetime NOT NULL,
        changed_by bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id),
        KEY entry_id (entry_id),
        KEY form_id (form_id)
    ) $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $history)) === $history) {
        update_option('bh_support_status_schema_version', '2', false);
    }
    wp_safe_redirect(admin_url('admin.php?page=bh-support-enquiries&bh3_schema=' . (get_option('bh_support_status_schema_version') === '2' ? 'installed' : 'error')));
    exit;
});

add_action('admin_menu', static function (): void {
    add_submenu_page('bh-support-dashboard', __('Bubba Hub Enquiries', 'bubbahub'), __('Bubba Hub Enquiries', 'bubbahub'),
        'manage_options', 'bh-support-enquiries', 'bh_support_enquiries_page');
});

function bh_support_enquiries_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view enquiries.', 'bubbahub'));
    }
    if (isset($_POST['bh_support_save_form_ids'])) {
        check_admin_referer('bh_support_form_settings');
        $raw = isset($_POST['bh_support_form_ids']) ? sanitize_text_field(wp_unslash($_POST['bh_support_form_ids'])) : '';
        $ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,]+/', $raw)))));
        update_option('bh_support_fluent_form_ids', $ids, false);
        echo '<div class="notice notice-success"><p>' . esc_html__('Support form settings saved.', 'bubbahub') . '</p></div>';
    }
    if (isset($_POST['bh_support_change_status'])) {
        check_admin_referer('bh_support_change_status');
        global $wpdb;
        $entry_id = isset($_POST['bh_support_entry_id']) ? absint($_POST['bh_support_entry_id']) : 0;
        $status = isset($_POST['bh_support_status']) ? sanitize_key(wp_unslash($_POST['bh_support_status'])) : '';
        $allowed = ['new', 'in_progress', 'resolved'];
        $configured = array_values(array_filter(array_map('absint', (array) get_option('bh_support_fluent_form_ids', []))));
        $submissions_table = $wpdb->prefix . 'fluentform_submissions';
        if ($entry_id && in_array($status, $allowed, true) && $configured
            && get_option('bh_support_status_schema_version') === '2'
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', bh_support_status_table())) === bh_support_status_table()
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', bh_support_history_table())) === bh_support_history_table()
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $submissions_table)) === $submissions_table) {
            $form_id = (int) $wpdb->get_var($wpdb->prepare("SELECT form_id FROM `$submissions_table` WHERE id = %d", $entry_id));
            if (in_array($form_id, $configured, true)) {
                // Keep the workflow state and its audit event in the same transaction.
                // InnoDB is required for rollback; check the storage engine before writing.
                $status_table_name = bh_support_status_table();
                $history_table_name = bh_support_history_table();
                $engines = $wpdb->get_results($wpdb->prepare(
                    "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)",
                    $status_table_name, $history_table_name
                ));
                $transactional = count((array) $engines) === 2;
                foreach ((array) $engines as $engine) {
                    if (strtoupper((string) $engine->ENGINE) !== 'INNODB') $transactional = false;
                }
                if (!$transactional || $wpdb->query('START TRANSACTION') === false) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Workflow changes require InnoDB audit tables. No status change was made.', 'bubbahub') . '</p></div>';
                } else {
                    $previous = $wpdb->get_var($wpdb->prepare(
                        "SELECT status FROM `$status_table_name` WHERE entry_id = %d AND form_id = %d FOR UPDATE",
                        $entry_id, $form_id
                    ));
                    $previous = $previous ?: 'new';
                    if ($previous === $status) {
                        $wpdb->query('COMMIT');
                    } else {
                        $changed_at = current_time('mysql', true);
                        $actor = get_current_user_id();
                        $saved = $wpdb->replace($status_table_name, [
                            'entry_id' => $entry_id, 'form_id' => $form_id, 'status' => $status,
                            'updated_at' => $changed_at, 'updated_by' => $actor,
                        ], ['%d', '%d', '%s', '%s', '%d']);
                        $logged = $saved === false ? false : $wpdb->insert($history_table_name, [
                            'entry_id' => $entry_id, 'form_id' => $form_id,
                            'old_status' => $previous, 'new_status' => $status,
                            'changed_at' => $changed_at, 'changed_by' => $actor,
                        ], ['%d', '%d', '%s', '%s', '%d']);
                        if ($saved === false || $logged === false) {
                            $wpdb->query('ROLLBACK');
                            echo '<div class="notice notice-error"><p>' . esc_html__('The enquiry update was rolled back because a database write failed.', 'bubbahub') . '</p></div>';
                        } elseif ($wpdb->query('COMMIT') === false) {
                            // A failed COMMIT can leave the outcome uncertain; do not claim rollback succeeded.
                            $wpdb->query('ROLLBACK');
                            echo '<div class="notice notice-error"><p>' . esc_html__('The database could not confirm the enquiry update. Check the status and audit history before retrying.', 'bubbahub') . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>' . esc_html__('Enquiry status and activity history updated.', 'bubbahub') . '</p></div>';
                        }
                    }
                }
            }
        }
    }
    $ids = array_values(array_filter(array_map('absint', (array) get_option('bh_support_fluent_form_ids', []))));
    echo '<div class="wrap"><h1>' . esc_html__('Bubba Hub · Support Enquiries', 'bubbahub') . '</h1>';
    if (get_option('bh_support_status_schema_version') !== '2' && strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) === 'staging.bubbahub.co.uk') {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Workflow tracking tables have not been installed. Back up staging before proceeding.', 'bubbahub') . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bh3_support_install_schema">';
        wp_nonce_field('bh3_support_install_schema');
        echo '<label for="bh3-support-confirm">' . esc_html__('Type INSTALL to create support workflow tables on staging:', 'bubbahub') . '</label> ';
        echo '<input id="bh3-support-confirm" name="bh3_support_confirm" required autocomplete="off"> ';
        echo '<button class="button button-secondary" type="submit">' . esc_html__('Install support tables', 'bubbahub') . '</button></form>';
    }
    if (isset($_GET['bh3_schema'])) {
        $schema_result = sanitize_key(wp_unslash($_GET['bh3_schema']));
        if (in_array($schema_result, ['installed', 'already', 'error'], true)) {
            echo '<div class="notice ' . ($schema_result === 'error' ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($schema_result === 'installed' ? 'Support workflow tables installed.' : ($schema_result === 'already' ? 'Support workflow tables already installed.' : 'Support workflow installation failed; inspect database logs.')) . '</p></div>';
        }
    }
    echo '<p>' . esc_html__('Select the Fluent Forms form IDs used for support enquiries. Entries remain stored in Fluent Forms; this page shows limited metadata only. Open Fluent Forms to view private responses.', 'bubbahub') . '</p>';
    echo '<form method="post">';
    wp_nonce_field('bh_support_form_settings');
    echo '<label for="bh_support_form_ids"><strong>' . esc_html__('Support form IDs (comma-separated)', 'bubbahub') . '</strong></label><br>';
    echo '<input id="bh_support_form_ids" name="bh_support_form_ids" type="text" class="regular-text" value="' . esc_attr(implode(', ', $ids)) . '" placeholder="12, 15">';
    echo ' <button class="button button-primary" name="bh_support_save_form_ids" value="1">' . esc_html__('Save', 'bubbahub') . '</button></form>';
    if (!$ids) {
        echo '<p>' . esc_html__('No support forms configured yet.', 'bubbahub') . '</p></div>';
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'fluentform_submissions';
    // Fluent Forms versions do not consistently expose the same bootstrap constant.
    // Verify the submissions table itself before making any queries.
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Fluent Forms submissions are not available on this installation.', 'bubbahub') . '</p></div></div>';
        return;
    }
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $filter = isset($_GET['bh_workflow']) && is_string($_GET['bh_workflow']) ? sanitize_key(wp_unslash($_GET['bh_workflow'])) : '';
    if (!in_array($filter, ['', 'new', 'in_progress', 'resolved'], true)) $filter = '';
    $offset = ($page - 1) * 20;
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $where = "form_id IN ($placeholders)";
    $status_table = bh_support_status_table();
    $has_workflow = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $status_table)) === $status_table;
    if (!$has_workflow) $filter = '';
    $join = $has_workflow ? " LEFT JOIN `$status_table` w ON w.entry_id = s.id AND w.form_id = s.form_id" : '';
    $where = 's.' . $where;
    $query_params = $ids;
    if ($filter !== '' && $has_workflow) {
        if ($filter === 'new') {
            $where .= " AND (w.status IS NULL OR w.status = %s)";
        } else {
            $where .= " AND w.status = %s";
        }
        $query_params[] = $filter;
    }
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` s $join WHERE $where", ...$query_params));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.form_id, s.status, s.created_at FROM `$table` s $join WHERE $where ORDER BY s.id DESC LIMIT %d OFFSET %d",
        ...array_merge($query_params, [20, $offset])
    ));
    echo '<form method="get" style="margin:18px 0">';
    echo '<input type="hidden" name="page" value="bh-support-enquiries">';
    echo '<label for="bh-workflow-filter">' . esc_html__('Show', 'bubbahub') . '</label> ';
    echo '<select id="bh-workflow-filter" name="bh_workflow">';
    foreach (['' => __('All enquiries', 'bubbahub'), 'new' => __('New', 'bubbahub'), 'in_progress' => __('In Progress', 'bubbahub'), 'resolved' => __('Resolved', 'bubbahub')] as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filter, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> <button class="button">' . esc_html__('Filter', 'bubbahub') . '</button></form>';
    echo '<p><strong>' . esc_html(sprintf(_n('%d enquiry', '%d enquiries', $count, 'bubbahub'), $count)) . '</strong></p>';
    $status_table = bh_support_status_table();
    $status_map = [];
    $status_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $status_table)) === $status_table;
    if ($rows && $status_table_exists) {
        $entry_ids = array_map(static function ($row) { return (int) $row->id; }, $rows);
        $keys = implode(',', array_fill(0, count($entry_ids), '%d'));
        $states = $wpdb->get_results($wpdb->prepare("SELECT entry_id, status FROM `$status_table` WHERE entry_id IN ($keys)", ...$entry_ids));
        foreach ((array) $states as $state) $status_map[(int) $state->entry_id] = $state->status;
    }
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Entry', 'bubbahub') . '</th><th>' . esc_html__('Form', 'bubbahub') . '</th><th>' . esc_html__('Submitted', 'bubbahub') . '</th><th>' . esc_html__('Fluent Forms status', 'bubbahub') . '</th><th>' . esc_html__('Support workflow', 'bubbahub') . '</th></tr></thead><tbody>';
    foreach ((array) $rows as $entry) {
        echo '<tr><td><a href="' . esc_url(add_query_arg(['page' => 'bh-support-enquiries', 'bh_history_entry' => (int) $entry->id], admin_url('admin.php'))) . '">#' . esc_html((string) $entry->id) . '</a></td><td>#' . esc_html((string) $entry->form_id) . '</td><td>' . esc_html((string) $entry->created_at) . '</td><td>' . esc_html((string) $entry->status) . '</td><td>';
        if (!$status_table_exists) {
            echo esc_html__('Workflow table unavailable', 'bubbahub') . '</td></tr>';
            continue;
        }
        echo '<form method="post">';
        wp_nonce_field('bh_support_change_status');
        echo '<input type="hidden" name="bh_support_entry_id" value="' . esc_attr((string) $entry->id) . '">';
        echo '<select name="bh_support_status" aria-label="' . esc_attr__('Support workflow status', 'bubbahub') . '">';
        foreach (['new' => __('New', 'bubbahub'), 'in_progress' => __('In Progress', 'bubbahub'), 'resolved' => __('Resolved', 'bubbahub')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status_map[(int) $entry->id] ?? 'new', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <button class="button" name="bh_support_change_status" value="1">' . esc_html__('Update', 'bubbahub') . '</button></form></td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="5">' . esc_html__('No entries found.', 'bubbahub') . '</td></tr>';
    echo '</tbody></table>';
    $pages = (int) ceil($count / 20);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= min($pages, 100); $i++) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'bh-support-enquiries', 'paged' => $i, 'bh_workflow' => $filter], admin_url('admin.php'))) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</p>';
    }
    $history_entry = isset($_GET['bh_history_entry']) ? absint($_GET['bh_history_entry']) : 0;
    if ($history_entry && get_option('bh_support_status_schema_version') === '2') {
        $entry_form = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT form_id FROM `$table` WHERE id = %d", $history_entry
        ));
        if (in_array($entry_form, $ids, true)) {
            $history_table = bh_support_history_table();
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT old_status, new_status, changed_at, changed_by FROM `$history_table`
                 WHERE entry_id = %d AND form_id = %d ORDER BY id DESC LIMIT 50",
                $history_entry, $entry_form
            ));
            echo '<h2>' . esc_html(sprintf(__('Activity history for enquiry #%d', 'bubbahub'), $history_entry)) . '</h2>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('When (UTC)', 'bubbahub') . '</th><th>' . esc_html__('Changed by', 'bubbahub') . '</th><th>' . esc_html__('Change', 'bubbahub') . '</th></tr></thead><tbody>';
            foreach ((array) $events as $event) {
                $staff = get_userdata((int) $event->changed_by);
                echo '<tr><td>' . esc_html($event->changed_at) . '</td><td>' . esc_html($staff ? $staff->display_name : __('Former user', 'bubbahub')) . '</td><td>' . esc_html($event->old_status . ' → ' . $event->new_status) . '</td></tr>';
            }
            if (!$events) echo '<tr><td colspan="3">' . esc_html__('No status changes recorded yet.', 'bubbahub') . '</td></tr>';
            echo '</tbody></table>';
        }
    }
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=fluent_forms')) . '">' . esc_html__('Open Fluent Forms', 'bubbahub') . '</a></p></div>';
}
