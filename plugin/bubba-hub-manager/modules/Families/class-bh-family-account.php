<?php
if (!defined('ABSPATH')) exit;
/** Edit only low-risk WP profile fields. Authentication remains with FluentAuth/WordPress. */
final class BH_Family_Account_3 {
    public static function register() { add_action('admin_post_bh3_family_account', array(__CLASS__, 'save')); }
    public static function save() {
        if (!BH_Family_Preferences_3::enabled() || !is_user_logged_in() || current_user_can('manage_options')
            || !isset($_POST['_wpnonce']) || !is_scalar($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bh3_family_account')) {
            wp_die(esc_html__('Profile editing is unavailable.', 'bubba-hub-manager'), '', array('response'=>403));
        }
        $user = wp_get_current_user();
        $fields = array('ID'=>$user->ID);
        foreach (array('first_name','last_name','display_name') as $key) {
            $fields[$key] = isset($_POST[$key]) && is_scalar($_POST[$key])
                ? substr(sanitize_text_field(wp_unslash($_POST[$key])),0,80) : (string)$user->$key;
        }
        if ($fields['display_name'] === '') $fields['display_name'] = $user->display_name;
        $result = wp_update_user($fields);
        $redirect = wp_get_referer() ?: home_url('/family/settings/');
        wp_safe_redirect(add_query_arg('bh3_profile_status',is_wp_error($result) ? 'error' : 'saved',$redirect));
        exit;
    }
}
