<?php
/**
 * Bubba Hub communications: consent-based newsletters and central email activity.
 *
 * Uses WordPress posts/meta to avoid introducing custom database tables.
 * All delivery goes through wp_mail(), allowing FluentSMTP to provide transport.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BH_Communications_3 {
    const SUBSCRIBER = 'bh3_subscriber';
    const CAMPAIGN   = 'bh3_campaign';
    const EMAIL_LOG  = 'bh3_email_log';
    const CRON_HOOK  = 'bh3_process_newsletter_batch';
    const BATCH_SIZE = 20;
    const LOG_RETENTION_DAYS = 90;
    private static $current_type = 'WordPress email';

    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_post_types' ) );
        add_action( 'init', array( __CLASS__, 'schedule_log_cleanup' ), 20 );
        add_action( 'bh3_cleanup_email_logs', array( __CLASS__, 'cleanup_email_logs' ) );
        add_action( 'init', array( __CLASS__, 'handle_public_tokens' ), 1 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
        add_action( 'admin_post_bh3_save_template', array( __CLASS__, 'save_template' ) );
        add_action( 'admin_post_bh3_test_template', array( __CLASS__, 'test_template' ) );
        add_action( 'admin_post_bh3_preview_template', array( __CLASS__, 'preview_template' ) );
        add_action( 'admin_post_bh3_save_campaign', array( __CLASS__, 'save_campaign' ) );
        add_action( 'admin_post_bh3_send_campaign', array( __CLASS__, 'send_campaign' ) );
        add_action( 'admin_post_bh3_toggle_communications', array( __CLASS__, 'toggle_communications' ) );
        add_action( 'admin_post_nopriv_bh3_subscribe', array( __CLASS__, 'subscribe' ) );
        add_action( 'admin_post_bh3_subscribe', array( __CLASS__, 'subscribe' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'process_batch' ) );
        add_action( 'wp_mail_succeeded', array( __CLASS__, 'log_success' ) );
        add_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ) );
        add_shortcode( 'bubbahub_newsletter_signup', array( __CLASS__, 'signup_shortcode' ) );
    }

    public static function register_post_types() {
        $base = array(
            'public'              => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        );
        register_post_type( self::SUBSCRIBER, $base );
        register_post_type( self::CAMPAIGN, $base );
        register_post_type( self::EMAIL_LOG, $base );
    }

    private static function is_staging() {
        return 'staging.bubbahub.co.uk' === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    private static function enabled() {
        return self::is_staging() && '1' === get_option( 'bh3_communications_enabled', '0' );
    }

    /**
     * Shared entry point for claim, booking, account and reminder triggers.
     * Delivery is staging-only until end-to-end QA is approved.
     */
    public static function send_transactional( $to, $subject, $html, $type = 'Transactional' ) {
        if ( ! self::enabled() ) {
            return new WP_Error( 'bh3_email_disabled', 'Bubba Hub email delivery is disabled on this environment.' );
        }
        self::$current_type = sanitize_text_field( $type );
        $sent = wp_mail(
            sanitize_email( $to ),
            sanitize_text_field( $subject ),
            wp_kses_post( $html ),
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
        self::$current_type = 'WordPress email';
        return $sent;
    }

    public static function register_admin_page() {
        add_submenu_page(
            'bubba-hub-3-status',
            __( 'Communications', 'bubba-hub-manager' ),
            __( 'Communications', 'bubba-hub-manager' ),
            'manage_options',
            'bh3-communications',
            array( __CLASS__, 'render_admin' )
        );
    }

    public static function render_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view communications.', 'bubba-hub-manager' ) );
        }
        $active = self::count_subscribers( 'active' );
        $pending = self::count_subscribers( 'pending' );
        $sent = self::count_logs( 'sent' );
        $failed = self::count_logs( 'failed' );
        $enabled = self::enabled();
        echo '<div class="wrap bh3-comms"><h1>Communications centre</h1>';
        echo '<p>Newsletters, automated email delivery and a central activity log. Delivery uses <code>wp_mail()</code>; FluentSMTP handles the SMTP connection when configured. A successful hand-off is not proof of inbox delivery or an open.</p>';
        echo '<style>
        .bh3-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0}
        .bh3-stat{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:16px}
        .bh3-stat strong{display:block;font-size:25px;line-height:1.4}.bh3-panel{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:18px;margin:18px 0}
        .bh3-panel input[type=text],.bh3-panel input[type=email]{width:100%;max-width:620px}.bh3-panel textarea{width:100%;max-width:900px;min-height:180px}
        .bh3-table{width:100%;border-collapse:collapse}.bh3-table th,.bh3-table td{padding:9px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
        .bh3-status{display:inline-block;padding:3px 8px;border-radius:20px;background:#edf2ee}
        @media(max-width:782px){.bh3-table{display:block;overflow-x:auto}}
        </style>';
        echo '<div class="bh3-stats">';
        self::stat_card( 'Active subscribers', $active );
        self::stat_card( 'Awaiting confirmation', $pending );
        self::stat_card( 'Mail accepted', $sent );
        self::stat_card( 'Email failures', $failed );
        echo '</div>';

        echo '<div class="bh3-panel"><h2>Delivery control</h2>';
        echo '<p><strong>Environment:</strong> ' . ( self::is_staging() ? '<span class="bh3-status">Staging</span>' : '<span class="bh3-status">Live — sending locked</span>' ) . '</p>';
        echo '<p><strong>Newsletter sending:</strong> ' . ( $enabled ? 'Enabled on staging' : 'Disabled' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_toggle_communications">';
        wp_nonce_field( 'bh3_toggle_communications' );
        echo '<button class="button button-secondary" type="submit">' . ( $enabled ? 'Disable staging email sending' : 'Enable staging email sending' ) . '</button></form>';
        echo '<p class="description">Sending is disabled by default and cannot be enabled on the live domain in this version.</p></div>';

        echo '<div class="bh3-panel"><h2>Email templates from Bubba Hub</h2><p>These branded templates are adapted from the original <code>bubbaplugin</code> email designs. Preview uses sample data only and does not send an email.</p>';
        echo '<table class="bh3-table"><thead><tr><th>Template</th><th>Original source</th><th>Preview</th></tr></thead><tbody>';
        foreach ( BH_Email_Templates_3::definitions() as $key => $definition ) {
            $preview = wp_nonce_url( add_query_arg( array( 'action' => 'bh3_preview_template', 'template' => $key ), admin_url( 'admin-post.php' ) ), 'bh3_preview_template_' . $key );
            echo '<tr><td>' . esc_html( $definition['name'] ) . '</td><td><code>' . esc_html( $definition['source'] ) . '</code></td><td><a class="button" target="_blank" rel="noopener" href="' . esc_url( $preview ) . '">Preview</a></td></tr>';
        }
        echo '</tbody></table><p>Customise a template below, or leave its body empty to restore the original design. Supported placeholders: <code>{{first_name}}</code>, <code>{{activity_title}}</code>, <code>{{action_url}}</code>, <code>{{date_time}}</code>, <code>{{venue}}</code>, <code>{{content}}</code>.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_save_template">';
        wp_nonce_field( 'bh3_save_template' );
        echo '<p><label>Template <select name="template">';
        foreach ( BH_Email_Templates_3::definitions() as $key => $definition ) {
            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $definition['name'] ) . '</option>';
        }
        echo '</select></label></p><p><label>Custom HTML body<br><textarea name="body"></textarea></label></p><button class="button" type="submit">Save custom body</button></form>';
        foreach ( BH_Email_Templates_3::definitions() as $key => $definition ) {
            $custom = BH_Email_Templates_3::editable_content( $key );
            echo '<details><summary>' . esc_html( $definition['name'] ) . ( $custom ? ' — customised' : ' — original' ) . '</summary><pre style="white-space:pre-wrap">' . esc_html( $custom ?: 'Original design in use' ) . '</pre></details>';
        }
        echo '<h3>Test email</h3><p>Test sends go only to the configured WordPress administrator email address, and only when staging sending is enabled.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_test_template">';
        wp_nonce_field( 'bh3_test_template' );
        echo '<select name="template">';
        foreach ( BH_Email_Templates_3::definitions() as $key => $definition ) {
            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $definition['name'] ) . '</option>';
        }
        echo '</select> <button class="button" type="submit" ' . disabled( ! $enabled, true, false ) . '>Send test to admin</button></form></div>';
        echo '<div class="bh3-panel"><h2>Create newsletter</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bh3_save_campaign">';
        wp_nonce_field( 'bh3_save_campaign' );
        echo '<p><label for="bh3_subject"><strong>Subject</strong></label><br><input id="bh3_subject" name="subject" type="text" required maxlength="180"></p>';
        echo '<p><label for="bh3_body"><strong>Newsletter content (HTML allowed)</strong></label><br><textarea id="bh3_body" name="body" required></textarea></p>';
        echo '<p><button class="button button-primary" type="submit">Save draft</button></p></form>';
        echo '<p class="description">Campaigns are saved as drafts. Sending is a separate action and only available after staging delivery is enabled. Only confirmed, opted-in subscribers are included.</p></div>';

        echo '<div class="bh3-panel"><h2>Newsletter campaigns</h2>';
        $campaigns = get_posts( array( 'post_type' => self::CAMPAIGN, 'post_status' => 'private', 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC' ) );
        if ( ! $campaigns ) {
            echo '<p>No campaigns created yet.</p>';
        } else {
            echo '<table class="bh3-table"><thead><tr><th>Campaign</th><th>Status</th><th>Sent</th><th>Failed</th><th>Action</th></tr></thead><tbody>';
            foreach ( $campaigns as $campaign ) {
                $status = get_post_meta( $campaign->ID, '_bh3_status', true ) ?: 'draft';
                echo '<tr><td><strong>' . esc_html( $campaign->post_title ) . '</strong><br><small>' . esc_html( get_the_date( 'd M Y H:i', $campaign ) ) . '</small></td>';
                echo '<td>' . esc_html( ucfirst( $status ) ) . '</td><td>' . absint( get_post_meta( $campaign->ID, '_bh3_sent', true ) ) . '</td><td>' . absint( get_post_meta( $campaign->ID, '_bh3_failed', true ) ) . '</td><td>';
                if ( 'draft' === $status ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_send_campaign"><input type="hidden" name="campaign_id" value="' . absint( $campaign->ID ) . '">';
                    wp_nonce_field( 'bh3_send_campaign_' . $campaign->ID );
                    echo '<button class="button button-secondary" type="submit" ' . disabled( ! $enabled, true, false ) . '>Queue send</button></form>';
                } else {
                    echo '—';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="bh3-panel"><h2>Recent email activity</h2>';
        $logs = get_posts( array( 'post_type' => self::EMAIL_LOG, 'post_status' => 'private', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
        if ( ! $logs ) {
            echo '<p>No email activity has been recorded yet. New WordPress emails are logged here automatically.</p>';
        } else {
            echo '<table class="bh3-table"><thead><tr><th>Date</th><th>Recipient</th><th>Subject</th><th>Type</th><th>Status / detail</th></tr></thead><tbody>';
            foreach ( $logs as $log ) {
                echo '<tr><td>' . esc_html( get_the_date( 'd M Y H:i:s', $log ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $log->ID, '_bh3_to', true ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $log->ID, '_bh3_subject', true ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $log->ID, '_bh3_type', true ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $log->ID, '_bh3_status', true ) ) . ' — ' . esc_html( (string) get_post_meta( $log->ID, '_bh3_detail', true ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p class="description">For privacy, message bodies and attachments are not copied into this central log. FluentSMTP may retain its own detailed transport log.</p></div>';
        echo '<div class="bh3-panel"><h2>Recent subscribers</h2>';
        $subscribers = get_posts( array( 'post_type' => self::SUBSCRIBER, 'post_status' => 'private', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
        if ( ! $subscribers ) {
            echo '<p>No newsletter subscribers yet.</p>';
        } else {
            echo '<table class="bh3-table"><thead><tr><th>Email</th><th>Status</th><th>Consent recorded (UTC)</th><th>Confirmed (UTC)</th></tr></thead><tbody>';
            foreach ( $subscribers as $subscriber ) {
                echo '<tr><td>' . esc_html( (string) get_post_meta( $subscriber->ID, '_bh3_email', true ) ) . '</td>';
                echo '<td>' . esc_html( ucfirst( (string) get_post_meta( $subscriber->ID, '_bh3_status', true ) ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $subscriber->ID, '_bh3_consent_at', true ) ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $subscriber->ID, '_bh3_confirmed_at', true ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p class="description">Only confirmed subscribers receive campaigns. Unsubscribed and pending records are excluded.</p></div>';
        echo '<div class="bh3-panel"><h2>Subscriber sign-up</h2><p>Use shortcode <code>[bubbahub_newsletter_signup]</code> to display the double opt-in sign-up form. Subscribers are not added to campaigns until they confirm by email.</p>';
        echo '<p>Subscriber totals above show status counts. Subscriber export, suppression management and preference categories are planned follow-up work.</p></div>';
        echo '</div>';
    }

    private static function stat_card( $label, $value ) {
        echo '<div class="bh3-stat"><strong>' . esc_html( number_format_i18n( $value ) ) . '</strong>' . esc_html( $label ) . '</div>';
    }

    private static function count_subscribers( $status ) {
        $query = new WP_Query( array( 'post_type' => self::SUBSCRIBER, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false, 'meta_key' => '_bh3_status', 'meta_value' => $status ) );
        return (int) $query->found_posts;
    }

    private static function count_logs( $status ) {
        $query = new WP_Query( array( 'post_type' => self::EMAIL_LOG, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false, 'meta_key' => '_bh3_status', 'meta_value' => $status ) );
        return (int) $query->found_posts;
    }

    public static function save_template() {
        self::require_admin();
        check_admin_referer( 'bh3_save_template' );
        $key = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
        $defs = BH_Email_Templates_3::definitions();
        if ( ! isset( $defs[ $key ] ) ) { wp_die( 'Invalid template' ); }
        $body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
        update_option( 'bh3_email_template_' . $key, $body, false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-communications&template=saved' ) );
        exit;
    }

    public static function test_template() {
        self::require_admin();
        check_admin_referer( 'bh3_test_template' );
        if ( ! self::enabled() ) { wp_die( 'Test sending is locked on this environment.' ); }
        $key = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
        $defs = BH_Email_Templates_3::definitions();
        if ( ! isset( $defs[ $key ] ) ) { wp_die( 'Invalid template' ); }
        $to = get_option( 'admin_email' );
        $html = BH_Email_Templates_3::render( $key );
        $sent = self::send_transactional( $to, '[TEST] ' . $defs[ $key ]['subject'], $html, 'Template test' );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-communications&test=' . ( true === $sent ? 'accepted' : 'failed' ) ) );
        exit;
    }

    /**
     * Integration adapter for a verified claim approval event.
     * Caller must enforce claim ownership and duplicate notification policy.
     */
    public static function notify_claim_approved( $recipient, $activity_title, $first_name = '' ) {
        if ( ! is_email( $recipient ) || '' === trim( (string) $activity_title ) ) {
            return new WP_Error( 'bh3_invalid_claim_notification', 'Recipient and activity title required.' );
        }
        return self::send_transactional(
            $recipient,
            'Your Bubba Hub listing has been approved',
            BH_Email_Templates_3::render( 'claim_approved', array(
                'first_name' => $first_name,
                'activity_title' => $activity_title,
                'action_url' => home_url( '/leader/classes.html' ),
            ) ),
            'Claim approved'
        );
    }

    /**
     * Invitation tokens and duplicate protection belong to the existing claim workflow.
     * This method only renders and delivers a previously authorised invitation.
     */
    public static function notify_claim_invitation( $recipient, $activity_title, $claim_url ) {
        if ( ! is_email( $recipient ) || '' === trim( (string) $activity_title ) || ! wp_http_validate_url( $claim_url ) ) {
            return new WP_Error( 'bh3_invalid_claim_invitation', 'Invalid invitation details.' );
        }
        return self::send_transactional(
            $recipient,
            'Your activity is on Bubba Hub – claim your free listing',
            BH_Email_Templates_3::render( 'claim_invitation', array(
                'activity_title' => $activity_title,
                'action_url' => $claim_url,
            ) ),
            'Claim invitation'
        );
    }

    public static function preview_template() {
        self::require_admin();
        $key = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
        check_admin_referer( 'bh3_preview_template_' . $key );
        $definitions = BH_Email_Templates_3::definitions();
        if ( ! isset( $definitions[ $key ] ) ) {
            wp_die( esc_html__( 'Unknown email template.', 'bubba-hub-manager' ), '', array( 'response' => 404 ) );
        }
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo BH_Email_Templates_3::render( $key, array(
            'first_name' => 'Sample parent',
            'activity_title' => 'Example family activity',
            'action_url' => home_url( '/leader/classes.html' ),
            'content' => '<p>Sample: explore the latest family classes, local events and activities.</p>',
            'date_time' => 'Tuesday at 10:00',
            'venue' => 'Sample community centre',
            'unsubscribe_url' => home_url( '/' ),
        ) );
        exit;
    }

    public static function toggle_communications() {
        self::require_admin();
        check_admin_referer( 'bh3_toggle_communications' );
        if ( ! self::is_staging() ) {
            wp_die( esc_html__( 'Email sending can only be enabled on staging.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        update_option( 'bh3_communications_enabled', self::enabled() ? '0' : '1', false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-communications' ) );
        exit;
    }

    public static function save_campaign() {
        self::require_admin();
        check_admin_referer( 'bh3_save_campaign' );
        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
        if ( '' === $subject || '' === trim( wp_strip_all_tags( $body ) ) ) {
            wp_die( esc_html__( 'A subject and newsletter body are required.', 'bubba-hub-manager' ), '', array( 'response' => 400 ) );
        }
        $id = wp_insert_post( array( 'post_type' => self::CAMPAIGN, 'post_status' => 'private', 'post_title' => $subject, 'post_content' => $body ), true );
        if ( is_wp_error( $id ) ) {
            wp_die( esc_html( $id->get_error_message() ) );
        }
        update_post_meta( $id, '_bh3_status', 'draft' );
        update_post_meta( $id, '_bh3_sent', 0 );
        update_post_meta( $id, '_bh3_failed', 0 );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-communications&campaign=saved' ) );
        exit;
    }

    public static function send_campaign() {
        self::require_admin();
        $id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
        check_admin_referer( 'bh3_send_campaign_' . $id );
        if ( ! self::enabled() ) {
            wp_die( esc_html__( 'Newsletter delivery is disabled. Enable it on staging first.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
        $campaign = get_post( $id );
        if ( ! $campaign || self::CAMPAIGN !== $campaign->post_type || 'draft' !== get_post_meta( $id, '_bh3_status', true ) ) {
            wp_die( esc_html__( 'This campaign cannot be queued.', 'bubba-hub-manager' ), '', array( 'response' => 400 ) );
        }
        $ids = get_posts( array( 'post_type' => self::SUBSCRIBER, 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_bh3_status', 'meta_value' => 'active' ) );
        update_post_meta( $id, '_bh3_recipient_ids', array_map( 'absint', $ids ) );
        update_post_meta( $id, '_bh3_cursor', 0 );
        update_post_meta( $id, '_bh3_status', 'sending' );
        wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $id ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-communications&campaign=queued' ) );
        exit;
    }

    public static function process_batch( $campaign_id ) {
        if ( ! self::enabled() ) {
            update_post_meta( $campaign_id, '_bh3_status', 'paused' );
            return;
        }
        $campaign = get_post( $campaign_id );
        if ( ! $campaign || self::CAMPAIGN !== $campaign->post_type || 'sending' !== get_post_meta( $campaign_id, '_bh3_status', true ) ) {
            return;
        }
        $ids = (array) get_post_meta( $campaign_id, '_bh3_recipient_ids', true );
        $cursor = absint( get_post_meta( $campaign_id, '_bh3_cursor', true ) );
        $batch = array_slice( $ids, $cursor, self::BATCH_SIZE );
        foreach ( $batch as $subscriber_id ) {
            if ( 'active' !== get_post_meta( $subscriber_id, '_bh3_status', true ) ) {
                continue;
            }
            $email = sanitize_email( get_post_meta( $subscriber_id, '_bh3_email', true ) );
                $token = wp_hash( 'bh3-unsubscribe:' . absint( $subscriber_id ) );
            $unsubscribe = add_query_arg( array( 'bh3_unsubscribe' => $subscriber_id, 'token' => rawurlencode( $token ) ), home_url( '/' ) );
            $html = BH_Email_Templates_3::render( 'newsletter', array( 'content' => $campaign->post_content, 'unsubscribe_url' => $unsubscribe ) );
            $sent = self::send_transactional( $email, $campaign->post_title, $html, 'Newsletter' );
            if ( $sent ) {
                update_post_meta( $campaign_id, '_bh3_sent', absint( get_post_meta( $campaign_id, '_bh3_sent', true ) ) + 1 );
            } else {
                update_post_meta( $campaign_id, '_bh3_failed', absint( get_post_meta( $campaign_id, '_bh3_failed', true ) ) + 1 );
            }
        }
        $cursor += count( $batch );
        update_post_meta( $campaign_id, '_bh3_cursor', $cursor );
        if ( $cursor < count( $ids ) ) {
            wp_schedule_single_event( time() + 15, self::CRON_HOOK, array( $campaign_id ) );
        } else {
            update_post_meta( $campaign_id, '_bh3_status', 'sent' );
        }
    }

    public static function signup_shortcode() {
        $message = '';
        if ( isset( $_GET['bh3_subscribed'] ) ) {
            $message = '<p role="status">Please check your inbox to confirm your subscription.</p>';
        }
        $html = $message . '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $html .= '<input type="hidden" name="action" value="bh3_subscribe">';
        $html .= wp_nonce_field( 'bh3_subscribe', '_wpnonce', true, false );
        $html .= '<p><label>Email address <input type="email" name="email" required autocomplete="email"></label></p>';
        $html .= '<p><label><input type="checkbox" name="consent" value="1" required> I agree to receive Bubba Hub newsletters and understand I can unsubscribe at any time.</label></p>';
        $html .= '<button type="submit">Sign up</button></form>';
        return $html;
    }

    public static function subscribe() {
        if ( ! self::enabled() ) {
            wp_die( esc_html__( 'Newsletter sign-up is temporarily unavailable while the communications system is being tested.', 'bubba-hub-manager' ), '', array( 'response' => 503 ) );
        }
        check_admin_referer( 'bh3_subscribe' );
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $consent = isset( $_POST['consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['consent'] ) );
        if ( ! is_email( $email ) || ! $consent ) {
            wp_die( esc_html__( 'Please enter a valid email address and confirm consent.', 'bubba-hub-manager' ), '', array( 'response' => 400 ) );
        }
        $existing = get_posts( array( 'post_type' => self::SUBSCRIBER, 'post_status' => 'private', 'numberposts' => 1, 'meta_key' => '_bh3_email', 'meta_value' => $email ) );
        if ( $existing && 'active' === get_post_meta( $existing[0]->ID, '_bh3_status', true ) ) {
            wp_safe_redirect( add_query_arg( 'bh3_subscribed', '1', wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }
        $subscriber_id = $existing ? $existing[0]->ID : wp_insert_post( array( 'post_type' => self::SUBSCRIBER, 'post_status' => 'private', 'post_title' => $email ), true );
        if ( is_wp_error( $subscriber_id ) ) {
            wp_die( esc_html( $subscriber_id->get_error_message() ) );
        }
        $token = wp_generate_password( 40, false, false );
        update_post_meta( $subscriber_id, '_bh3_email', $email );
        update_post_meta( $subscriber_id, '_bh3_status', 'pending' );
        update_post_meta( $subscriber_id, '_bh3_consent_at', current_time( 'mysql', true ) );
        update_post_meta( $subscriber_id, '_bh3_consent_source', 'website newsletter signup' );
        update_post_meta( $subscriber_id, '_bh3_confirm_hash', wp_hash_password( $token ) );
        $url = add_query_arg( array( 'bh3_confirm_sub' => $subscriber_id, 'token' => rawurlencode( $token ) ), home_url( '/' ) );
        $body = '<p>Thanks for asking to receive Bubba Hub updates.</p><p><a href="' . esc_url( $url ) . '">Confirm my subscription</a></p><p>If you did not request this, you can ignore this email.</p>';
        self::send_transactional( $email, 'Please confirm your Bubba Hub newsletter subscription', BH_Email_Templates_3::render( 'newsletter', array( 'content' => $body ) ), 'Newsletter confirmation' );
        wp_safe_redirect( add_query_arg( 'bh3_subscribed', '1', wp_get_referer() ?: home_url( '/' ) ) );
        exit;
    }

    public static function handle_public_tokens() {
        if ( isset( $_GET['bh3_confirm_sub'], $_GET['token'] ) ) {
            $id = absint( $_GET['bh3_confirm_sub'] );
            $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
            $hash = (string) get_post_meta( $id, '_bh3_confirm_hash', true );
            if ( $hash && wp_check_password( $token, $hash ) ) {
                update_post_meta( $id, '_bh3_status', 'active' );
                delete_post_meta( $id, '_bh3_confirm_hash' );
                update_post_meta( $id, '_bh3_confirmed_at', current_time( 'mysql', true ) );
                self::send_transactional( (string) get_post_meta( $id, '_bh3_email', true ), 'Welcome to Bubba Hub 💚', BH_Email_Templates_3::render( 'welcome', array( 'unsubscribe_url' => add_query_arg( array( 'bh3_unsubscribe' => $id, 'token' => rawurlencode( wp_hash( 'bh3-unsubscribe:' . $id ) ) ), home_url( '/' ) ) ) ), 'Newsletter welcome' );
                wp_die( esc_html__( 'Your Bubba Hub newsletter subscription is confirmed. Thank you!', 'bubba-hub-manager' ), esc_html__( 'Subscription confirmed', 'bubba-hub-manager' ), array( 'response' => 200 ) );
            }
            wp_die( esc_html__( 'This confirmation link is invalid or has expired.', 'bubba-hub-manager' ), esc_html__( 'Unable to confirm', 'bubba-hub-manager' ), array( 'response' => 400 ) );
        }
        if ( isset( $_GET['bh3_unsubscribe'], $_GET['token'] ) ) {
            $id = absint( $_GET['bh3_unsubscribe'] );
            $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
            $expected = wp_hash( 'bh3-unsubscribe:' . $id );
            if ( $id && hash_equals( $expected, $token ) && self::SUBSCRIBER === get_post_type( $id ) ) {
                update_post_meta( $id, '_bh3_status', 'unsubscribed' );
                update_post_meta( $id, '_bh3_unsubscribed_at', current_time( 'mysql', true ) );
                wp_die( esc_html__( 'You have been unsubscribed from Bubba Hub newsletters.', 'bubba-hub-manager' ), esc_html__( 'Unsubscribed', 'bubba-hub-manager' ), array( 'response' => 200 ) );
            }
            wp_die( esc_html__( 'This unsubscribe link is invalid.', 'bubba-hub-manager' ), esc_html__( 'Unable to unsubscribe', 'bubba-hub-manager' ), array( 'response' => 400 ) );
        }
    }

    public static function log_success( $mail_data ) {
        self::write_log( $mail_data, 'sent', '' );
    }

    public static function log_failure( $error ) {
        $data = is_wp_error( $error ) ? $error->get_error_data() : array();
        $detail = is_wp_error( $error ) ? $error->get_error_message() : 'Email delivery failed';
        self::write_log( is_array( $data ) ? $data : array(), 'failed', $detail );
    }

    private static function write_log( $mail_data, $status, $detail ) {
        $to = isset( $mail_data['to'] ) ? $mail_data['to'] : '';
        if ( is_array( $to ) ) {
            $to = implode( ', ', array_map( 'sanitize_email', $to ) );
        }
        $subject = isset( $mail_data['subject'] ) ? sanitize_text_field( $mail_data['subject'] ) : '(no subject)';
        $id = wp_insert_post( array( 'post_type' => self::EMAIL_LOG, 'post_status' => 'private', 'post_title' => $subject ), true );
        if ( is_wp_error( $id ) || ! $id ) {
            return;
        }
        update_post_meta( $id, '_bh3_to', sanitize_text_field( (string) $to ) );
        update_post_meta( $id, '_bh3_subject', $subject );
        update_post_meta( $id, '_bh3_status', $status );
        update_post_meta( $id, '_bh3_detail', sanitize_text_field( $detail ) );
        update_post_meta( $id, '_bh3_type', self::$current_type );
    }

    public static function schedule_log_cleanup() {
        if ( ! wp_next_scheduled( 'bh3_cleanup_email_logs' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bh3_cleanup_email_logs' );
        }
    }

    public static function cleanup_email_logs() {
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::LOG_RETENTION_DAYS * DAY_IN_SECONDS ) );
        $expired = get_posts( array(
            'post_type'      => self::EMAIL_LOG,
            'post_status'    => 'private',
            'numberposts'    => 100,
            'fields'         => 'ids',
            'date_query'     => array( array( 'column' => 'post_date_gmt', 'before' => $cutoff ) ),
        ) );
        foreach ( $expired as $log_id ) {
            wp_delete_post( $log_id, true );
        }
    }

    private static function require_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Administrator permission required.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) );
        }
    }
}
