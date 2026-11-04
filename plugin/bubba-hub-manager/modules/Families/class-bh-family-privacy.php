<?php
if (!defined('ABSPATH')) exit;
/** Explicit, account-scoped privacy choices. No external consent sync. */
final class BH_Family_Privacy_3 {
    const META = '_bh3_family_privacy';
    public static function choices() {
        return array(
            'personalisation'=>array('Personalised activity suggestions','Use family profiles to tailor activity suggestions. Children’s details are never made public by this choice.'),
            'saved_planner'=>array('Connect saved activities and planner','Keep saved activities and planned sessions associated with your account.'),
            'marketing'=>array('Occasional Bubba Hub news','Optional marketing messages, separate from essential account messages.'),
            'leader_contact'=>array('Contact from relevant class leaders','Allow contact relating to an enquiry you initiate. This does not share your profile publicly.')
        );
    }
    public static function read() {
        $defaults = array_fill_keys(array_keys(self::choices()),false);
        if (!BH_Family_Preferences_3::can_edit()) return $defaults;
        $saved = get_user_meta(get_current_user_id(),self::META,true);
        if (!is_array($saved)) return $defaults;
        foreach ($defaults as $key=>$value) $defaults[$key] = !empty($saved[$key]);
        return $defaults;
    }
    public static function register() { add_action('admin_post_bh3_family_privacy',array(__CLASS__,'save')); }
    public static function save() {
        if (!BH_Family_Preferences_3::can_edit() || !isset($_POST['_wpnonce']) || !is_scalar($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),'bh3_family_privacy')) {
            wp_die(esc_html__('Privacy settings unavailable.','bubba-hub-manager'),'',array('response'=>403));
        }
        $values = array();
        foreach (array_keys(self::choices()) as $key) $values[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
        $values['updated_at'] = current_time('mysql',true);
        update_user_meta(get_current_user_id(),self::META,$values);
        // Choices are stored, not interpreted as consent for external services.
        wp_safe_redirect(add_query_arg('bh3_privacy_saved','1',home_url('/family/settings/')) . '#bh3-privacy');
        exit;
    }
}
