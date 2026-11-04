<?php
if (!defined('ABSPATH')) exit;
/** Family preferences live in WordPress user meta, never shared with other families. */
final class BH_Family_Preferences_3 {
    const META = '_bh3_family_preferences';
    public static function interests() {
        return array('baby_groups'=>'Baby groups','toddler_groups'=>'Toddler groups','sensory'=>'Sensory play','music'=>'Music & movement','outdoors'=>'Outdoor adventures','arts'=>'Arts & crafts','libraries'=>'Libraries & stories','sports'=>'Sports & swimming','parent_support'=>'Parent support','pregnancy'=>'Pregnancy & antenatal','free_events'=>'Free events');
    }
    public static function days() {
        return array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday');
    }
    public static function defaults() {
        return array('interests'=>array(),'days'=>array(),'town'=>'','region'=>'','max_price'=>null,'postcode'=>'','radius'=>10,'free_only'=>false,'term_time'=>false);
    }
    public static function enabled() {
        return wp_parse_url(home_url(), PHP_URL_HOST) === 'staging.bubbahub.co.uk'
            && defined('BH3_FAMILY_PRIVATE_PREVIEW') && BH3_FAMILY_PRIVATE_PREVIEW === true;
    }
    public static function can_edit() {
        return self::enabled() && is_user_logged_in() && !current_user_can('manage_options')
            && BH_Family_Identity_3::current_legacy_id() > 0;
    }
    public static function read() {
        if (!self::can_edit()) return self::defaults();
        $value = get_user_meta(get_current_user_id(), self::META, true);
        // New table is authoritative only when a row exists; older meta remains readable.
        global $wpdb;
        $table = $wpdb->prefix . 'bh_family_preferences';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            $json = $wpdb->get_var($wpdb->prepare("SELECT preferences_json FROM `{$table}` WHERE user_id = %d", get_current_user_id()));
            if (is_string($json)) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) $value = $decoded;
            }
        }
        return is_array($value) ? array_merge(self::defaults(), $value) : self::defaults();
    }
    public static function register() {
        add_action('admin_post_bh3_family_preferences', array(__CLASS__, 'save'));
    }
    public static function save() {
        if (!self::can_edit() || !isset($_POST['_wpnonce']) || !is_scalar($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bh3_family_preferences')) {
            wp_die(esc_html__('Family preferences are unavailable.', 'bubba-hub-manager'), '', array('response'=>403));
        }
        $interests = isset($_POST['interests']) && is_array($_POST['interests']) ? array_keys(array_intersect_key(self::interests(), array_flip(array_map('sanitize_key', wp_unslash($_POST['interests']))))) : array();
        $days = isset($_POST['days']) && is_array($_POST['days']) ? array_keys(array_intersect_key(self::days(), array_flip(array_map('sanitize_key', wp_unslash($_POST['days']))))) : array();
        $town = isset($_POST['town']) && is_scalar($_POST['town']) ? substr(sanitize_text_field(wp_unslash($_POST['town'])), 0, 80) : '';
        $region = isset($_POST['region']) && is_scalar($_POST['region']) ? substr(sanitize_text_field(wp_unslash($_POST['region'])),0,120) : '';
        $price_raw = isset($_POST['max_price']) && is_scalar($_POST['max_price']) ? trim((string)wp_unslash($_POST['max_price'])) : '';
        if ($price_raw !== '' && (!is_numeric($price_raw) || (float)$price_raw < 0 || (float)$price_raw > 10000)) {
            wp_safe_redirect(add_query_arg('bh3_preferences_error','price',home_url('/family/settings/')) . '#bh3-preferences');
            exit;
        }
        $max_price = $price_raw === '' ? null : round((float)$price_raw,2);
        $postcode = isset($_POST['postcode']) && is_scalar($_POST['postcode']) ? strtoupper(trim(sanitize_text_field(wp_unslash($_POST['postcode'])))) : '';
        if ($postcode !== '' && (strlen($postcode) > 9 || !preg_match('/^[A-Z0-9 ]+$/', $postcode))) $postcode = '';
        $radius = isset($_POST['radius']) && is_scalar($_POST['radius']) ? absint($_POST['radius']) : 10;
        if (!in_array($radius, array(5,10,15,25,50), true)) $radius = 10;
        $preferences = array(
            'interests'=>$interests, 'days'=>$days, 'town'=>$town, 'region'=>$region, 'max_price'=>$max_price, 'postcode'=>$postcode,
            'radius'=>$radius, 'free_only'=>!empty($_POST['free_only']), 'term_time'=>!empty($_POST['term_time'])
        );
        global $wpdb;
        $table = $wpdb->prefix . 'bh_family_preferences';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            wp_die('Family preferences table is unavailable.', '', array('response'=>503));
        }
        $written = $wpdb->replace($table, array('user_id'=>get_current_user_id(), 'preferences_json'=>wp_json_encode($preferences), 'updated_at'=>current_time('mysql')), array('%d','%s','%s'));
        if ($written === false) wp_die('Unable to save family preferences.', '', array('response'=>500));
        wp_safe_redirect(add_query_arg('bh3_preferences_saved','1',wp_get_referer() ?: home_url('/family/')));
        exit;
    }
}
