<?php
/**
 * Staging-only Ask Support leader preferences. No question access or routing is enabled.
 */
defined('ABSPATH') || exit;

final class BH_Ask_Support_Optin_3 {
    public static function categories(): array {
        return [
            'sleep' => 'Baby & child sleep',
            'feeding' => 'Feeding & breastfeeding',
            'pregnancy' => 'Pregnancy & postnatal',
            'development' => 'Child development',
            'wellbeing' => 'Parent wellbeing',
            'activities' => 'Activities & classes',
        ];
    }

    public static function register(): void {
        if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'staging.bubbahub.co.uk') return;
        add_shortcode('bh_leader_ask_support', [__CLASS__, 'render']);
        add_action('admin_post_bh3_save_ask_support', [__CLASS__, 'save']);
    }

    private static function linked_organiser(): int {
        if (!is_user_logged_in()) return 0;
        $id = absint(get_user_meta(get_current_user_id(), 'bh3_organiser_id', true));
        if (!$id) return 0;
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return 0;
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE id = %d LIMIT 1", $id)) ? $id : 0;
    }

    public static function save(): void {
        if (!is_user_logged_in() || !current_user_can('read') || !self::linked_organiser()) {
            wp_die('Not authorised.', '', ['response' => 403]);
        }
        check_admin_referer('bh3_save_ask_support');
        $enabled = isset($_POST['bh_ask_opt_in']) && $_POST['bh_ask_opt_in'] === '1';
        $raw = isset($_POST['bh_ask_categories']) && is_array($_POST['bh_ask_categories'])
            ? array_map('sanitize_key', wp_unslash($_POST['bh_ask_categories'])) : [];
        $selected = array_values(array_intersect(array_keys(self::categories()), $raw));
        if (!$enabled) $selected = [];
        // Approval is always a separate administrator action. Never grant it on opt-in.
        $user_id = get_current_user_id();
        $organiser_id = self::linked_organiser();
        $requested = $enabled && count($selected) > 0;
        $next = [
            'opted_in' => $requested,
            'categories' => $selected,
            'updated_at' => current_time('mysql', true),
        ];
        if (BH_Ask_Support_Storage_3::installed()) {
            global $wpdb;
            $table = BH_Ask_Support_Storage_3::tables()['specialists'];
            // Revoke removed categories and organiser transfers before creating new requests.
            // A previously approved category never survives a withdrawal.
            $existing = $wpdb->get_results($wpdb->prepare(
                "SELECT id,organiser_id,category_slug,opted_in,approval_status FROM `{$table}` WHERE user_id=%d",
                $user_id
            ), ARRAY_A);
            if (!is_array($existing)) wp_die('Unable to load specialist preferences.', '', ['response' => 503]);
            foreach ($existing as $row) {
                $retained = $requested && (int) $row['organiser_id'] === $organiser_id && in_array($row['category_slug'], $selected, true);
                if (!$retained) {
                    $ok = $wpdb->update($table,
                        ['opted_in' => 0, 'approval_status' => 'pending', 'updated_at' => current_time('mysql', true)],
                        ['id' => (int) $row['id']], ['%d','%s','%s'], ['%d']);
                    if ($ok === false) wp_die('Unable to revoke specialist access.', '', ['response' => 503]);
                }
            }
            if ($requested) {
                foreach ($selected as $category) {
                    $matching = null;
                    foreach ($existing as $row) {
                        if ($row['category_slug'] === $category) { $matching = $row; break; }
                    }
                    if ($matching && (int) $matching['organiser_id'] === $organiser_id && (int) $matching['opted_in'] === 1) {
                        // Preserve the approval decision for an unchanged active category.
                        continue;
                    }
                    if ($matching) {
                        $ok = $wpdb->update($table,
                            ['organiser_id' => $organiser_id, 'opted_in' => 1, 'approval_status' => 'pending', 'updated_at' => current_time('mysql', true)],
                            ['id' => (int) $matching['id']], ['%d','%d','%s','%s'], ['%d']);
                    } else {
                        $ok = $wpdb->insert($table,
                            ['organiser_id' => $organiser_id, 'user_id' => $user_id, 'category_slug' => $category,
                             'opted_in' => 1, 'approval_status' => 'pending', 'updated_at' => current_time('mysql', true)],
                            ['%d','%d','%s','%d','%s','%s']);
                    }
                    if ($ok === false) wp_die('Unable to create specialist approval request.', '', ['response' => 503]);
                }
            }
        } else {
            // Until schema is installed, preferences remain a non-operational interest list.
            $next['awaiting_setup'] = true;
        }
        update_user_meta($user_id, 'bh3_ask_support_preferences', $next);
        $url = wp_get_referer();
        if (!$url || wp_validate_redirect($url, false) === false) $url = home_url('/leader/');
        wp_safe_redirect(add_query_arg('ask_support_saved', '1', $url));
        exit;
    }

    public static function render(): string {
        if (!is_user_logged_in()) return '<p>Please sign in to manage Ask Support preferences.</p>';
        if (!self::linked_organiser()) return '<p>Your leader account must be linked to an organiser before you can join Ask Support.</p>';
        $preferences = (array) get_user_meta(get_current_user_id(), 'bh3_ask_support_preferences', true);
        $enabled = !empty($preferences['opted_in']);
        $selected = array_map('sanitize_key', (array) ($preferences['categories'] ?? []));
        ob_start(); ?>
        <section id="leader-ask-support" class="bh-leader-ask-support" style="max-width:850px;background:#fff;border:1px solid #dbe5df;border-radius:16px;padding:clamp(18px,4vw,30px);margin:24px 0;color:#203c31">
          <h2 style="margin-top:0">Ask Support · Specialist settings</h2>
          <p>Help families by answering questions in your areas of expertise. Select the topics you would like to support. Participation is subject to Bubba Hub approval.</p>
          <p><strong>Not yet active:</strong> these settings save your interest only. You will not receive questions until specialist verification, privacy controls and moderation are ready.</p>
          <?php if (isset($_GET['ask_support_saved']) && $_GET['ask_support_saved'] === '1') : ?><p role="status" style="color:#245e4a;font-weight:700">Your preferences have been saved.</p><?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bh3_save_ask_support">
            <?php wp_nonce_field('bh3_save_ask_support'); ?>
            <p><label><input type="checkbox" name="bh_ask_opt_in" value="1" <?php checked($enabled); ?>> <strong>I would like to join Ask Support</strong></label></p>
            <fieldset style="border:0;padding:0;margin:20px 0"><legend style="font-weight:700;margin-bottom:12px">Topics I can help with</legend>
              <?php foreach (self::categories() as $slug => $label) : ?>
                <label style="display:block;margin:10px 0"><input type="checkbox" name="bh_ask_categories[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected, true)); ?>> <?php echo esc_html($label); ?></label>
              <?php endforeach; ?>
            </fieldset>
            <button type="submit" style="background:#174b36;color:white;border:0;border-radius:9px;padding:12px 22px;cursor:pointer">Save preferences</button>
          </form>
          <p style="font-size:.9rem;color:#52685b">Private questions will only be accessible to approved specialists in matching categories. Parents' contact details will not be shared.</p>
        </section>
        <?php return (string) ob_get_clean();
    }
}
BH_Ask_Support_Optin_3::register();
