<?php
if (!defined('ABSPATH')) exit;
/** Edit only low-risk WP profile fields. Authentication remains with FluentAuth/WordPress. */
final class BH_Family_Account_3 {
    const DETAILS_META = '_bh3_family_account_details';
    public static function details() {
        $defaults = array('date_of_birth'=>'','address_line1'=>'','address_line2'=>'','city'=>'','county'=>'','postcode'=>'','country'=>'');
        if (!BH_Family_Preferences_3::can_edit()) return $defaults;
        $saved = get_user_meta(get_current_user_id(),self::DETAILS_META,true);
        return is_array($saved) ? array_merge($defaults,array_intersect_key($saved,$defaults)) : $defaults;
    }
    public static function register() { add_action('admin_post_bh3_family_account', array(__CLASS__, 'save')); }
    public static function save() {
        if (!BH_Family_Preferences_3::can_edit()
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
        $details = array();
        $lengths = array('address_line1'=>160,'address_line2'=>160,'city'=>100,'county'=>100,'postcode'=>20,'country'=>80);
        foreach ($lengths as $key=>$limit) {
            $details[$key] = isset($_POST[$key]) && is_scalar($_POST[$key])
                ? substr(sanitize_text_field(wp_unslash($_POST[$key])),0,$limit) : '';
        }
        $dob = isset($_POST['date_of_birth']) && is_scalar($_POST['date_of_birth'])
            ? sanitize_text_field(wp_unslash($_POST['date_of_birth'])) : '';
        if ($dob !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$dob,wp_timezone());
            if (!$parsed || $parsed->format('Y-m-d') !== $dob || $parsed > new DateTimeImmutable('today',wp_timezone()) || $parsed->format('Y') < 1900) {
                wp_safe_redirect(add_query_arg('bh3_profile_status','invalid_date',home_url('/family/settings/')) . '#bh3-account');
                exit;
            }
        }
        $details['date_of_birth'] = $dob;
        $result = wp_update_user($fields);
        if (!is_wp_error($result)) update_user_meta($user->ID,self::DETAILS_META,$details);
        $redirect = wp_get_referer() ?: home_url('/family/settings/');
        wp_safe_redirect(add_query_arg('bh3_profile_status',is_wp_error($result) ? 'error' : 'saved',$redirect));
        exit;
    }
}
