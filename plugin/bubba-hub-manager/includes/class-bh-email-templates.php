<?php
/**
 * Email design adapted from bubbaplugin:
 * api/newsletter-welcome.php, api/newsletter.php and api/claim-invitations-cron.php.
 * No legacy mailer or database logic is imported.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Email_Templates_3 {
    public static function definitions() {
        return array(
            'newsletter' => array( 'name' => 'Bubba Hub Round Up', 'source' => 'api/newsletter.php', 'subject' => 'Your Bubba Hub Round Up' ),
            'welcome' => array( 'name' => 'Newsletter welcome', 'source' => 'api/newsletter-welcome.php', 'subject' => 'Welcome to Bubba Hub 💚' ),
            'claim_invitation' => array( 'name' => 'Claim your listing', 'source' => 'api/claim-invitations-cron.php', 'subject' => 'Your activity is on Bubba Hub – claim your free listing' ),
            'claim_approved' => array( 'name' => 'Claim approved', 'source' => 'new WordPress adaptation', 'subject' => 'Your Bubba Hub listing has been approved' ),
            'booking_confirmation' => array( 'name' => 'Booking confirmation', 'source' => 'new WordPress adaptation', 'subject' => 'Your Bubba Hub booking confirmation' ),
            'booking_reminder' => array( 'name' => 'Booking reminder', 'source' => 'new WordPress adaptation', 'subject' => 'A reminder about your Bubba Hub booking' ),
        );
    }

    public static function render( $key, $vars = array() ) {
        $defs = self::definitions();
        if ( ! isset( $defs[ $key ] ) ) { return ''; }
        $defaults = array(
            'first_name' => 'there', 'activity_title' => 'Your family activity',
            'action_url' => home_url( '/' ), 'unsubscribe_url' => '',
            'content' => '<p>Discover local family activities, classes and events across Devon &amp; Cornwall.</p>',
            'date_time' => 'Your scheduled session', 'venue' => 'Your chosen venue',
        );
        $v = wp_parse_args( $vars, $defaults );
        $name = esc_html( (string) $v['first_name'] );
        $activity = esc_html( (string) $v['activity_title'] );
        $action = esc_url( (string) $v['action_url'] );
        $button = static function( $label, $url ) {
            return '<p style="text-align:center;margin:28px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#416651;color:#fff;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:10px">' . esc_html( $label ) . '</a></p>';
        };
        switch ( $key ) {
            case 'welcome':
                $body = '<h1>Welcome to Bubba Hub! 💚</h1><p>Thanks for joining our newsletter.</p><p>Bubba Hub helps families find baby groups, toddler classes, events and local services across Devon &amp; Cornwall.</p><h2>What you’ll get from us</h2><p>👶 Family activities and local finds</p><p>📅 What’s coming up</p><p>💚 Bubba Hub updates</p><p>📍 Helpful local family information</p>' . $button( 'Explore Bubba Hub', home_url( '/' ) ) . '<p>Thanks for being part of the Bubba Hub family.</p><p>Gina<br><strong>Founder, Bubba Hub</strong></p>';
                break;
            case 'claim_invitation':
                $body = '<h1>Your activity is on Bubba Hub!</h1><p>We have added <strong>' . $activity . '</strong> to our family activities directory.</p><p>Claiming your listing and creating a Class Leader account are free.</p>' . $button( 'Claim my free listing', $action ) . '<p>Once approved, you can update your activity details, photos and timetable.</p><p>The Bubba Hub South West Team</p>';
                break;
            case 'claim_approved':
                $body = '<h1>Your listing claim has been approved</h1><p>Hello ' . $name . ',</p><p>You can now manage <strong>' . $activity . '</strong> in your Class Leader dashboard.</p>' . $button( 'Manage my classes', $action );
                break;
            case 'booking_confirmation':
                $body = '<h1>Booking received</h1><p>Hello ' . $name . ',</p><p>Your booking for <strong>' . $activity . '</strong> has been recorded.</p><p><strong>When:</strong> ' . esc_html( (string) $v['date_time'] ) . '<br><strong>Where:</strong> ' . esc_html( (string) $v['venue'] ) . '</p><p>Please check your booking details and any instructions from the organiser.</p>' . $button( 'View booking details', $action );
                break;
            case 'booking_reminder':
                $body = '<h1>A little reminder</h1><p>Hello ' . $name . ',</p><p>You have a reservation for <strong>' . $activity . '</strong>.</p><p><strong>When:</strong> ' . esc_html( (string) $v['date_time'] ) . '</p><p>Please confirm your place with the organiser if you have not already done so.</p>' . $button( 'View reservation', $action );
                break;
            case 'newsletter':
            default:
                $body = '<h1>Your Bubba Hub Round Up</h1><p>A little nudge towards what is happening for families.</p><h2>New on Bubba Hub</h2>' . wp_kses_post( (string) $v['content'] ) . $button( 'Explore Bubba Hub', home_url( '/' ) );
                break;
        }
        $logo = esc_url( home_url( '/wp-content/uploads/logo/logoheader.png' ) );
        $footer = '<p style="font-size:12px;line-height:1.6;color:#617261;margin:28px 0 0;padding-top:18px;border-top:1px solid #d8e6db">Bubba Hub South West · Devon &amp; Cornwall<br><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#416651">bubbahub.co.uk</a></p>';
        if ( in_array( $key, array( 'newsletter', 'welcome' ), true ) ) {
            $footer .= '<p style="font-size:12px;color:#617261">You are receiving this because you subscribed to Bubba Hub newsletters.';
            if ( ! empty( $v['unsubscribe_url'] ) ) {
                $footer .= ' <a href="' . esc_url( (string) $v['unsubscribe_url'] ) . '">Unsubscribe</a>';
            }
            $footer .= '</p>';
        }
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#d8e6db;font-family:Arial,Helvetica,sans-serif;color:#244c38"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#d8e6db;padding:32px 12px"><tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#fff;border-radius:20px"><tr><td style="padding:28px 32px 18px;text-align:center"><img src="' . $logo . '" alt="Bubba Hub" width="220" style="max-width:100%;height:auto;border:0"></td></tr><tr><td style="padding:8px 36px 36px;font-size:16px;line-height:1.7">' . $body . $footer . '</td></tr></table></td></tr></table></body></html>';
    }
}
