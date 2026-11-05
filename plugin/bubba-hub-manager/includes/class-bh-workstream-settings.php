<?php
/** Safe admin diagnostics for Family Hub and Leader Portal; no configuration writes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Workstream_Settings_3 {
    public static function register() {
        add_menu_page('Family Hub settings', 'Family Hub', 'manage_options', 'bh3-family-settings', array(__CLASS__, 'family'), 'dashicons-groups', 27);
        add_submenu_page('bh3-family-settings', 'Family display settings', 'Display settings', 'manage_options', 'bh3-family-display', array(__CLASS__, 'family_display'));
        add_submenu_page('bubba-hub-3-status', 'Leader Portal settings', 'Leader settings', 'manage_options', 'bh3-leader-settings', array(__CLASS__, 'leader'));
        add_menu_page('Support & Help', 'Support & Help', 'manage_options', 'bh3-support-help', array(__CLASS__, 'support'), 'dashicons-sos', 29);
        add_menu_page('Team', 'Team', 'manage_options', 'bh3-team', array(__CLASS__, 'team'), 'dashicons-groups', 30);
    }

    const FAMILY_DISPLAY_OPTION = 'bh3_family_display_preferences';

    public static function family_display_defaults() {
        return array('show_saved' => 1, 'show_planner' => 1, 'show_visited' => 1, 'show_discover' => 1, 'cards_per_row' => 2);
    }
    public static function family_display_current() {
        $stored = get_option(self::FAMILY_DISPLAY_OPTION, array());
        if (!is_array($stored)) $stored = array();
        return array_merge(self::family_display_defaults(), array_intersect_key($stored, self::family_display_defaults()));
    }
    public static function family_display_save() {
        if (!current_user_can('manage_options')) wp_die('Administrator permission required.', '', array('response' => 403));
        check_admin_referer('bh3_family_display_save');
        $raw = isset($_POST['bh3_family_display']) && is_array($_POST['bh3_family_display']) ? wp_unslash($_POST['bh3_family_display']) : array();
        $out = array();
        foreach (array('show_saved', 'show_planner', 'show_visited', 'show_discover') as $key) {
            $out[$key] = empty($raw[$key]) ? 0 : 1;
        }
        $out['cards_per_row'] = isset($raw['cards_per_row']) ? max(1, min(3, absint($raw['cards_per_row']))) : 2;
        update_option(self::FAMILY_DISPLAY_OPTION, $out, false);
        wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=bh3-family-display')));
        exit;
    }
    public static function family_display() {
        if (!current_user_can('manage_options')) wp_die('Administrator permission required.', '', array('response' => 403));
        $v = self::family_display_current();
        echo '<div class="wrap"><h1>Family Hub display settings</h1><p>Save display preferences for the future Family Hub integration. These options do not yet change the public Family Hub, enable private data or grant permissions.</p>';
        if (isset($_GET['updated']) && sanitize_key(wp_unslash($_GET['updated'])) === '1') echo '<div class="notice notice-success"><p>Preferences saved.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bh3_family_display_save');
        echo '<input type="hidden" name="action" value="bh3_family_display_save">';
        foreach (array('show_saved'=>'Saved activities','show_planner'=>'Family planner','show_visited'=>'Visited activities','show_discover'=>'Discover activities') as $key=>$label) {
            echo '<p><label><input type="checkbox" name="bh3_family_display[' . esc_attr($key) . ']" value="1" ' . checked($v[$key], 1, false) . '> ' . esc_html($label) . '</label></p>';
        }
        echo '<p><label for="bh3-family-columns">Preferred desktop card columns</label> <select id="bh3-family-columns" name="bh3_family_display[cards_per_row]">';
        for ($i=1; $i<=3; $i++) echo '<option value="' . esc_attr((string)$i) . '"' . selected((int)$v['cards_per_row'], $i, false) . '>' . esc_html((string)$i) . '</option>';
        echo '</select></p>';
        submit_button('Save display preferences');
        echo '</form></div>';
    }
    private static function header($title, $description) {
        if (!current_user_can('manage_options')) {
            wp_die('Administrator permission required.', '', array('response' => 403));
        }
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p>';
        echo '<style>.bh3-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px;max-width:1100px}.bh3-settings-card{background:white;border:1px solid #d9e4df;border-radius:12px;padding:20px;box-sizing:border-box}.bh3-settings-card h2{margin-top:0}.bh3-settings-card p{line-height:1.55}.bh3-settings-card code{overflow-wrap:anywhere}.bh3-settings-card .button{white-space:normal;height:auto;padding:6px 12px}</style><div class="bh3-settings-grid">';
    }
    private static function card($title, $body, $url = '', $label = '') {
        echo '<section class="bh3-settings-card"><h2>' . esc_html($title) . '</h2><p>' . esc_html($body) . '</p>';
        if ($url) echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
        echo '</section>';
    }
    private static function footer() { echo '</div><p>These diagnostics do not enable modules, change user roles, install tables or modify family records.</p></div>'; }
    private static function staging() {
        return strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) === 'staging.bubbahub.co.uk';
    }
    public static function support() {
        self::header('Support & Help', 'Administration overview for family support, website help and resources. The full support system is not yet migrated.');
        self::card('Family support', 'Guides and resources for parents and carers are planned. No live content management is enabled here yet.');
        self::card('Website support', 'Help articles, contact requests and FAQs will be integrated after workflow and permissions testing.');
        self::card('Resource library', 'Resource management and blog publishing remain pending integration.');
        self::footer();
    }
    public static function team() {
        self::header('Team', 'Administration overview for volunteers and area coordinators. No volunteer permissions are changed here.');
        self::card('Volunteers', 'Volunteer registration and assignments are pending migration and testing.');
        self::card('Area coordinators', 'Regional ownership and assignment controls will require permission and audit checks.');
        self::card('Team communications', 'Team messaging and notifications are not yet integrated.');
        self::footer();
    }
    public static function family() {
        self::header('Family Hub settings', 'Current implementation and safety gates. This is a read-only administration screen.');
        self::card('Module readiness', BH_Manager_3::FEATURE_FAMILY ? 'Feature marked enabled.' : 'Feature remains disabled pending staging parity and identity tests.');
        self::card('Private preview', self::staging() && defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true ? 'Staging private preview flag is enabled. Verified family identity is still required.' : 'Private preview is not enabled for this site.');
        self::card('Family actions', self::staging() && class_exists('BH_Family_Actions_3') && BH_Family_Actions_3::enabled() ? 'Staging family actions are enabled. Test with a verified family account.' : 'Family write actions are disabled.');
        self::card('Account linking', function_exists('bh_wp_resolve_legacy_identity') ? 'Legacy identity bridge function is available. This does not prove any individual account is linked.' : 'Legacy identity bridge function is unavailable; private data must remain inaccessible.');
        self::card('Display preferences', 'Choose which dashboard sections to show once frontend integration is ready.', admin_url('admin.php?page=bh3-family-display'), 'Edit display settings');
        self::card('Frontend shortcode', 'Use [bubbahub_family_hub] on a private staging test page. Do not expose real family records until QA is complete.');
        self::footer();
    }
    public static function leader() {
        self::header('Leader Portal settings', 'Current implementation and administration entry points. This screen does not grant leader access.');
        self::card('Module readiness', BH_Manager_3::FEATURE_LEADER ? 'Feature marked enabled.' : 'Feature remains disabled pending staging permission and workflow tests.');
        self::card('My Listings', 'View the existing listing management screen with its own permission checks.', admin_url('admin.php?page=bh3-my-listings'), 'Open My Listings');
        self::card('Editing', 'Existing activity editor. Ownership and saving must be tested with a leader account.', admin_url('admin.php?page=bh3-leader-edit'), 'Open editor');
        self::card('Sessions', 'Existing timetable management. Verify class ownership and save/delete behaviour.', admin_url('admin.php?page=bh3-leader-sessions'), 'Open sessions');
        self::card('Venues', 'Existing venue selection and attachment. Verify permissions before use.', admin_url('admin.php?page=bh3-leader-venues'), 'Open venues');
        self::card('Authentication', 'Uses WordPress permissions; verify FluentAuth login, role mapping and organiser ownership on staging.');
        self::footer();
    }
}
