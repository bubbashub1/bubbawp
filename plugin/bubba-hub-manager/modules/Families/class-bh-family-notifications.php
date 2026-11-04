<?php
if (!defined('ABSPATH')) exit;
/** Family notification preferences. No messages are sent by this module. */
final class BH_Family_Notifications_3 {
    const META = '_bh3_family_notifications';
    public static function choices() {
        return array(
            'planner_reminders'=>array('Planner reminders','Reminders about activities in your planner.'),
            'booking_updates'=>array('Booking updates','Optional booking updates and changes. Essential booking messages may still be sent.'),
            'saved_activity_alerts'=>array('Saved activity alerts','New activities relevant to your interests.'),
            'support_replies'=>array('Support replies','Replies to your private support questions.'),
            'event_reminders'=>array('Event reminders','Reminders for saved family events.')
        );
    }
    public static function read() {
        $defaults = array_fill_keys(array_keys(self::choices()),false);
        $defaults['newsletter'] = false;
        if (!BH_Family_Preferences_3::can_edit()) return $defaults;
        $value = get_user_meta(get_current_user_id(),self::META,true);
        if (!is_array($value)) return $defaults;
        foreach ($defaults as $key=>$default) $defaults[$key] = !empty($value[$key]);
        return $defaults;
    }
    public static function register() { add_action('admin_post_bh3_family_notifications',array(__CLASS__,'save')); }
    public static function save() {
        if (!BH_Family_Preferences_3::can_edit() || !isset($_POST['_wpnonce']) || !is_scalar($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),'bh3_family_notifications')) {
            wp_die(esc_html__('Notification settings unavailable.','bubba-hub-manager'),'',array('response'=>403));
        }
        $values = array();
        foreach (array_keys(self::choices()) as $key) $values[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
        $values['newsletter'] = isset($_POST['newsletter']) && $_POST['newsletter'] === '1';
        update_user_meta(get_current_user_id(),self::META,$values);
        // Do not subscribe to external mailing lists or send email from this handler.
        wp_safe_redirect(add_query_arg('bh3_notifications_saved','1',home_url('/family/settings/#bh3-notifications')));
        exit;
    }
}
