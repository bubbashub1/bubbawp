<?php
/** Staging-only marketplace preferences. This module never charges or transfers money. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Marketplace_Settings_3 {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_create_test_booking', array( __CLASS__, 'create_test_booking' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_test_webhook_route' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_parent_booking_binding_route' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_bound_parent_checkout_route' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_native_booking_preflight_route' ) );
        add_action( 'admin_post_bh3_save_commission', array( __CLASS__, 'save_commission' ) );
        add_action( 'admin_post_bh3_leader_enrol_booking', array( __CLASS__, 'handle_leader_enrol_booking' ) );
        add_action( 'admin_post_bh3_verify_stripe', array( __CLASS__, 'verify_stripe' ) );
        add_action( 'admin_post_bh3_stripe_onboard', array( __CLASS__, 'stripe_onboard' ) );
        add_action( 'admin_post_bh3_save_payout_method', array( __CLASS__, 'save_method' ) );
        add_shortcode( 'bh_leader_payout_settings', array( __CLASS__, 'leader_form' ) );
        add_shortcode( 'bh_parent_booking', array( __CLASS__, 'parent_booking_widget' ) );
        add_filter( 'fluentform/rendering_field_data_input_checkbox', array( __CLASS__, 'filter_form7_checkbox_field' ), 10, 2 );
        add_filter( 'fluentform/rendering_field_data_input_hidden', array( __CLASS__, 'filter_form7_hidden_field' ), 10, 2 );
        add_filter( 'fluentform/submission_confirmation', array( __CLASS__, 'filter_form7_checkout_confirmation' ), 20, 5 );
        add_shortcode( 'bh_booking_readiness', array( __CLASS__, 'booking_readiness_preview' ) );
        add_shortcode( 'bh_leader_email_verification', array( __CLASS__, 'leader_email_verification_widget' ) );
        add_shortcode( 'bh_leader_account_setup', array( __CLASS__, 'leader_account_setup_widget' ) );
        add_action( 'admin_post_bh3_admin_link_leader', array( __CLASS__, 'admin_link_leader' ) );
        add_action( 'admin_post_bh3_request_leader_email_verification', array( __CLASS__, 'request_leader_email_verification' ) );
        add_action( 'admin_post_bh3_confirm_leader_email_verification', array( __CLASS__, 'confirm_leader_email_verification' ) );
    }
    /**
     * Front-end setup status for leaders, with an administrator-only staging
     * linking form. A leader must never self-claim an arbitrary organiser ID.
     */
    public static function leader_account_setup_widget() {
        if ( ! is_user_logged_in() ) {
            return '<section class="bh3-leader-setup"><h2>Set up your leader account</h2><p>Please log in or register to get started.</p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log in</a></section>';
        }
        $user = wp_get_current_user();
        $organiser_id = absint( get_user_meta( $user->ID, 'bh3_organiser_id', true ) );
        $html = '<section class="bh3-leader-setup"><h2>Leader account setup</h2>';
        $html .= '<p><strong>Account:</strong> ' . esc_html( $user->display_name ) . '</p>';
        $html .= '<p><strong>Organisation:</strong> ' . ( $organiser_id ? 'Linked' : 'Awaiting team approval' ) . '</p>';
        if ( ! $organiser_id ) {
            $html .= '<p>To protect organisers from unauthorised claims, the Bubba Hub team must approve your organisation link.</p>';
        } else {
            $html .= self::leader_email_verification_widget();
        }
        if ( current_user_can( 'manage_options' ) ) {
            global $wpdb;
            $organisers_table = $wpdb->prefix . 'organisers';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $organisers_table ) ) );
            $html .= '<h3>Team: link a test leader</h3>';
            if ( $exists !== $organisers_table ) {
                $html .= '<p>Organiser table unavailable.</p>';
            } else {
                $organisers = $wpdb->get_results( "SELECT id, name FROM `{$organisers_table}` ORDER BY name ASC LIMIT 200", ARRAY_A );
                $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                $html .= '<input type="hidden" name="action" value="bh3_admin_link_leader">';
                $html .= wp_nonce_field( 'bh3_admin_link_leader', '_wpnonce', true, false );
                $html .= '<p><label>WordPress user ID <input type="number" name="leader_user_id" min="1" required></label></p>';
                $html .= '<p><label>Approved organisation <select name="organiser_id" required><option value="">Select organisation</option>';
                foreach ( $organisers as $org ) {
                    $html .= '<option value="' . esc_attr( $org['id'] ) . '">' . esc_html( $org['name'] ) . ' (#' . absint( $org['id'] ) . ')</option>';
                }
                $html .= '</select></label></p><button type="submit">Approve and link leader</button></form>';
                $html .= '<p>Only link accounts after confirming the leader is authorised to represent the selected organisation.</p>';
            }
        }
        return $html . '</section>';
    }
    public static function admin_link_leader() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Administrator approval required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_admin_link_leader' );
        $user_id = isset( $_POST['leader_user_id'] ) ? absint( $_POST['leader_user_id'] ) : 0;
        $organiser_id = isset( $_POST['organiser_id'] ) ? absint( $_POST['organiser_id'] ) : 0;
        $user = $user_id ? get_userdata( $user_id ) : false;
        if ( ! $user || ! $organiser_id || ! is_email( $user->user_email )
            || user_can( $user_id, 'manage_options' ) ) {
            wp_die( 'Choose a valid non-administrator leader and organisation.', '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d LIMIT 1", $organiser_id ) );
        if ( ! $exists ) { wp_die( 'Organisation does not exist.', '', array( 'response' => 400 ) ); }
        $linked = get_users( array( 'meta_key' => 'bh3_organiser_id', 'meta_value' => (string) $organiser_id, 'fields' => 'ID', 'number' => 2 ) );
        foreach ( $linked as $existing_id ) {
            if ( (int) $existing_id !== $user_id ) {
                wp_die( 'Organisation is already linked to another account. Review ownership before making changes.', '', array( 'response' => 409 ) );
            }
        }
        $old = absint( get_user_meta( $user_id, 'bh3_organiser_id', true ) );
        if ( $old && $old !== $organiser_id ) {
            wp_die( 'Account already belongs to another organisation. Review ownership before changing links.', '', array( 'response' => 409 ) );
        }
        update_user_meta( $user_id, 'bh3_organiser_id', $organiser_id );
        // A newly approved organisation link never inherits an old verification.
        delete_user_meta( $user_id, 'bh3_verified_booking_email' );
        delete_user_meta( $user_id, 'bh3_verified_booking_email_at' );
        delete_user_meta( $user_id, 'bh3_booking_email_verification_pending' );
        wp_safe_redirect( add_query_arg( 'bh3_leader_linked', '1', home_url( '/leader/' ) ) );
        exit;
    }
    /** Staging-only email ownership proof for the current linked leader account. */
    public static function leader_email_verification_widget() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to verify your leader email address.</p>';
        }
        $user = wp_get_current_user();
        $organiser_id = absint( get_user_meta( $user->ID, 'bh3_organiser_id', true ) );
        if ( ! $organiser_id || ! is_email( $user->user_email ) ) {
            return '<p>Your leader account must be linked to an organisation before email verification.</p>';
        }
        $verified = (string) get_user_meta( $user->ID, 'bh3_verified_booking_email', true );
        $verified_at = (string) get_user_meta( $user->ID, 'bh3_verified_booking_email_at', true );
        $is_verified = $verified_at && strtotime( $verified_at )
            && hash_equals( strtolower( trim( $user->user_email ) ), strtolower( trim( $verified ) ) );
        $html = '<section class="bh3-leader-email-verification"><h3>Verify your booking email</h3>';
        if ( $is_verified ) {
            return $html . '<p role="status">Your current account email is verified for booking notifications.</p></section>';
        }
        $html .= '<p>We will send a verification link to your WordPress account email. Booking details will not be sent until verification is complete.</p>';
        $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $html .= '<input type="hidden" name="action" value="bh3_request_leader_email_verification">';
        $html .= wp_nonce_field( 'bh3_request_leader_email_verification', '_wpnonce', true, false );
        $html .= '<button type="submit">Send verification email</button></form></section>';
        return $html;
    }
    public static function request_leader_email_verification() {
        if ( ! is_user_logged_in() ) { wp_die( 'Login required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_request_leader_email_verification' );
        $user = wp_get_current_user();
        $organiser_id = absint( get_user_meta( $user->ID, 'bh3_organiser_id', true ) );
        if ( ! $organiser_id || ! is_email( $user->user_email ) ) {
            wp_die( 'Linked leader account and valid email required.', '', array( 'response' => 403 ) );
        }
        $limit_key = 'bh3_booking_email_verify_limit_' . $user->ID;
        if ( get_transient( $limit_key ) ) {
            wp_die( 'Please wait before requesting another verification email.', '', array( 'response' => 429 ) );
        }
        $token = bin2hex( random_bytes( 32 ) );
        $expires = time() + HOUR_IN_SECONDS;
        $payload = array(
            'hash' => hash( 'sha256', $token ),
            'email' => strtolower( trim( $user->user_email ) ),
            'expires' => $expires,
        );
        update_user_meta( $user->ID, 'bh3_booking_email_verification_pending', $payload );
        set_transient( $limit_key, 1, 2 * MINUTE_IN_SECONDS );
        $url = add_query_arg( array(
            'action' => 'bh3_confirm_leader_email_verification',
            'uid' => $user->ID,
            'token' => $token,
        ), admin_url( 'admin-post.php' ) );
        $sent = wp_mail( $user->user_email, 'Verify your Bubba Hub booking email',
            "Please confirm your booking email by opening this link within one hour:\n\n" . $url .
            "\n\nIf you did not request this, you can ignore this email." );
        if ( ! $sent ) {
            delete_user_meta( $user->ID, 'bh3_booking_email_verification_pending' );
            wp_die( 'Email could not be sent. Please check your mail configuration.', '', array( 'response' => 503 ) );
        }
        wp_safe_redirect( add_query_arg( 'bh3_email_verification', 'sent', home_url( '/leader/' ) ) );
        exit;
    }
    public static function confirm_leader_email_verification() {
        $user_id = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
        $token = isset( $_GET['token'] ) && is_string( $_GET['token'] )
            ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( ! is_user_logged_in() || get_current_user_id() !== $user_id
            || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            wp_die( 'Log in to the leader account that requested verification.', '', array( 'response' => 403 ) );
        }
        $pending = get_user_meta( $user_id, 'bh3_booking_email_verification_pending', true );
        $user = get_userdata( $user_id );
        if ( ! is_array( $pending ) || ! $user || empty( $pending['hash'] )
            || empty( $pending['expires'] ) || time() > (int) $pending['expires']
            || ! hash_equals( (string) $pending['hash'], hash( 'sha256', $token ) )
            || ! hash_equals( (string) $pending['email'], strtolower( trim( $user->user_email ) ) )
            || ! absint( get_user_meta( $user_id, 'bh3_organiser_id', true ) ) ) {
            wp_die( 'Verification link is invalid or expired. Request a new one.', '', array( 'response' => 403 ) );
        }
        update_user_meta( $user_id, 'bh3_verified_booking_email', $user->user_email );
        update_user_meta( $user_id, 'bh3_verified_booking_email_at', gmdate( 'Y-m-d H:i:s' ) );
        delete_user_meta( $user_id, 'bh3_booking_email_verification_pending' );
        wp_safe_redirect( add_query_arg( 'bh3_email_verification', 'verified', home_url( '/leader/' ) ) );
        exit;
    }
    public static function rate() {
        $value = get_option( 'bh3_commission_percent', '2.50' );
        return is_numeric( $value ) ? min( 100, max( 0, (float) $value ) ) : 2.5;
    }
    public static function split_pence( $gross_pence ) {
        $gross = max( 0, absint( $gross_pence ) );
        $commission = (int) round( $gross * self::rate() / 100, 0, PHP_ROUND_HALF_UP );
        return array( 'gross_pence' => $gross, 'commission_pence' => $commission, 'leader_before_fees_pence' => $gross - $commission );
    }
    /**
     * Administrator-controlled estimate used for the single parent-facing fee.
     * Values are assumptions only; actual Stripe fees can vary by payment method.
     */
    public static function parent_booking_fee_settings() {
        $defaults = array( 'processing_percent' => '1.50', 'processing_fixed_pence' => 20 );
        $saved = get_option( 'bh3_parent_booking_fee_settings', array() );
        $saved = is_array( $saved ) ? $saved : array();
        $percent = isset( $saved['processing_percent'] ) && is_numeric( $saved['processing_percent'] )
            ? (float) $saved['processing_percent'] : (float) $defaults['processing_percent'];
        $fixed = isset( $saved['processing_fixed_pence'] ) ? absint( $saved['processing_fixed_pence'] ) : $defaults['processing_fixed_pence'];
        if ( $percent < 0 || $percent >= 100 ) { $percent = (float) $defaults['processing_percent']; }
        if ( $fixed > 10000 ) { $fixed = $defaults['processing_fixed_pence']; }
        return array( 'processing_percent' => number_format( $percent, 2, '.', '' ), 'processing_fixed_pence' => $fixed );
    }
    /**
     * Illustrative parent-paid quote in integer pence. The leader's class price
     * is preserved; the single customer fee covers platform commission and an
     * estimated Stripe processing allowance. This is NOT a payment instruction.
     * Configure real processing rates and review fee disclosure before launch.
     */
    public static function parent_paid_fee_preview( $leader_price_pence, $processing_percent = null, $processing_fixed_pence = null ) {
        $settings = self::parent_booking_fee_settings();
        $processing_percent = $processing_percent === null ? (float) $settings['processing_percent'] : (float) $processing_percent;
        $processing_fixed_pence = $processing_fixed_pence === null ? (int) $settings['processing_fixed_pence'] : absint( $processing_fixed_pence );
        $leader_price_pence = absint( $leader_price_pence );
        if ( $leader_price_pence < 1 || $leader_price_pence > 10000000 ) {
            return new WP_Error( 'invalid_leader_price', 'Invalid class price.' );
        }
        $processing_percent = (float) $processing_percent;
        $processing_fixed_pence = absint( $processing_fixed_pence );
        if ( $processing_percent < 0 || $processing_percent >= 100 || $processing_fixed_pence > 10000 ) {
            return new WP_Error( 'invalid_processing_assumption', 'Invalid processing fee assumption.' );
        }
        $platform_pence = (int) round( $leader_price_pence * self::rate() / 100, 0, PHP_ROUND_HALF_UP );
        // Gross up for Stripe's percentage charged on the parent-facing total.
        $total_pence = (int) ceil( ( $leader_price_pence + $platform_pence + $processing_fixed_pence ) / ( 1 - $processing_percent / 100 ) );
        $customer_fee_pence = $total_pence - $leader_price_pence;
        return array(
            'leader_price_pence' => $leader_price_pence,
            'booking_fee_pence' => $customer_fee_pence,
            'parent_total_pence' => $total_pence,
            'platform_component_pence' => $platform_pence,
            'estimated_processing_pence' => $customer_fee_pence - $platform_pence,
            'processing_percent_assumption' => $processing_percent,
            'processing_fixed_pence_assumption' => $processing_fixed_pence,
            'preview_only' => true,
        );
    }
    public static function policy() {
        $defaults = array( 'processing_fees' => 'leader', 'payout_schedule' => 'weekly', 'refund_policy' => 'manual_review' );
        $saved = get_option( 'bh3_marketplace_policy', array() );
        $saved = is_array( $saved ) ? $saved : array();
        return array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
    }
    public static function booking_snapshot( $gross_pence ) {
        return array_merge( self::split_pence( $gross_pence ), array(
            'commission_rate_percent' => number_format( self::rate(), 2, '.', '' ),
            'policy' => self::policy(),
            'currency' => 'gbp',
        ) );
    }
    /**
     * Server-authoritative booking quote. Not a capacity hold or a payment request.
     * Never accept an amount or commission from browser/Fluent Forms input.
     */
    public static function quote_session( $session_id, $places = 1 ) {
        $session_id = absint( $session_id );
        $places = filter_var( $places, FILTER_VALIDATE_INT );
        if ( ! $session_id || $places === false || $places < 1 || $places > 20 ) {
            return new WP_Error( 'invalid_booking_request', 'Invalid session or number of places.' );
        }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $activities = $wpdb->prefix . 'activities';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.id,s.status,a.id AS activity_id,a.organiser_id,a.status AS activity_status
             FROM `{$sessions}` s INNER JOIN `{$activities}` a ON a.id=s.activity_id
             WHERE s.id=%d LIMIT 1", $session_id
        ), ARRAY_A );
        if ( ! $row || $row['activity_status'] !== 'published' || $row['status'] !== 'scheduled' ) {
            return new WP_Error( 'session_unavailable', 'This session is not available for booking.' );
        }
        $configs = get_option( 'bh3_session_booking_config', array() );
        $config = is_array( $configs ) ? ( $configs[$session_id] ?? null ) : null;
        if ( ! is_array( $config ) || empty( $config['enabled'] ) ) {
            return new WP_Error( 'booking_disabled', 'Bookings are not enabled for this session.' );
        }
        $price = isset( $config['price'] ) && is_scalar( $config['price'] ) ? (string) $config['price'] : '';
        if ( ! preg_match( '/^(?:0|[1-9][0-9]{0,5})(?:\\.[0-9]{1,2})?$/', $price ) || (float) $price > 100000 ) {
            return new WP_Error( 'invalid_server_price', 'Session price is not configured correctly.' );
        }
        $parts = explode( '.', $price, 2 );
        $unit_pence = (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '', 2, '0' );
        $gross_pence = $unit_pence * $places;
        if ( $gross_pence < 1 ) {
            return new WP_Error( 'free_session', 'Free sessions do not require Stripe checkout.' );
        }
        $capacity = isset( $config['capacity'] ) ? absint( $config['capacity'] ) : 0;
        if ( $capacity && $places > $capacity ) {
            return new WP_Error( 'capacity_exceeded', 'Requested places exceed session capacity.' );
        }
        // Keep legacy gross/commission snapshot unchanged until reservation and
        // webhook schema are migrated together. Expose a separate server-owned
        // parent-paid breakdown for UI review; never use browser-supplied totals.
        $parent_fee_quote = self::parent_paid_fee_preview( $gross_pence );
        if ( is_wp_error( $parent_fee_quote ) ) {
            return $parent_fee_quote;
        }
        return array_merge( self::booking_snapshot( $gross_pence ), array(
            'parent_paid_fee_quote' => $parent_fee_quote,
            'session_id' => $session_id,
            'activity_id' => (int) $row['activity_id'],
            'organiser_id' => (int) $row['organiser_id'],
            'places' => $places,
            'unit_price_pence' => $unit_pence,
            'capacity' => $capacity,
            'capacity_checked_against_existing_bookings' => false,
            'checkout_enabled' => false,
        ) );
    }
    /**
     * Read conservative availability for one occurrence. Reserved places remain held
     * until a verified expiry transition changes booking_status to expired.
     * This is a read-only preview; reservation creation must use an atomic DB lock.
     */
    public static function occurrence_availability( $session_id, $occurrence_date ) {
        $session_id = absint( $session_id );
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $session_id || ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date ) {
            return new WP_Error( 'invalid_occurrence', 'Invalid session date.' );
        }
        $configs = get_option( 'bh3_session_booking_config', array() );
        $config = is_array( $configs ) ? ( $configs[$session_id] ?? array() ) : array();
        if ( empty( $config['enabled'] ) || empty( $config['capacity'] ) ) {
            return new WP_Error( 'capacity_not_configured', 'A capacity must be configured before paid checkout.' );
        }
        $capacity = absint( $config['capacity'] );
        if ( $capacity < 1 ) { return new WP_Error( 'invalid_capacity', 'Invalid session capacity.' ); }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return new WP_Error( 'bookings_table_missing', 'Booking storage has not been installed.' );
        }
        // Booking status is authoritative for occupancy, not payment status.
        // Fail closed for unknown statuses or invalid place quantities; do not
        // accidentally reopen capacity while expiry/cancellation is unverified.
        $totals = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN booking_status IN ('reserved','confirmed','enrolled') THEN places ELSE 0 END),0) AS occupied,
                COALESCE(SUM(CASE WHEN booking_status NOT IN ('reserved','confirmed','enrolled','cancelled','canceled','expired','rejected') OR booking_status IS NULL OR places < 1 THEN 1 ELSE 0 END),0) AS invalid_rows
             FROM `{$table}` WHERE session_id=%d AND occurrence_date=%s",
            $session_id, $occurrence_date
        ), ARRAY_A );
        if ( ! is_array( $totals ) || $wpdb->last_error || (int) $totals['invalid_rows'] > 0 ) {
            return new WP_Error( 'booking_status_review_required', 'Booking records need review before availability can be calculated.' );
        }
        $used = (int) $totals['occupied'];
        if ( $used > $capacity ) {
            return new WP_Error( 'over_capacity', 'Recorded bookings exceed session capacity.' );
        }
        return array(
            'session_id' => $session_id, 'occurrence_date' => $occurrence_date,
            'capacity' => $capacity, 'reserved_or_paid' => $used,
            'available' => max( 0, $capacity - $used ),
            'authoritative_reservation_created' => false,
        );
    }
    /**
     * Internal-only atomic reservation primitive. No public action is registered.
     * Requires an existing authenticated customer and an installed bookings table.
     */
    /**
     * Confirm a requested calendar date is an actual scheduled occurrence.
     * Reject unsupported frequency rules rather than taking payment for a non-event.
     */
    public static function validate_occurrence( $session_id, $occurrence_date ) {
        $session_id = absint( $session_id );
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $session_id || ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date ) {
            return new WP_Error( 'invalid_occurrence', 'Invalid session date.' );
        }
        $today = new DateTimeImmutable( 'today', wp_timezone() );
        if ( $date < $today || $date > $today->modify( '+365 days' ) ) {
            return new WP_Error( 'date_out_of_range', 'Session date is outside the bookable range.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT day_of_week,start_date,end_date,frequency,term_time_only,status FROM `{$table}` WHERE id=%d LIMIT 1",
            $session_id
        ), ARRAY_A );
        if ( ! $rule || $rule['status'] !== 'scheduled' ) {
            return new WP_Error( 'session_unavailable', 'Session is not scheduled.' );
        }
        if ( $rule['frequency'] === 'weekly' ) {
            if ( (int) $rule['day_of_week'] !== (int) $date->format( 'N' ) ) {
                return new WP_Error( 'wrong_weekday', 'This class does not run on the selected day.' );
            }
        } elseif ( $rule['frequency'] === 'once' || $rule['frequency'] === 'single' ) {
            if ( empty( $rule['start_date'] ) || $rule['start_date'] !== $occurrence_date ) {
                return new WP_Error( 'wrong_date', 'This class does not run on the selected date.' );
            }
        } else {
            return new WP_Error( 'unsupported_frequency', 'This timetable type is not ready for paid booking.' );
        }
        if ( ( ! empty( $rule['start_date'] ) && $occurrence_date < $rule['start_date'] )
            || ( ! empty( $rule['end_date'] ) && $occurrence_date > $rule['end_date'] ) ) {
            return new WP_Error( 'outside_schedule', 'This date is outside the class schedule.' );
        }
        if ( ! empty( $rule['term_time_only'] ) ) {
            $ranges = get_option( 'bh3_term_date_ranges', array() );
            $valid = false;
            foreach ( is_array( $ranges ) ? $ranges : array() as $range ) {
                if ( is_array( $range ) && ! empty( $range['start'] ) && ! empty( $range['end'] )
                    && $occurrence_date >= $range['start'] && $occurrence_date <= $range['end'] ) {
                    $valid = true; break;
                }
            }
            if ( ! $valid ) { return new WP_Error( 'outside_term', 'No confirmed term-time session on this date.' ); }
        }
        return true;
    }
    public static function reserve_occurrence( $session_id, $occurrence_date, $places, $customer_user_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true ) {
            return new WP_Error( 'reservations_disabled', 'Booking reservations are disabled.' );
        }
        $customer_user_id = absint( $customer_user_id );
        if ( ! $customer_user_id || ! get_userdata( $customer_user_id ) ) {
            return new WP_Error( 'customer_required', 'A valid customer account is required.' );
        }
        $quote = self::quote_session( $session_id, $places );
        if ( is_wp_error( $quote ) ) { return $quote; }
        $date = is_string( $occurrence_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $occurrence_date, wp_timezone() ) : false;
        if ( ! $date || $date->format( 'Y-m-d' ) !== $occurrence_date || $date < new DateTimeImmutable( 'today', wp_timezone() ) ) {
            return new WP_Error( 'invalid_occurrence', 'Choose a valid future session date.' );
        }
        $valid_occurrence = self::validate_occurrence( $session_id, $occurrence_date );
        if ( is_wp_error( $valid_occurrence ) ) { return $valid_occurrence; }
        // Never use client-provided price, organiser or activity IDs.
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $lock = 'bh3_' . md5( $wpdb->prefix . ':' . $quote['session_id'] . ':' . $occurrence_date );
        $acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
        if ( $acquired !== 1 ) {
            return new WP_Error( 'booking_busy', 'This session is busy. Please try again.' );
        }
        try {
            $availability = self::occurrence_availability( $quote['session_id'], $occurrence_date );
            if ( is_wp_error( $availability ) ) { return $availability; }
            if ( $availability['available'] < $quote['places'] ) {
                return new WP_Error( 'session_full', 'Not enough places remain.' );
            }
            $recheck_date = self::validate_occurrence( $session_id, $occurrence_date );
            if ( is_wp_error( $recheck_date ) ) { return $recheck_date; }
            // Fail closed if session pricing or capacity changed during the wait.
            $current = self::quote_session( $session_id, $places );
            if ( is_wp_error( $current ) || $current['gross_pence'] !== $quote['gross_pence'] ) {
                return new WP_Error( 'quote_changed', 'Session pricing changed. Please try again.' );
            }
            $reference = wp_generate_uuid4();
            $now = current_time( 'mysql', true );
            $expires = gmdate( 'Y-m-d H:i:s', time() + 35 * MINUTE_IN_SECONDS );
            $ok = $wpdb->insert( $table, array(
                'public_reference' => $reference,
                'session_id' => $quote['session_id'],
                'occurrence_date' => $occurrence_date,
                'activity_id' => $quote['activity_id'],
                'organiser_id' => $quote['organiser_id'],
                'customer_user_id' => $customer_user_id,
                'places' => $quote['places'],
                'currency' => 'gbp',
                'unit_price_pence' => $quote['unit_price_pence'],
                'gross_pence' => $quote['gross_pence'],
                'commission_pence' => $quote['commission_pence'],
                'leader_before_fees_pence' => $quote['leader_before_fees_pence'],
                'commission_rate_percent' => $quote['commission_rate_percent'],
                'booking_status' => 'reserved',
                'payment_status' => 'pending',
                'reservation_expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ) );
            if ( $ok !== 1 ) {
                return new WP_Error( 'reservation_failed', 'Could not reserve places.' );
            }
            return array( 'booking_id' => (int) $wpdb->insert_id, 'reference' => $reference,
                'expires_at_utc' => $expires, 'status' => 'pending', 'checkout_enabled' => false );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
    /**
     * Internal Stripe Checkout test-session creator. No public route is registered.
     * Destination charges make the platform responsible for Stripe processing fees,
     * so refuse the default leader-pays policy until a compatible flow is built.
     */
    /**
     * Internal-only staging lifecycle transition. No route, shortcode or form action
     * invokes this method. A separate reviewed controller is required for leaders.
     */
    /**
     * Staging-only, read-only leader ownership gate for future booking actions.
     * This does not grant leaders access to the administrator-only transition method.
     */
    public static function leader_can_manage_booking( $booking_id, $user_id = 0 ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return new WP_Error( 'leader_booking_disabled', 'Leader booking actions are not enabled.' );
        }
        $user_id = absint( $user_id ?: get_current_user_id() );
        $booking_id = absint( $booking_id );
        if ( ! $user_id || ! $booking_id || ! is_user_logged_in() || $user_id !== get_current_user_id() ) {
            return new WP_Error( 'leader_booking_unauthorised', 'Booking access denied.' );
        }
        // Admin organisation preview never grants leader mutation rights.
        $organiser_id = absint( get_user_meta( $user_id, 'bh3_organiser_id', true ) );
        if ( ! $organiser_id ) {
            return new WP_Error( 'leader_booking_unauthorised', 'Booking access denied.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT organiser_id FROM `{$table}` WHERE id=%d LIMIT 1", $booking_id
        ) );
        if ( $owner === null || (int) $owner !== $organiser_id ) {
            return new WP_Error( 'leader_booking_unauthorised', 'Booking access denied.' );
        }
        return true;
    }

    /**
     * Read-only eligibility check for a future leader enrolment action.
     * Recheck all conditions under the booking lock before any actual mutation.
     */
    public static function leader_enrolment_eligibility( $booking_id ) {
        $ownership = self::leader_can_manage_booking( $booking_id );
        if ( is_wp_error( $ownership ) ) { return $ownership; }
        if ( ! defined( 'BH3_ENABLE_LEADER_BOOKING_ACTIONS' ) || BH3_ENABLE_LEADER_BOOKING_ACTIONS !== true ) {
            return new WP_Error( 'leader_actions_disabled', 'Leader booking actions are not enabled.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT booking_status,payment_status,organiser_id,session_id,occurrence_date
             FROM `{$table}` WHERE id=%d LIMIT 1", absint( $booking_id )
        ), ARRAY_A );
        if ( ! $booking || (int) $booking['organiser_id'] !== absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) {
            return new WP_Error( 'leader_booking_unauthorised', 'Booking access denied.' );
        }
        if ( $booking['booking_status'] !== 'confirmed' || $booking['payment_status'] !== 'paid' ) {
            return new WP_Error( 'not_ready_for_enrolment', 'Only confirmed, paid bookings can be enrolled.' );
        }
        if ( absint( $booking['session_id'] ) < 1 || ! preg_match( '/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/', (string) $booking['occurrence_date'] ) ) {
            return new WP_Error( 'invalid_booking_occurrence', 'Booking occurrence needs review.' );
        }
        return true;
    }

    /**
     * Staging-only enrolment POST handler. The booking-level lock protects
     * ownership and paid/confirmed status from changing between checks and write.
     */
    public static function handle_leader_enrol_booking() {
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST'
            || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_LEADER_BOOKING_ACTIONS' ) || BH3_ENABLE_LEADER_BOOKING_ACTIONS !== true
            || ! is_user_logged_in() ) {
            wp_die( 'Booking action unavailable.', '', array( 'response'=>403 ) );
        }
        $booking_id = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! $booking_id || ! wp_verify_nonce( $nonce, 'bh3_enrol_booking_' . $booking_id ) ) {
            wp_die( 'Booking action denied.', '', array( 'response'=>403 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $lock = 'bh3_status_' . md5( $wpdb->prefix . ':' . $booking_id );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            wp_die( 'Booking is busy. Please try again.', '', array( 'response'=>409 ) );
        }
        try {
            $allowed = self::leader_enrolment_eligibility( $booking_id );
            if ( is_wp_error( $allowed ) ) {
                wp_die( esc_html( $allowed->get_error_message() ), '', array( 'response'=>403 ) );
            }
            $changed = $wpdb->update(
                $table,
                array( 'booking_status'=>'enrolled', 'updated_at'=>current_time( 'mysql', true ) ),
                array( 'id'=>$booking_id, 'organiser_id'=>absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ), 'booking_status'=>'confirmed', 'payment_status'=>'paid' ),
                array( '%s', '%s' ),
                array( '%d', '%d', '%s', '%s' )
            );
            if ( $changed !== 1 ) {
                wp_die( 'Booking changed. Please refresh and try again.', '', array( 'response'=>409 ) );
            }
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
        wp_safe_redirect( home_url( '/leader/bookings/?bh_booking_updated=enrolled' ) );
        exit;
    }

    public static function transition_booking_status( $booking_id, $target_status ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_STATUS_TRANSITIONS' ) || BH3_ENABLE_BOOKING_STATUS_TRANSITIONS !== true
            || ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'transition_disabled', 'Booking status changes are not enabled.' );
        }
        $booking_id = absint( $booking_id );
        $target_status = sanitize_key( $target_status );
        if ( ! $booking_id || ! in_array( $target_status, array( 'confirmed', 'enrolled', 'cancelled', 'expired' ), true ) ) {
            return new WP_Error( 'invalid_transition', 'Unsupported booking status change.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $lock = 'bh3_status_' . md5( $wpdb->prefix . ':' . $booking_id );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            return new WP_Error( 'booking_busy', 'Booking is busy. Try again.' );
        }
        try {
            $booking = $wpdb->get_row( $wpdb->prepare(
                "SELECT id,booking_status,payment_status,reservation_expires_at FROM `{$table}` WHERE id=%d LIMIT 1",
                $booking_id
            ), ARRAY_A );
            if ( ! $booking ) { return new WP_Error( 'booking_missing', 'Booking not found.' ); }
            $from = (string) $booking['booking_status'];
            if ( $from === $target_status ) { return array( 'booking_id'=>$booking_id, 'status'=>$target_status, 'changed'=>false ); }
            $allowed = array(
                'reserved' => array( 'confirmed', 'cancelled', 'expired' ),
                'confirmed' => array( 'enrolled', 'cancelled' ),
                'enrolled' => array( 'cancelled' ),
            );
            if ( ! in_array( $target_status, $allowed[$from] ?? array(), true ) ) {
                return new WP_Error( 'invalid_transition', 'This booking status change is not permitted.' );
            }
            if ( $target_status === 'confirmed' && $booking['payment_status'] !== 'paid' ) {
                return new WP_Error( 'payment_not_verified', 'Payment must be verified before confirming this booking.' );
            }
            if ( $target_status === 'expired' ) {
                if ( $booking['payment_status'] !== 'pending' || empty( $booking['reservation_expires_at'] )
                    || strtotime( $booking['reservation_expires_at'] . ' UTC' ) > time() ) {
                    return new WP_Error( 'not_expired', 'Only an elapsed unpaid reservation can expire.' );
                }
            }
            $changed = $wpdb->update(
                $table,
                array( 'booking_status'=>$target_status, 'updated_at'=>current_time( 'mysql', true ) ),
                array( 'id'=>$booking_id, 'booking_status'=>$from ),
                array( '%s', '%s' ),
                array( '%d', '%s' )
            );
            if ( $changed !== 1 ) { return new WP_Error( 'transition_conflict', 'Booking changed during update.' ); }
            return array( 'booking_id'=>$booking_id, 'status'=>$target_status, 'changed'=>true );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
    public static function create_test_checkout( $booking_id, $customer_user_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) || BH3_ENABLE_STRIPE_TEST_CHECKOUT !== true ) {
            return new WP_Error( 'checkout_disabled', 'Checkout is disabled.' );
        }
        $config = self::stripe_status();
        if ( $config['mode'] !== 'test' || ! $config['keys_configured'] ) {
            return new WP_Error( 'stripe_test_required', 'Stripe test credentials required.' );
        }
        if ( self::policy()['processing_fees'] !== 'platform' ) {
            return new WP_Error( 'fee_policy_unsupported', 'Destination charges charge processing fees to the platform. Choose platform fees or use a different payment flow.' );
        }
        $customer_user_id = absint( $customer_user_id );
        if ( ! $customer_user_id || $customer_user_id !== get_current_user_id() ) {
            return new WP_Error( 'customer_mismatch', 'Customer authentication required.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id=%d AND customer_user_id=%d LIMIT 1",
            absint( $booking_id ), $customer_user_id
        ), ARRAY_A );
        if ( ! $booking || $booking['payment_status'] !== 'pending'
            || ! empty( $booking['stripe_checkout_session_id'] )
            || empty( $booking['reservation_expires_at'] )
            || strtotime( $booking['reservation_expires_at'] . ' UTC' ) <= time() + 120 ) {
            return new WP_Error( 'invalid_reservation', 'Reservation unavailable or too close to expiry.' );
        }
        // Once form binding is enabled, no unlinked or unconsented
        // reservation may proceed to Stripe Checkout, even via QA routes.
        if ( defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) && BH3_ENABLE_BOOKING_FORM_BINDING === true ) {
            if ( empty( $booking['booking_submission_id'] ) || empty( $booking['booking_form_id'] )
                || (int) $booking['booking_form_id'] !== absint( get_option( 'bh3_booking_form_id', 8 ) ) ) {
                return new WP_Error( 'booking_form_not_bound', 'Complete and verify the booking form before payment.' );
            }
            $attendees_table = $wpdb->prefix . 'bh3_booking_attendees';
            $consents_table = $wpdb->prefix . 'bh3_booking_consents';
            foreach ( array( $attendees_table, $consents_table ) as $required_table ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $required_table ) ) ) !== $required_table ) {
                    return new WP_Error( 'booking_details_schema_missing', 'Booking details storage is not ready.' );
                }
            }
            $attendee_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$attendees_table}` WHERE booking_id=%d", (int) $booking['id']
            ) );
            $sharing_consent = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$consents_table}` WHERE booking_id=%d AND consent_key=%s AND consent_version=%s AND response=%s",
                (int) $booking['id'], 'share_attendee_details', 'v1', 'yes'
            ) );
            $terms_version = sanitize_key( (string) get_option( 'bh3_booking_terms_version', 'v1' ) );
            $terms_consent = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$consents_table}` WHERE booking_id=%d AND consent_key=%s AND consent_version=%s AND response=%s",
                (int) $booking['id'], 'booking_terms', $terms_version, 'yes'
            ) );
            if ( $attendee_count !== (int) $booking['places'] || $sharing_consent !== 1 || $terms_consent !== 1 ) {
                return new WP_Error( 'booking_details_incomplete', 'Attendees, booking terms and sharing consent must be saved before payment.' );
            }
        }
        $valid = self::validate_occurrence( (int) $booking['session_id'], $booking['occurrence_date'] );
        if ( is_wp_error( $valid ) ) { return $valid; }
        // Payout account belongs to the organiser, never the customer.
        $organiser_users = get_users( array( 'meta_key' => 'bh3_organiser_id',
            'meta_value' => (string) (int) $booking['organiser_id'], 'fields' => 'ID', 'number' => 2 ) );
        if ( count( $organiser_users ) !== 1 ) {
            return new WP_Error( 'organiser_account_ambiguous', 'Organiser payout account must be uniquely linked.' );
        }
        $account = get_user_meta( (int) $organiser_users[0], 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            return new WP_Error( 'leader_not_connected', 'Leader Stripe test account is not connected.' );
        }
        $leader_status = self::leader_stripe_status( (int) $organiser_users[0] );
        if ( $leader_status['state'] !== 'ready_test' ) {
            return new WP_Error( 'leader_not_ready', 'Leader test account is not ready.' );
        }
        $gross = (int) $booking['gross_pence'];
        $commission = (int) $booking['commission_pence'];
        if ( $gross < 50 || $commission < 0 || $commission > $gross || $booking['currency'] !== 'gbp' ) {
            return new WP_Error( 'invalid_payment_amount', 'Invalid booking amount.' );
        }
        // The parent pays one combined booking fee. The destination transfer
        // remains exactly the advertised class price; the platform application
        // fee is the parent-facing fee, from which Stripe processing is paid.
        $parent_fee_quote = self::parent_paid_fee_preview( $gross );
        if ( is_wp_error( $parent_fee_quote )
            || (int) $parent_fee_quote['parent_total_pence'] <= $gross
            || (int) $parent_fee_quote['booking_fee_pence'] !== (int) $parent_fee_quote['parent_total_pence'] - $gross
            || (int) $parent_fee_quote['platform_component_pence'] !== $commission ) {
            return new WP_Error( 'booking_fee_quote_invalid', 'Combined booking fee could not be calculated safely.' );
        }
        $parent_total = (int) $parent_fee_quote['parent_total_pence'];
        $customer_fee = (int) $parent_fee_quote['booking_fee_pence'];
        $estimated_processing = (int) $parent_fee_quote['estimated_processing_pence'];
        $expiry = strtotime( $booking['reservation_expires_at'] . ' UTC' );
        // Reserve a minimum 30-minute Checkout window; leave a safety margin.
        if ( $expiry < time() + 30 * MINUTE_IN_SECONDS ) {
            return new WP_Error( 'hold_too_short', 'Checkout requires a longer capacity hold; no payment session created.' );
        }
        // Lock per booking; Stripe idempotency protects retries after network timeouts.
        $lock = 'bh3_checkout_' . md5( $wpdb->prefix . ':' . $booking['id'] );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            return new WP_Error( 'checkout_busy', 'Checkout is already being prepared.' );
        }
        try {
            $current = $wpdb->get_row( $wpdb->prepare(
                "SELECT payment_status,stripe_checkout_session_id,reservation_expires_at FROM `{$table}` WHERE id=%d LIMIT 1",
                (int) $booking['id']
            ), ARRAY_A );
            if ( ! $current || $current['payment_status'] !== 'pending' || ! empty( $current['stripe_checkout_session_id'] )
                || strtotime( $current['reservation_expires_at'] . ' UTC' ) < time() + 30 * MINUTE_IN_SECONDS ) {
                return new WP_Error( 'checkout_state_changed', 'Reservation is no longer eligible for checkout.' );
            }
            $secret = constant( 'BH3_STRIPE_TEST_SECRET_KEY' );
            $expiry_timestamp = min( time() + 31 * MINUTE_IN_SECONDS, strtotime( $current['reservation_expires_at'] . ' UTC' ) );
            if ( $expiry_timestamp < time() + 30 * MINUTE_IN_SECONDS ) {
                return new WP_Error( 'checkout_expiry_invalid', 'Reservation hold is too short.' );
            }
            $success = add_query_arg( 'bh3_checkout', 'returned', home_url( '/my-hub/' ) );
            $cancel = add_query_arg( 'bh3_checkout', 'cancelled', home_url( '/my-hub/' ) );
            $payload = array(
                'mode' => 'payment',
                'success_url' => $success,
                'cancel_url' => $cancel,
                'client_reference_id' => $booking['public_reference'],
                'expires_at' => $expiry_timestamp,
                'line_items' => array( array(
                    'price_data' => array(
                        'currency' => 'gbp',
                        'unit_amount' => $parent_total,
                        'product_data' => array( 'name' => 'Bubba Hub class booking ' . $booking['public_reference'] ),
                    ),
                    'quantity' => 1,
                ) ),
                'payment_intent_data' => array(
                    'application_fee_amount' => $customer_fee,
                    'transfer_data' => array( 'destination' => $account ),
                    'metadata' => array(
                        'bh3_booking_id' => (string) $booking['id'],
                        'bh3_class_price_pence' => (string) $gross,
                        'bh3_booking_fee_pence' => (string) $customer_fee,
                        'bh3_platform_component_pence' => (string) $commission,
                        'bh3_estimated_processing_pence' => (string) $estimated_processing,
                    ),
                ),
                'metadata' => array(
                    'bh3_booking_id' => (string) $booking['id'],
                    'bh3_class_price_pence' => (string) $gross,
                    'bh3_booking_fee_pence' => (string) $customer_fee,
                    'bh3_platform_component_pence' => (string) $commission,
                    'bh3_estimated_processing_pence' => (string) $estimated_processing,
                ),
            );
            $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
                'timeout' => 25,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret,
                    'Idempotency-Key' => 'bh3-test-checkout-' . (int) $booking['id'],
                ),
                'body' => $payload,
            ) );
            if ( is_wp_error( $response ) ) { return new WP_Error( 'stripe_unavailable', 'Stripe checkout could not be reached.' ); }
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( wp_remote_retrieve_response_code( $response ) !== 200 || ! is_array( $data )
                || ! preg_match( '/^cs_test_[A-Za-z0-9_]+$/', (string) ( $data['id'] ?? '' ) )
                || ! isset( $data['url'] ) || ! wp_http_validate_url( $data['url'] )
                || strtolower( (string) wp_parse_url( $data['url'], PHP_URL_HOST ) ) !== 'checkout.stripe.com'
                || ( $data['livemode'] ?? null ) !== false
                || (int) ( $data['amount_total'] ?? -1 ) !== $parent_total
                || ( $data['currency'] ?? '' ) !== 'gbp' ) {
                return new WP_Error( 'stripe_checkout_rejected', 'Stripe did not return a valid test Checkout session.' );
            }
            $saved = $wpdb->query( $wpdb->prepare(
                "UPDATE `{$table}` SET stripe_checkout_session_id=%s,updated_at=%s
                 WHERE id=%d AND payment_status='pending' AND stripe_checkout_session_id IS NULL",
                $data['id'], current_time( 'mysql', true ), (int) $booking['id']
            ) );
            if ( $saved !== 1 ) {
                return new WP_Error( 'checkout_not_saved', 'Checkout session could not be saved; manual reconciliation required.' );
            }
            return array( 'checkout_session_id' => $data['id'], 'checkout_url' => $data['url'],
                'booking_id' => (int) $booking['id'], 'test_mode' => true );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
    /**
     * Internal-only signed Stripe event verifier. Does not mutate booking status.
     * Call from a dedicated staging webhook controller after registration.
     */
    /**
     * Explicitly gated, staging-only webhook ingestion. Respond with a retryable
     * error when persistence fails. Never mark a booking paid at ingestion.
     */
    /**
     * Authenticated staging-only booking endpoint. Frontend callers must send
     * X-WP-Nonce; no price or organiser fields are accepted from the browser.
     */
    /**
     * Parent-facing preview. Fluent Form 7 is informational only until its
     * validated submission is linked to a booking; do not imply it charges.
     * Usage: [bh_parent_booking session_id="123"]
     */
    /**
     * Read-only Fluent Forms submission ownership check. This is a prerequisite
     * only: child consent and session-specific fields still require validation.
     */
    public static function inspect_booking_form_submission( $submission_id, $customer_user_id ) {
        $submission_id = absint( $submission_id );
        $customer_user_id = absint( $customer_user_id );
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! $submission_id || ! $customer_user_id || $customer_user_id !== get_current_user_id()
            || ! is_user_logged_in() ) {
            return new WP_Error( 'form_access_denied', 'Booking form access denied.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_submissions';
        $form_id = absint( get_option( 'bh3_booking_form_id', 8 ) );
        if ( ! $form_id ) {
            return new WP_Error( 'booking_form_missing', 'Booking form is not configured.' );
        }
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id,form_id,user_id,created_at FROM `{$table}` WHERE id=%d AND form_id=%d AND user_id=%d LIMIT 1",
            $submission_id, $form_id, $customer_user_id
        ), ARRAY_A );
        if ( ! $row ) {
            return new WP_Error( 'submission_not_owned', 'Booking details submission was not found for this account.' );
        }
        $created = strtotime( $row['created_at'] . ' UTC' );
        if ( ! $created || $created > time() + 300 || $created < time() - DAY_IN_SECONDS ) {
            return new WP_Error( 'submission_outdated', 'Please submit fresh booking details.' );
        }
        return array(
            'submission_id' => (int) $row['id'],
            'form_id' => (int) $row['form_id'],
            'customer_user_id' => (int) $row['user_id'],
            'owned' => true,
            'child_consent_verified' => false,
            'session_selection_verified' => false,
            'checkout_authorised' => false,
        );
    }
    /**
     * Validate the expected Form 7 booking fields without trusting client-side
     * values. Admin must map actual Fluent Forms input names first.
     * No payment or capacity reservation is authorised by this check alone.
     */
    /**
     * Fluent Forms confirmation is the hand-off point: after Form 7 has
     * successfully saved, validate that exact saved submission, reserve its
     * selected occurrence, bind attendees/consent, then redirect to Stripe.
     */
    public static function filter_form7_checkout_confirmation( $return_data, $form, $confirmation, $submission_id, $form_data ) {
        $form_id = absint( get_option( 'bh3_booking_form_id', 8 ) );
        if ( absint( $form->id ?? 0 ) !== $form_id ) { return $return_data; }
        if ( ! is_user_logged_in() || ! is_array( $form_data ) ) {
            $return_data['message'] = 'Your details were received, but you must be signed in to continue to secure checkout.';
            return $return_data;
        }
        $submitted_session_id = absint( $form_data['session_id'] ?? 0 );
        if ( ! self::verify_booking_form_context_token(
            $form_data['booking_context_token'] ?? '', $submitted_session_id, get_current_user_id()
        ) ) {
            $return_data['message'] = 'This booking form has expired or does not match the selected class. Please refresh the class page and try again.';
            return $return_data;
        }
        $request = new WP_REST_Request( 'POST', '/bubba-hub/v3/parent-bound-test-checkout' );
        $request->set_param( 'submission_id', absint( $submission_id ) );
        $request->set_param( 'session_id', absint( $form_data['session_id'] ?? 0 ) );
        $request->set_param( 'occurrence_date', sanitize_text_field( (string) ( $form_data['occurrence_date'] ?? '' ) ) );
        $request->set_param( 'places', absint( $form_data['places'] ?? 0 ) );
        $result = self::start_bound_parent_test_checkout( $request );
        if ( is_wp_error( $result ) ) {
            $return_data['message'] = 'Your booking details have been saved, but secure checkout could not start: '
                . esc_html( $result->get_error_message() ) . ' Please contact Bubba Hub before submitting again.';
            return $return_data;
        }
        $payload = $result instanceof WP_REST_Response ? $result->get_data() : $result;
        if ( ! is_array( $payload ) || empty( $payload['checkout_url'] )
            || ! wp_http_validate_url( $payload['checkout_url'] )
            || strpos( $payload['checkout_url'], 'https://checkout.stripe.com/' ) !== 0 ) {
            $return_data['message'] = 'Your booking details were saved, but a secure checkout link could not be verified. Please contact Bubba Hub.';
            return $return_data;
        }
        $return_data['redirectUrl'] = esc_url_raw( $payload['checkout_url'] );
        $return_data['message'] = 'Your booking details are saved. Taking you to secure Stripe checkout…';
        return $return_data;
    }

    public static function filter_form7_checkbox_field( $data, $form ) {
        if ( absint( $form->id ?? 0 ) !== absint( get_option( 'bh3_booking_form_id', 8 ) )
            || ( $data['attributes']['name'] ?? '' ) !== 'family_profile_ids'
            || ! is_user_logged_in() ) { return $data; }
        $options = array();
        foreach ( self::booking_profile_choices( get_current_user_id() ) as $profile ) {
            $type = ( $profile['profile_type'] ?? '' ) === 'bump' ? 'Pregnancy' : 'Child';
            $options[] = array( 'label' => sanitize_text_field( $profile['display_name'] . ' (' . $type . ')' ),
                'value' => (string) absint( $profile['id'] ), 'calc_value' => '' );
        }
        if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $data['settings']['advanced_options'] = $options;
        }
        return $data;
    }

    public static function create_booking_form_context_token( $session_id, $user_id, $expires = 0 ) {
        $session_id = absint( $session_id );
        $user_id = absint( $user_id );
        $expires = $expires ? absint( $expires ) : time() + 2 * HOUR_IN_SECONDS;
        if ( ! $session_id || ! $user_id || $expires <= time() || $expires > time() + 2 * HOUR_IN_SECONDS ) { return ''; }
        $payload = $session_id . '|' . $user_id . '|' . $expires;
        $signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
        return $payload . '|' . $signature;
    }

    public static function verify_booking_form_context_token( $token, $session_id, $user_id ) {
        if ( ! is_string( $token ) || ! preg_match( '/^([1-9][0-9]*)\\|([1-9][0-9]*)\\|([1-9][0-9]*)\\|([a-f0-9]{64})$/', $token, $match ) ) {
            return false;
        }
        $payload = $match[1] . '|' . $match[2] . '|' . $match[3];
        $expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
        return (int) $match[1] === absint( $session_id )
            && (int) $match[2] === absint( $user_id )
            && (int) $match[3] >= time()
            && (int) $match[3] <= time() + 2 * HOUR_IN_SECONDS
            && hash_equals( $expected, $match[4] );
    }

    public static function filter_form7_hidden_field( $data, $form ) {
        if ( absint( $form->id ?? 0 ) !== absint( get_option( 'bh3_booking_form_id', 8 ) )
            || empty( $GLOBALS['bh3_form7_session_id'] ) || ! is_user_logged_in() ) { return $data; }
        $field_name = $data['attributes']['name'] ?? '';
        if ( $field_name === 'session_id' ) {
            $data['attributes']['value'] = (string) absint( $GLOBALS['bh3_form7_session_id'] );
        } elseif ( $field_name === 'booking_context_token' ) {
            $data['attributes']['value'] = self::create_booking_form_context_token(
                $GLOBALS['bh3_form7_session_id'], get_current_user_id()
            );
        }
        return $data;
    }

    public static function is_affirmative_booking_consent( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $choice ) {
                if ( in_array( $choice, array( 'yes', '1', 1, true ), true ) ) { return true; }
            }
            return false;
        }
        return in_array( $value, array( 'yes', '1', 1, true ), true );
    }

    public static function booking_form_field_map() {
        $defaults = array(
            'session_id' => 'session_id', 'occurrence_date' => 'occurrence_date',
            'places' => 'places', 'consent' => 'booking_terms_consent',
            'profile_ids' => 'family_profile_ids', 'share_consent' => 'share_child_details_consent',
            'parent_name' => 'parent_name', 'parent_email' => 'parent_email',
        );
        $saved = get_option( 'bh3_booking_form_field_map', array() );
        return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
    }

    public static function inspect_booking_form_fields( $submission_id, $customer_user_id, $expected_session_id, $expected_date, $expected_places ) {
        $owned = self::inspect_booking_form_submission( $submission_id, $customer_user_id );
        if ( is_wp_error( $owned ) ) { return $owned; }
        $map = self::booking_form_field_map();
        $required = array( 'session_id', 'occurrence_date', 'places', 'consent', 'parent_name', 'parent_email' );
        if ( ! is_array( $map ) ) {
            return new WP_Error( 'booking_fields_unmapped', 'Booking form field mapping is missing.' );
        }
        foreach ( $required as $field ) {
            if ( empty( $map[ $field ] ) || ! is_string( $map[ $field ] )
                || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $map[ $field ] ) ) {
                return new WP_Error( 'booking_fields_unmapped', 'Booking form field mapping is incomplete.' );
            }
        }
        if ( count( array_unique( array_values( array_intersect_key( $map, array_flip( $required ) ) ) ) ) !== count( $required ) ) {
            return new WP_Error( 'booking_fields_ambiguous', 'Booking form field mapping has duplicate keys.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_submissions';
        $raw = $wpdb->get_var( $wpdb->prepare(
            "SELECT response FROM `{$table}` WHERE id=%d AND form_id=%d AND user_id=%d LIMIT 1",
            $owned['submission_id'], $owned['form_id'], $owned['customer_user_id']
        ) );
        $data = json_decode( (string) $raw, true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'booking_fields_invalid', 'Booking details could not be read.' );
        }
        $session = $data[ $map['session_id'] ] ?? null;
        $date = $data[ $map['occurrence_date'] ] ?? null;
        $places = $data[ $map['places'] ] ?? null;
        $consent = $data[ $map['consent'] ] ?? null;
        if ( ! is_scalar( $session ) || ! ctype_digit( (string) $session )
            || (int) $session !== absint( $expected_session_id )
            || ! is_string( $date ) || $date !== $expected_date
            || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $date )
            || ! is_scalar( $places ) || ! ctype_digit( (string) $places )
            || (int) $places !== absint( $expected_places )
            || (int) $places < 1 || (int) $places > 20 ) {
            return new WP_Error( 'booking_selection_mismatch', 'Booking details do not match the selected class, date and places.' );
        }
        $parent_name = $data[ $map['parent_name'] ] ?? '';
        $parent_email = $data[ $map['parent_email'] ] ?? '';
        $account = get_userdata( absint( $customer_user_id ) );
        if ( ! is_string( $parent_name ) || trim( $parent_name ) === ''
            || ! is_string( $parent_email ) || ! is_email( $parent_email )
            || ! $account || ! is_email( $account->user_email )
            || ! hash_equals( strtolower( trim( $account->user_email ) ), strtolower( trim( $parent_email ) ) ) ) {
            return new WP_Error( 'booking_contact_mismatch', 'Enter your name and use the email address linked to your Bubba Hub account.' );
        }
        // Consent must be an explicit affirmative value, never a non-empty
        // arbitrary string, unchecked checkbox, or inferred default.
        if ( ! self::is_affirmative_booking_consent( $consent ) ) {
            return new WP_Error( 'booking_consent_missing', 'Booking consent is required.' );
        }
        $owned['session_selection_verified'] = true;
        $owned['consent_verified'] = true;
        $owned['checkout_authorised'] = false; // Child profile ownership is not yet checked.
        return $owned;
    }
    /**
     * Return only the selected profiles belonging to the authenticated parent.
     * The leader-facing snapshot deliberately excludes full dates of birth,
     * family account identifiers and unselected children.
     */
    /**
     * Validate selected family profiles and explicit sharing consent from a
     * server-stored, parent-owned Fluent Forms submission. Never trust
     * browser checkbox values alone, and never authorise Checkout here.
     */
    /**
     * Validate guest-entered attendees before a future booking write.
     * Returns only minimal attendee snapshots; no dates of birth or contact
     * details. This is validation only, not guest Checkout authorisation.
     */
    /**
     * Atomically attach an already-verified Fluent Form 7 submission and
     * selected family attendees to an existing pending reservation.
     * This does not authorise payment or dispatch notifications.
     */
    public static function bind_parent_submission_to_booking( $booking_id, $submission_id, $customer_user_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) || BH3_ENABLE_BOOKING_FORM_BINDING !== true
            || ! is_user_logged_in() || get_current_user_id() !== absint( $customer_user_id ) ) {
            return new WP_Error( 'booking_binding_disabled', 'Booking form binding is unavailable.' );
        }
        $booking_id = absint( $booking_id );
        $submission_id = absint( $submission_id );
        if ( ! $booking_id || ! $submission_id ) { return new WP_Error( 'booking_binding_invalid', 'Booking or submission is missing.' ); }
        global $wpdb;
        $bookings = $wpdb->prefix . 'bh3_bookings';
        $attendees_table = $wpdb->prefix . 'bh3_booking_attendees';
        $consents_table = $wpdb->prefix . 'bh3_booking_consents';
        foreach ( array( $bookings, $attendees_table, $consents_table ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return new WP_Error( 'booking_schema_missing', 'Booking tables must be migrated first.' );
            }
        }
        $lock = 'bh3_bind_' . md5( $wpdb->prefix . ':' . $booking_id );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            return new WP_Error( 'booking_binding_busy', 'Booking is being updated. Try again.' );
        }
        try {
            $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$bookings}` WHERE id=%d", $booking_id ), ARRAY_A );
            if ( ! $booking || (int) $booking['customer_user_id'] !== absint( $customer_user_id )
                || $booking['payment_status'] !== 'pending'
                || ! empty( $booking['booking_submission_id'] )
                || strtotime( (string) $booking['reservation_expires_at'] . ' UTC' ) <= time() ) {
                return new WP_Error( 'booking_not_bindable', 'Booking is unavailable or reservation has expired.' );
            }
            $checked = self::inspect_booking_form_attendees( $submission_id, $customer_user_id,
                (int) $booking['session_id'], (string) $booking['occurrence_date'], (int) $booking['places'] );
            if ( is_wp_error( $checked ) ) { return $checked; }
            $now = current_time( 'mysql', true );
            if ( $wpdb->query( 'START TRANSACTION' ) === false ) {
                return new WP_Error( 'booking_transaction_failed', 'Could not start booking update.' );
            }
            $success = $wpdb->update( $bookings,
                array( 'booking_form_id' => (int) $checked['form_id'],
                    'booking_submission_id' => $submission_id, 'updated_at' => $now ),
                array( 'id' => $booking_id, 'payment_status' => 'pending', 'booking_submission_id' => null ),
                array( '%d', '%d', '%s' ), array( '%d', '%s', '%d' ) ) === 1;
            if ( $success ) {
                foreach ( $checked['attendees'] as $index => $person ) {
                    $success = $wpdb->insert( $attendees_table, array(
                        'booking_id' => $booking_id, 'attendee_number' => $index + 1,
                        'family_profile_id' => absint( $person['profile_id'] ?? 0 ) ?: null,
                        'attendee_type' => $person['type'], 'attendee_name' => $person['name'],
                        'gender' => $person['gender'], 'year_of_birth' => $person['year_of_birth'],
                        'created_at' => $now,
                    ) ) === 1;
                    if ( ! $success ) { break; }
                }
            }
            if ( $success ) {
                $success = $wpdb->insert( $consents_table, array(
                    'booking_id' => $booking_id, 'consent_key' => 'share_attendee_details',
                    'consent_version' => 'v1', 'response' => 'yes',
                    'response_at' => $now, 'actor_user_id' => $customer_user_id,
                    'source' => 'booking', 'created_at' => $now,
                ) ) === 1;
            }
            if ( $success ) {
                $terms_version = sanitize_key( (string) get_option( 'bh3_booking_terms_version', 'v1' ) );
                $success = $wpdb->insert( $consents_table, array(
                    'booking_id' => $booking_id, 'consent_key' => 'booking_terms',
                    'consent_version' => $terms_version, 'response' => 'yes',
                    'response_at' => $now, 'actor_user_id' => $customer_user_id,
                    'source' => 'booking', 'created_at' => $now,
                ) ) === 1;
            }
            if ( ! $success ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'booking_binding_failed', 'Booking details could not be saved.' );
            }
            if ( $wpdb->query( 'COMMIT' ) === false ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'booking_commit_failed', 'Booking update could not be confirmed.' );
            }
            return array( 'booking_id' => $booking_id, 'submission_id' => $submission_id,
                'attendee_count' => count( $checked['attendees'] ), 'checkout_authorised' => false );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    public static function validate_guest_booking_attendees( $submitted, $places ) {
        $places = absint( $places );
        if ( ! is_array( $submitted ) || $places < 1 || $places > 20 || count( $submitted ) !== $places ) {
            return new WP_Error( 'guest_attendee_count', 'Enter one attendee for each booked place.' );
        }
        $attendees = array();
        foreach ( $submitted as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['type'] ) || ! is_string( $row['type'] )
                || ! in_array( $row['type'], array( 'child', 'bump' ), true ) ) {
                return new WP_Error( 'guest_attendee_type', 'Choose child or pregnancy for each attendee.' );
            }
            if ( $row['type'] === 'bump' ) {
                $attendees[] = array( 'type' => 'bump', 'name' => 'Pregnancy', 'gender' => '', 'year_of_birth' => null );
                continue;
            }
            $name = isset( $row['name'] ) && is_string( $row['name'] ) ? trim( sanitize_text_field( $row['name'] ) ) : '';
            if ( $name === '' || mb_strlen( $name ) > 100 ) {
                return new WP_Error( 'guest_child_name', 'Enter a child name of up to 100 characters.' );
            }
            $year = $row['year_of_birth'] ?? null;
            $current_year = (int) wp_date( 'Y' );
            if ( ! is_scalar( $year ) || ! ctype_digit( (string) $year )
                || (int) $year < 1900 || (int) $year > $current_year ) {
                return new WP_Error( 'guest_child_year', 'Enter a valid year of birth.' );
            }
            $gender = isset( $row['gender'] ) && is_string( $row['gender'] ) ? trim( sanitize_text_field( $row['gender'] ) ) : '';
            if ( mb_strlen( $gender ) > 40 ) {
                return new WP_Error( 'guest_child_gender', 'Gender must be 40 characters or fewer.' );
            }
            $attendees[] = array( 'type' => 'child', 'name' => $name,
                'gender' => $gender, 'year_of_birth' => (int) $year );
        }
        return $attendees;
    }

    public static function inspect_booking_form_attendees( $submission_id, $customer_user_id, $expected_session_id, $expected_date, $expected_places ) {
        $verified = self::inspect_booking_form_fields( $submission_id, $customer_user_id, $expected_session_id, $expected_date, $expected_places );
        if ( is_wp_error( $verified ) ) { return $verified; }
        $map = self::booking_form_field_map();
        foreach ( array( 'profile_ids', 'share_consent' ) as $field ) {
            if ( empty( $map[ $field ] ) || ! is_string( $map[ $field ] )
                || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $map[ $field ] ) ) {
                return new WP_Error( 'attendee_fields_unmapped', 'Attendee selection and sharing consent fields are required.' );
            }
        }
        $keys = array();
        foreach ( array( 'session_id', 'occurrence_date', 'places', 'consent', 'profile_ids', 'share_consent' ) as $field ) {
            $keys[] = $map[ $field ];
        }
        if ( count( array_unique( $keys ) ) !== count( $keys ) ) {
            return new WP_Error( 'attendee_fields_ambiguous', 'Booking form field mappings must be unique.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_submissions';
        $raw = $wpdb->get_var( $wpdb->prepare(
            "SELECT response FROM `{$table}` WHERE id=%d AND form_id=%d AND user_id=%d LIMIT 1",
            $verified['submission_id'], $verified['form_id'], $verified['customer_user_id']
        ) );
        $data = json_decode( (string) $raw, true );
        if ( ! is_array( $data ) ) { return new WP_Error( 'attendee_form_invalid', 'Attendee form could not be read.' ); }
        $consent = $data[ $map['share_consent'] ] ?? null;
        if ( ! self::is_affirmative_booking_consent( $consent ) ) {
            return new WP_Error( 'attendee_sharing_consent_missing', 'Explicit consent to share selected attendee details is required.' );
        }
        $ids = $data[ $map['profile_ids'] ] ?? null;
        if ( ! is_array( $ids ) || array_keys( $ids ) !== range( 0, count( $ids ) - 1 ) ) {
            return new WP_Error( 'attendee_selection_invalid', 'Select one saved family profile for each place.' );
        }
        $attendees = self::inspect_booking_attendees( $ids, $customer_user_id, $expected_places );
        if ( is_wp_error( $attendees ) ) { return $attendees; }
        $verified['attendees'] = $attendees;
        $verified['attendee_count'] = count( $attendees );
        $verified['child_sharing_consent_verified'] = true;
        $verified['checkout_authorised'] = false; // Requires atomic submission-to-booking binding.
        return $verified;
    }
    public static function inspect_booking_attendees( $profile_ids, $customer_user_id, $places ) {
        $customer_user_id = absint( $customer_user_id );
        $places = absint( $places );
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! is_user_logged_in() || $customer_user_id !== get_current_user_id()
            || ! is_array( $profile_ids ) || $places < 1 || $places > 20
            || count( $profile_ids ) !== $places ) {
            return new WP_Error( 'invalid_attendees', 'Select one family profile for each place.' );
        }
        $ids = array();
        foreach ( $profile_ids as $id ) {
            if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 ) {
                return new WP_Error( 'invalid_profile_id', 'Invalid family profile selection.' );
            }
            $ids[] = (int) $id;
        }
        if ( count( array_unique( $ids ) ) !== count( $ids ) ) {
            return new WP_Error( 'duplicate_attendee', 'Each family profile can only be selected once.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh_family_profiles';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $exists !== $table ) {
            return new WP_Error( 'family_profiles_unavailable', 'Family profiles are unavailable.' );
        }
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( ! is_array( $columns ) || ! in_array( 'gender', $columns, true ) ) {
            return new WP_Error( 'family_profiles_outdated', 'Family profile gender field is not ready.' );
        }
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,profile_type,display_name,profile_date,gender FROM `{$table}` WHERE user_id=%d AND id IN ({$placeholders})",
            array_merge( array( $customer_user_id ), $ids )
        ), ARRAY_A );
        if ( ! is_array( $rows ) || count( $rows ) !== count( $ids ) ) {
            return new WP_Error( 'profile_not_owned', 'One or more family profiles are unavailable for this account.' );
        }
        $by_id = array();
        foreach ( $rows as $row ) { $by_id[ (int) $row['id'] ] = $row; }
        $attendees = array();
        foreach ( $ids as $id ) {
            $row = $by_id[ $id ];
            if ( ! in_array( $row['profile_type'], array( 'child', 'bump' ), true ) ) {
                return new WP_Error( 'invalid_profile_type', 'Unsupported family profile type.' );
            }
            if ( $row['profile_type'] === 'child' ) {
                $year = substr( (string) $row['profile_date'], 0, 4 );
                if ( ! preg_match( '/^(19|20)\\d{2}$/', $year ) ) {
                    return new WP_Error( 'birth_year_missing', 'Child birth year is missing.' );
                }
                $name = trim( (string) $row['display_name'] );
                if ( $name === '' ) { return new WP_Error( 'child_name_missing', 'Child name is missing.' ); }
                $attendees[] = array( 'profile_id' => $id, 'type' => 'child', 'name' => $name,
                    'gender' => (string) $row['gender'], 'year_of_birth' => (int) $year );
            } else {
                $attendees[] = array( 'profile_id' => $id, 'type' => 'bump', 'name' => 'Pregnancy', 'gender' => '', 'year_of_birth' => null );
            }
        }
        return $attendees;
    }
    /** Parent-only profile choices for the booking preview. */
    /**
     * Resolve a leader notification recipient without trusting a listing email.
     * Verification must be recorded by the account-verification workflow;
     * this method does not send email or expose the address to parents.
     */
    public static function verified_booking_leader_recipient( $organiser_id ) {
        $organiser_id = absint( $organiser_id );
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! $organiser_id ) {
            return new WP_Error( 'recipient_unavailable', 'Verified leader recipient is unavailable.' );
        }
        $users = get_users( array(
            'meta_key' => 'bh3_organiser_id',
            'meta_value' => (string) $organiser_id,
            'fields' => 'ID',
            'number' => 2,
        ) );
        if ( count( $users ) !== 1 ) {
            return new WP_Error( 'leader_link_unverified', 'A unique leader account is required.' );
        }
        $leader_id = (int) $users[0];
        $leader = get_userdata( $leader_id );
        if ( ! $leader || ! is_email( $leader->user_email ) ) {
            return new WP_Error( 'leader_email_missing', 'Leader account email is unavailable.' );
        }
        // Do not assume WordPress user_email alone proves mailbox ownership.
        // Only an explicit verification timestamp tied to this exact address
        // may authorise delivery of children's personal information.
        $verified_email = (string) get_user_meta( $leader_id, 'bh3_verified_booking_email', true );
        $verified_at = (string) get_user_meta( $leader_id, 'bh3_verified_booking_email_at', true );
        if ( ! hash_equals( strtolower( trim( $leader->user_email ) ), strtolower( trim( $verified_email ) ) )
            || ! $verified_at || ! strtotime( $verified_at ) ) {
            return new WP_Error( 'leader_email_unverified', 'Leader email must be verified before booking details can be sent.' );
        }
        return array( 'leader_user_id' => $leader_id, 'email' => $leader->user_email );
    }
    /**
     * Staging-only, administrator-only booking readiness preview.
     * Read-only: never reserves a place, sends email or initiates checkout.
     * Usage: [bh_booking_readiness session_id="123"]
     */
    public static function booking_readiness_preview( $atts ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! current_user_can( 'manage_options' ) ) {
            return '';
        }
        $atts = shortcode_atts( array( 'session_id' => 0, 'activity_id' => 0 ), $atts, 'bh_booking_readiness' );
        $activity_id = absint( $atts['activity_id'] );
        $session_id = absint( $atts['session_id'] );
        $sessions = array();
        $heading = 'Booking readiness — staging only';
        if ( $activity_id ) {
            global $wpdb;
            $activities_table = $wpdb->prefix . 'activities';
            $sessions_table = $wpdb->prefix . 'sessions';
            $activity = $wpdb->get_row( $wpdb->prepare(
                "SELECT id,status FROM `{$activities_table}` WHERE id=%d LIMIT 1", $activity_id
            ), ARRAY_A );
            if ( ! $activity ) {
                return '<p role="status">Activity ' . esc_html( $activity_id ) . ' was not found in the activities table.</p>';
            }
            $sessions = $wpdb->get_results( $wpdb->prepare(
                "SELECT id,status,day_of_week,start_date,end_date,frequency FROM `{$sessions_table}` WHERE activity_id=%d ORDER BY id ASC LIMIT 100",
                $activity_id
            ), ARRAY_A );
            $sessions = is_array( $sessions ) ? $sessions : array();
            $heading = 'Booking readiness — activity ' . $activity_id;
        } elseif ( $session_id ) {
            $sessions[] = array( 'id' => $session_id );
        } else {
            return '<p>Provide an activity or session ID, for example [bh_booking_readiness activity_id="2"].</p>';
        }
        $html = '<section class="bh3-booking-readiness"><h3>' . esc_html( $heading ) . '</h3>';
        if ( $activity_id ) {
            $html .= '<p>Activity status: <strong>' . esc_html( $activity['status'] ) . '</strong>. Found ' .
                esc_html( count( $sessions ) ) . ' session(s)' . ( count( $sessions ) === 100 ? ' (first 100 shown)' : '' ) . '.</p>';
        }
        if ( ! $sessions ) {
            $html .= '<p role="status">No sessions are linked to this activity. Add a session in the activity editor before testing bookings.</p>';
        }
        foreach ( $sessions as $session ) {
            $id = absint( $session['id'] );
            $quote = self::quote_session( $id, 1 );
            $items = array( array( 'Session ID', (string) $id ) );
            if ( isset( $session['status'] ) ) {
                $items[] = array( 'Session status', (string) $session['status'] );
                $items[] = array( 'Frequency', (string) $session['frequency'] );
                $items[] = array( 'Start date', (string) $session['start_date'] );
                $items[] = array( 'End date', (string) $session['end_date'] );
            }
            // Show independent blockers even if the quote fails on a draft activity.
            global $wpdb;
            $session_record = $wpdb->get_row( $wpdb->prepare(
                "SELECT s.status,s.frequency,s.day_of_week,s.start_date,s.end_date,s.term_time_only,a.status AS activity_status,a.organiser_id
                 FROM `{$wpdb->prefix}sessions` s
                 INNER JOIN `{$wpdb->prefix}activities` a ON a.id=s.activity_id
                 WHERE s.id=%d LIMIT 1", $id
            ), ARRAY_A );
            if ( $session_record ) {
                $items[] = array( 'Activity publication', $session_record['activity_status'] === 'published'
                    ? 'Published' : 'Blocked: activity is ' . $session_record['activity_status'] );
                $items[] = array( 'Timetable', $session_record['status'] !== 'scheduled'
                    ? 'Blocked: session is not scheduled'
                    : ( ! in_array( $session_record['frequency'], array( 'weekly', 'once', 'single' ), true )
                        ? 'Review: frequency is not supported for paid booking'
                        : ( $session_record['frequency'] === 'weekly'
                            && (int) $session_record['day_of_week'] < 1
                            ? 'Review: weekday is missing'
                            : ( in_array( $session_record['frequency'], array( 'once', 'single' ), true )
                                && empty( $session_record['start_date'] )
                                ? 'Review: one-off session date is missing' : 'Schedule rule present' ) ) ) );
                if ( ! empty( $session_record['term_time_only'] ) ) {
                    $ranges = get_option( 'bh3_term_date_ranges', array() );
                    $items[] = array( 'Term dates', is_array( $ranges ) && $ranges
                        ? 'Configured; individual dates still require validation' : 'Blocked: no term-date ranges configured' );
                }
                $configs = get_option( 'bh3_session_booking_config', array() );
                $config = is_array( $configs ) && isset( $configs[$id] ) && is_array( $configs[$id] )
                    ? $configs[$id] : array();
                $items[] = array( 'Booking enabled', ! empty( $config['enabled'] ) ? 'Yes' : 'No — enable in session booking settings' );
                $items[] = array( 'Configured price', isset( $config['price'] ) && is_scalar( $config['price'] )
                    ? '£' . (string) $config['price'] : 'Not configured' );
                $items[] = array( 'Configured capacity', ! empty( $config['capacity'] )
                    ? absint( $config['capacity'] ) . ' places' : 'Not configured' );
                $recipient = self::verified_booking_leader_recipient( $session_record['organiser_id'] );
                $items[] = array( 'Verified leader email', is_wp_error( $recipient )
                    ? 'Not ready: ' . $recipient->get_error_message() : 'Verified (address hidden)' );
            } else {
                $items[] = array( 'Linked session', 'Not found in a linked activity' );
            }
            $items[] = array( 'Session booking quote', is_wp_error( $quote )
                ? 'Not ready: ' . $quote->get_error_message()
                : 'Ready: £' . number_format( $quote['unit_price_pence'] / 100, 2 ) . ' per place' );
            if ( ! is_wp_error( $quote ) ) {
                $items[] = array( 'Capacity configured', ! empty( $quote['capacity'] ) ? 'Yes (' . absint( $quote['capacity'] ) . ' places)' : 'No' );
                $items[] = array( 'Commission', number_format( self::rate(), 2 ) . '%' );
            }
            $html .= '<h4>Session ' . esc_html( $id ) . '</h4><dl>';
            foreach ( $items as $item ) {
                $html .= '<dt><strong>' . esc_html( $item[0] ) . '</strong></dt><dd>' . esc_html( $item[1] ) . '</dd>';
            }
            $html .= '</dl>';
        }
        $names = class_exists( 'BH_Schema_3' ) ? BH_Schema_3::booking_structure_audit() : array();
        $ready = ! empty( $names );
        foreach ( $names as $item ) {
            if ( ! $item['exists'] || $item['missing_columns'] || $item['missing_indexes'] ) {
                $ready = false;
                break;
            }
        }
        $html .= '<p><strong>Booking database:</strong> ' . esc_html( $ready
            ? 'Expected columns and indexes present' : 'Database structure needs review' ) . '</p>';
        return $html . '<p>Read-only preview. No booking has been made and payments remain disabled.</p></section>';
    }

    /**
     * Validate native parent input without storing data or starting Checkout.
     * Future handlers must independently verify the session, capacity, nonce,
     * booking context and Stripe readiness before reserving any places.
     */
    public static function validate_native_parent_booking_details( $input, $user_id ) {
        $user_id = absint( $user_id );
        if ( ! is_array( $input ) || ! is_user_logged_in() || $user_id !== get_current_user_id() ) {
            return new WP_Error( 'native_booking_auth_required', 'Please sign in to book.' );
        }
        $account = get_userdata( $user_id );
        $name = $input['parent_name'] ?? null;
        $email = $input['parent_email'] ?? null;
        if ( ! is_string( $name ) || trim( $name ) === '' || strlen( $name ) > 200
            || ! is_string( $email ) || ! is_email( $email ) || ! $account
            || ! is_email( $account->user_email )
            || ! hash_equals( strtolower( trim( $account->user_email ) ), strtolower( trim( $email ) ) ) ) {
            return new WP_Error( 'native_booking_contact_invalid', 'Use your name and account email address.' );
        }
        if ( ! self::is_affirmative_booking_consent( $input['booking_terms_consent'] ?? null )
            || ! self::is_affirmative_booking_consent( $input['share_child_details_consent'] ?? null ) ) {
            return new WP_Error( 'native_booking_consent_missing', 'Both booking consents are required.' );
        }
        $profile_ids = $input['family_profile_ids'] ?? null;
        if ( ! is_array( $profile_ids ) || count( $profile_ids ) < 1 || count( $profile_ids ) > 20 ) {
            return new WP_Error( 'native_booking_attendees_missing', 'Select the family members attending.' );
        }
        $attendees = self::inspect_booking_attendees( $profile_ids, $user_id, count( $profile_ids ) );
        if ( is_wp_error( $attendees ) ) { return $attendees; }
        return array(
            'parent_name' => sanitize_text_field( $name ),
            'parent_email' => sanitize_email( $email ),
            'attendees' => $attendees,
            'attendee_count' => count( $attendees ),
            'terms_consent_verified' => true,
            'sharing_consent_verified' => true,
            'checkout_authorised' => false,
        );
    }

    /** Read-only staging endpoint for pre-testing native form validation. */
    public static function register_native_booking_preflight_route() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_PREVIEW_NATIVE_BOOKING_FORM' ) || BH3_PREVIEW_NATIVE_BOOKING_FORM !== true ) { return; }
        register_rest_route( 'bubba-hub/v3', '/native-booking-preflight', array(
            'methods' => 'POST',
            'permission_callback' => function ( $request ) {
                return is_user_logged_in() && wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
            },
            'callback' => array( __CLASS__, 'native_booking_preflight' ),
        ) );
    }
    public static function native_booking_preflight( $request ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_PREVIEW_NATIVE_BOOKING_FORM' ) || BH3_PREVIEW_NATIVE_BOOKING_FORM !== true
            || ! is_user_logged_in() || ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_Error( 'native_preflight_disabled', 'Native booking preflight is unavailable.', array( 'status' => 403 ) );
        }
        $data = $request->get_json_params();
        $checked = self::validate_native_parent_booking_details( $data, get_current_user_id() );
        if ( is_wp_error( $checked ) ) {
            return new WP_Error( $checked->get_error_code(), $checked->get_error_message(), array( 'status' => 400 ) );
        }
        return rest_ensure_response( array(
            'valid' => true, 'attendee_count' => $checked['attendee_count'],
            'checkout_authorised' => false, 'reservation_created' => false,
            'payment_started' => false,
        ) );
    }

    public static function booking_profile_choices( $customer_user_id ) {
        $customer_user_id = absint( $customer_user_id );
        if ( ! is_user_logged_in() || $customer_user_id !== get_current_user_id() ) { return array(); }
        global $wpdb;
        $table = $wpdb->prefix . 'bh_family_profiles';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return array();
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,profile_type,display_name FROM `{$table}` WHERE user_id=%d AND profile_type IN ('child','bump') ORDER BY id DESC LIMIT 50",
            $customer_user_id
        ), ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }
    /** Administrator-only staging links to configured, quote-valid sessions. */
    public static function staging_booking_test_links() {
        if ( ! current_user_can( 'manage_options' )
            || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return '';
        }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return '<p>Sessions table is missing on staging.</p>';
        }
        $configs = get_option( 'bh3_session_booking_config', array() );
        if ( ! is_array( $configs ) ) { $configs = array(); }
        $links = array();
        foreach ( $configs as $id => $config ) {
            if ( count( $links ) >= 15 ) { break; }
            if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || empty( $config['enabled'] ) ) { continue; }
            $quote = self::quote_session( (int) $id, 1 );
            if ( is_wp_error( $quote ) ) { continue; }
            $links[] = '<li><a href="' . esc_url( add_query_arg( 'session_id', (int) $id, home_url( '/checkout/' ) ) ) . '">Session #' . absint( $id ) . ' — preview checkout</a></li>';
        }
        if ( ! $links ) {
            return '<p>No quote-valid paid sessions found. Configure a scheduled session with a published activity, enabled booking and a positive price before testing checkout.</p>';
        }
        return '<p>Administrator-only staging test links. These do not reserve places or initiate payments.</p><ul>' . implode( '', $links ) . '</ul>';
    }

    public static function parent_booking_widget( $atts ) {
        $atts = shortcode_atts( array( 'session_id' => 0 ), $atts, 'bh_parent_booking' );
        $session_id = absint( $atts['session_id'] );
        // A shared /checkout/ page can receive a session ID from the Book Now link.
        // The server-side quote still validates session status, price and capacity.
        if ( ! $session_id && isset( $_GET['session_id'] ) && is_scalar( $_GET['session_id'] ) ) {
            $requested_session = wp_unslash( $_GET['session_id'] );
            if ( is_string( $requested_session ) && preg_match( '/^[1-9][0-9]{0,9}$/', $requested_session ) ) {
                $session_id = absint( $requested_session );
            }
        }
        if ( ! $session_id ) {
            return '<p>Select a session on an activity page to see booking options.</p>' . self::staging_booking_test_links();
        }
        $quote = self::quote_session( $session_id, 1 );
        if ( is_wp_error( $quote ) ) {
            return '<p role="status">' . esc_html( $quote->get_error_message() ) . '</p>';
        }
        $stripe_readiness = self::stripe_status();
        $enabled = is_user_logged_in()
            && defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) && BH3_ENABLE_BOOKING_FORM_BINDING === true
            && defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) && BH3_ENABLE_STRIPE_TEST_CHECKOUT === true
            && defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) && BH3_ENABLE_STRIPE_TEST_WEBHOOK === true
            && defined( 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION' ) && BH3_ENABLE_TEST_BOOKING_CONFIRMATION === true
            && defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) && BH3_ENABLE_BOOKING_RESERVATIONS === true
            && $stripe_readiness['mode'] === 'test' && $stripe_readiness['keys_configured']
            && self::policy()['processing_fees'] === 'platform';
        $id = 'bh3-book-' . wp_unique_id();
        ob_start();
        echo '<section id="' . esc_attr( $id ) . '" class="bh3-parent-booking" aria-label="Class booking">';
        echo '<h3>Book a class</h3>';
        $fee_quote = $quote['parent_paid_fee_quote'] ?? null;
        if ( is_array( $fee_quote ) && ! empty( $fee_quote['preview_only'] ) ) {
            $price_quotes = array();
            for ( $place_count = 1; $place_count <= 20; $place_count++ ) {
                $calculated = self::parent_paid_fee_preview( (int) $quote['unit_price_pence'] * $place_count );
                if ( ! is_wp_error( $calculated ) ) {
                    $price_quotes[ $place_count ] = array(
                        'class' => (int) $calculated['leader_price_pence'],
                        'fee' => (int) $calculated['booking_fee_pence'],
                        'total' => (int) $calculated['parent_total_pence'],
                    );
                }
            }
            echo '<div class="bh3-booking-fee-preview" aria-label="Estimated booking total" data-price-quotes="' . esc_attr( wp_json_encode( $price_quotes ) ) . '">';
            echo '<p><strong>Class price:</strong> £<span data-bh-price-class>' . esc_html( number_format( $fee_quote['leader_price_pence'] / 100, 2 ) ) . '</span></p>';
            echo '<p><strong>Bubba Hub Booking Fee:</strong> £<span data-bh-price-fee>' . esc_html( number_format( $fee_quote['booking_fee_pence'] / 100, 2 ) ) . '</span></p>';
            echo '<p><strong>Estimated total to pay:</strong> £<span data-bh-price-total>' . esc_html( number_format( $fee_quote['parent_total_pence'] / 100, 2 ) ) . '</span></p>';
            echo '<p><small>One combined fee covers the Bubba Hub platform fee and estimated card-processing costs. The class leader receives the advertised class price. Final amounts are confirmed at Stripe Checkout.</small></p>';
            echo '</div>';
        } else {
            echo '<p><strong>Price per place:</strong> £' . esc_html( number_format( $quote['unit_price_pence'] / 100, 2 ) ) . '</p>';
        }
        if ( is_user_logged_in() ) {
            $profiles = self::booking_profile_choices( get_current_user_id() );
            echo '<div class="bh3-booking-attendees"><h4>Who is attending?</h4>';
            echo '<p>Select the children attending, or choose a pregnancy profile for an antenatal class.</p>';
            if ( $profiles ) {
                echo '<p>Your saved child and pregnancy profiles will appear as selectable options in the booking form below.</p>';
            } else {
                echo '<p>No child or pregnancy profiles found. <a href="' . esc_url( home_url( '/family/children/' ) ) . '">Add a family profile in Family Hub</a> before booking.</p>';
            }
            echo '<p><a href="' . esc_url( home_url( '/family/children/' ) ) . '">Add or edit a child in Family Hub</a></p></div>';
        }
        if ( ! is_user_logged_in() ) {
            // Guest details are local form inputs only. No guest checkout,
            // reservation, or persistent storage is enabled by this preview.
            echo '<div class="bh3-guest-attendees"><h4>Who is attending?</h4>';
            echo '<p>You can enter details without creating an account. Guest booking is coming soon.</p>';
            echo '<fieldset><legend>Attendee details</legend>';
            echo '<p><label>Booking for <select name="bh3_guest_profile_type" disabled><option value="child">Child</option><option value="bump">Pregnancy / bump</option></select></label></p>';
            echo '<p><label>Child name <input type="text" maxlength="100" autocomplete="off" disabled></label></p>';
            echo '<p><label>Gender (optional) <select disabled><option value="">Prefer not to say</option><option>Girl</option><option>Boy</option><option>Another identity</option></select></label></p>';
            echo '<p><label>Year of birth <input type="number" min="1900" max="' . esc_attr( wp_date( 'Y' ) ) . '" disabled></label></p>';
            echo '</fieldset><p><small>Preview only — these fields cannot be submitted yet. Guest consent and booking verification are being built.</small></p></div>';
        }
        // Staging-only preflight form. It validates only; it never reserves or charges.
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk'
            && defined( 'BH3_PREVIEW_NATIVE_BOOKING_FORM' ) && BH3_PREVIEW_NATIVE_BOOKING_FORM === true ) {
            echo '<div class="bh3-native-booking-preview" aria-label="Native booking preflight">';
            echo '<h4>Native booking — pre-test</h4>';
            echo '<p>This checks your details only. No reservation or payment will be made.</p>';
            if ( is_user_logged_in() ) {
                $account = wp_get_current_user();
                echo '<form class="bh3-native-preflight-form">';
                echo '<p><label>Parent or guardian name <input name="parent_name" type="text" maxlength="200" autocomplete="name" required></label></p>';
                echo '<p><label>Account email <input name="parent_email" type="email" autocomplete="email" value="' . esc_attr( $account->user_email ) . '" readonly required></label></p>';
                echo '<fieldset><legend>Who is attending?</legend>';
                foreach ( self::booking_profile_choices( get_current_user_id() ) as $profile ) {
                    echo '<p><label><input name="family_profile_ids[]" type="checkbox" value="' . esc_attr( $profile['id'] ) . '"> ' . esc_html( $profile['display_name'] ) . ' (' . esc_html( $profile['profile_type'] === 'bump' ? 'Pregnancy' : 'Child' ) . ')</label></p>';
                }
                echo '</fieldset>';
                echo '<p><label><input name="share_child_details_consent" type="checkbox" value="yes" required> I agree to share the selected attendee details with the class leader for this booking.</label></p>';
                echo '<p><label><input name="booking_terms_consent" type="checkbox" value="yes" required> I agree to the <a href="' . esc_url( home_url( '/legal/bookings/' ) ) . '">Booking Terms and Conditions</a>.</label></p>';
                echo '<p><button type="submit">Validate details (no payment)</button></p>';
                echo '<p class="bh3-native-preflight-result" role="status" aria-live="polite"></p></form>';
                echo '<script>(function(){const root=document.getElementById(' . wp_json_encode( $id ) . ');if(!root)return;const form=root.querySelector(".bh3-native-preflight-form");if(!form)return;const result=form.querySelector(".bh3-native-preflight-result");form.addEventListener("submit",async function(event){event.preventDefault();const button=form.querySelector("button[type=submit]");button.disabled=true;result.textContent="Validating…";const data=new FormData(form);const body={parent_name:data.get("parent_name"),parent_email:data.get("parent_email"),family_profile_ids:data.getAll("family_profile_ids[]"),share_child_details_consent:data.get("share_child_details_consent"),booking_terms_consent:data.get("booking_terms_consent")};try{const response=await fetch(' . wp_json_encode( rest_url( 'bubba-hub/v3/native-booking-preflight' ) ) . ',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . '},body:JSON.stringify(body)});const payload=await response.json();result.textContent=response.ok&&payload.valid?"Details validated for "+payload.attendee_count+" attendee(s). No booking or payment has been made.":(payload.message||"Validation could not be completed.");}catch(error){result.textContent="Unable to validate details. Please try again.";}finally{button.disabled=false;}});})();</script>';
            } else {
                echo '<p>Please sign in to pre-test the booking form.</p>';
            }
            echo '</div>';
        }
        // Do not display a submittable Form 7 when booking is disabled.
        // A submission without a configured checkout would strand the parent.
        $form_id = absint( get_option( 'bh3_booking_form_id', 8 ) );
        if ( $enabled && $form_id && shortcode_exists( 'fluentform' ) ) {
            echo '<div class="bh3-booking-details"><h4>Booking details</h4>';
            echo '<p>Please read our <a href="' . esc_url( home_url( '/legal/bookings/' ) ) . '" target="_blank" rel="noopener">Booking Terms &amp; Conditions</a> before continuing. Your booking is confirmed only after Stripe verifies payment.</p>';
            $previous_session_context = $GLOBALS['bh3_form7_session_id'] ?? null;
            $GLOBALS['bh3_form7_session_id'] = $session_id;
            echo do_shortcode( '[fluentform id="' . $form_id . '"]' );
            echo '<script>(function(){const root=document.getElementById(' . wp_json_encode( $id ) . ');if(!root)return;const box=root.querySelector(".bh3-booking-fee-preview"),field=root.querySelector("[name=places]");if(!box||!field)return;let quotes={};try{quotes=JSON.parse(box.dataset.priceQuotes||"{}");}catch(e){return;}const update=function(){const count=String(Math.max(1,Math.min(20,parseInt(field.value||"1",10)||1)));const q=quotes[count];if(!q)return;box.querySelector("[data-bh-price-class]").textContent=(q.class/100).toFixed(2);box.querySelector("[data-bh-price-fee]").textContent=(q.fee/100).toFixed(2);box.querySelector("[data-bh-price-total]").textContent=(q.total/100).toFixed(2);};field.addEventListener("input",update);field.addEventListener("change",update);update();})();</script>';
            if ( $previous_session_context === null ) { unset( $GLOBALS['bh3_form7_session_id'] ); }
            else { $GLOBALS['bh3_form7_session_id'] = $previous_session_context; }
            echo '<p class="bh3-booking-note">Submitting your details creates a temporary place hold and opens Stripe test checkout. Your booking is confirmed only after payment is verified.</p></div>';
        } elseif ( $enabled ) {
            echo '<p role="status">Booking details form is not available. Please contact Bubba Hub before attempting payment.</p>';
        }
        if ( ! is_user_logged_in() ) {
            echo '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to book.</p>';
        } elseif ( ! $enabled ) {
            echo '<p role="status">Online checkout remains disabled until booking details are securely linked to the reservation. Booking preview only.</p>';
        } else {
            echo '<p role="status">Complete the booking details form above. Once your details and consent have been saved and verified, you will be taken to secure Stripe test checkout. No payment is taken until you confirm payment with Stripe.</p>';
        }
        echo '</section>';
        return ob_get_clean();
    }
    /**
     * One authenticated staging path from verified Form 7 submission to
     * capacity hold, attendee/consent binding and Stripe test Checkout.
     * A failed binding leaves only an expiring hold, never a payment.
     */
    public static function register_bound_parent_checkout_route() {
        if ( ! defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) || BH3_ENABLE_BOOKING_FORM_BINDING !== true ) { return; }
        register_rest_route( 'bubba-hub/v3', '/parent-bound-test-checkout', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'start_bound_parent_test_checkout' ),
            'permission_callback' => function () { return is_user_logged_in(); },
            'args' => array(
                'submission_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
                'session_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
                'occurrence_date' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' ),
                'places' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 20 ),
            ),
        ) );
    }
    public static function register_parent_booking_binding_route() {
        if ( ! defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) || BH3_ENABLE_BOOKING_FORM_BINDING !== true ) { return; }
        register_rest_route( 'bubba-hub/v3', '/parent-booking-bind', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receive_parent_booking_binding' ),
            'permission_callback' => function () { return is_user_logged_in(); },
            'args' => array(
                'booking_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
                'submission_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
            ),
        ) );
    }
    public static function start_bound_parent_test_checkout( $request ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) || BH3_ENABLE_BOOKING_FORM_BINDING !== true
            || ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) || BH3_ENABLE_STRIPE_TEST_CHECKOUT !== true
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) || BH3_ENABLE_STRIPE_TEST_WEBHOOK !== true
            || ! defined( 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION' ) || BH3_ENABLE_TEST_BOOKING_CONFIRMATION !== true
            || ! is_user_logged_in() ) {
            return new WP_Error( 'bound_checkout_disabled', 'Verified test checkout is unavailable.', array( 'status' => 404 ) );
        }
        $user_id = get_current_user_id();
        $submission_id = absint( $request->get_param( 'submission_id' ) );
        $session_id = absint( $request->get_param( 'session_id' ) );
        $date = (string) $request->get_param( 'occurrence_date' );
        $places = absint( $request->get_param( 'places' ) );
        if ( ! $submission_id || ! $session_id || $places < 1 || $places > 20 ) {
            return new WP_Error( 'invalid_booking_selection', 'Invalid booking details.', array( 'status' => 400 ) );
        }
        $checked = self::inspect_booking_form_attendees( $submission_id, $user_id, $session_id, $date, $places );
        if ( is_wp_error( $checked ) ) { return $checked; }
        global $wpdb;
        $submission_lock = 'bh3_sub_' . md5( $wpdb->prefix . ':' . $submission_id );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $submission_lock ) ) !== 1 ) {
            return new WP_Error( 'submission_busy', 'This booking is already being prepared. Please wait.', array( 'status' => 409 ) );
        }
        try {
            // A submission-scoped lock prevents concurrent requests from making duplicate capacity holds.
            global $wpdb;
            $table = $wpdb->prefix . 'bh3_bookings';
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE booking_submission_id=%d LIMIT 1", $submission_id
            ) );
            if ( $existing ) {
                return new WP_Error( 'submission_already_booked', 'This form submission already belongs to a booking.', array( 'status' => 409 ) );
            }
            $config = self::stripe_status();
            if ( $config['mode'] !== 'test' || ! $config['keys_configured']
                || ! defined( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' )
                || ! is_string( constant( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' ) )
                || constant( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' ) === ''
                || self::policy()['processing_fees'] !== 'platform' ) {
                return new WP_Error( 'test_checkout_not_ready', 'Stripe test payment verification is not configured.', array( 'status' => 503 ) );
            }
            $reservation = self::reserve_occurrence( $session_id, $date, $places, $user_id );
            if ( is_wp_error( $reservation ) ) { return $reservation; }
            $bound = self::bind_parent_submission_to_booking( $reservation['booking_id'], $submission_id, $user_id );
            if ( is_wp_error( $bound ) ) {
                return new WP_Error( 'booking_details_not_saved', 'Booking details could not be linked. The temporary hold will expire.', array( 'status' => 409 ) );
            }
            $checkout = self::create_test_checkout( $reservation['booking_id'], $user_id );
            if ( is_wp_error( $checkout ) ) {
                return new WP_Error( 'test_checkout_not_started', 'Checkout could not be started. The temporary hold will expire.', array( 'status' => 503 ) );
            }
            return rest_ensure_response( array(
                'booking_reference' => $reservation['reference'],
                'checkout_url' => $checkout['checkout_url'],
                'test_mode' => true,
            ) );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $submission_lock ) );
        }
    }
    public static function receive_parent_booking_binding( $request ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_FORM_BINDING' ) || BH3_ENABLE_BOOKING_FORM_BINDING !== true
            || ! is_user_logged_in() ) {
            return new WP_Error( 'booking_binding_unavailable', 'Booking binding unavailable.', array( 'status' => 404 ) );
        }
        $result = self::bind_parent_submission_to_booking(
            absint( $request->get_param( 'booking_id' ) ),
            absint( $request->get_param( 'submission_id' ) ),
            get_current_user_id()
        );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }
        return rest_ensure_response( array(
            'booking_id' => $result['booking_id'],
            'attendee_count' => $result['attendee_count'],
            'details_saved' => true,
            'checkout_authorised' => false,
        ) );
    }
    public static function start_test_booking_checkout( $request ) {
        // Temporary QA-only bypass. Until Form 7 submission ownership, child
        // consent and selected-session binding are checked server-side, the
        // REST endpoint must enforce the same disabled-by-default guard as UI.
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_UNLINKED_BOOKING_TEST_UI' ) || BH3_ENABLE_UNLINKED_BOOKING_TEST_UI !== true
            || ! defined( 'BH3_ENABLE_PARENT_TEST_BOOKING' ) || BH3_ENABLE_PARENT_TEST_BOOKING !== true
            || ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) || BH3_ENABLE_STRIPE_TEST_CHECKOUT !== true ) {
            return new WP_Error( 'booking_disabled', 'Test booking is unavailable.', array( 'status' => 404 ) );
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'authentication_required', 'Please sign in.', array( 'status' => 401 ) );
        }
        $session_id = absint( $request->get_param( 'session_id' ) );
        $date = (string) $request->get_param( 'occurrence_date' );
        $places = absint( $request->get_param( 'places' ) );
        if ( $session_id < 1 || $places < 1 || $places > 20 || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $date ) ) {
            return new WP_Error( 'invalid_booking_input', 'Invalid booking selection.', array( 'status' => 400 ) );
        }
        // Do not create a capacity hold if Checkout is known to be unavailable.
        if ( self::stripe_status()['mode'] !== 'test' || ! self::stripe_status()['keys_configured']
            || self::policy()['processing_fees'] !== 'platform' ) {
            return new WP_Error( 'checkout_not_configured', 'Test checkout is not configured.', array( 'status' => 503 ) );
        }
        $reservation = self::reserve_occurrence( $session_id, $date, $places, $user_id );
        if ( is_wp_error( $reservation ) ) { return $reservation; }
        $checkout = self::create_test_checkout( $reservation['booking_id'], $user_id );
        if ( is_wp_error( $checkout ) ) {
            // Hold expires automatically; do not delete a reservation after an
            // uncertain Stripe response, as a payment may still be in flight.
            return new WP_Error( 'checkout_pending_review',
                'Checkout could not be started. Your temporary hold will expire automatically.',
                array( 'status' => 503 ) );
        }
        return rest_ensure_response( array(
            'booking_reference' => $reservation['reference'],
            'checkout_url' => $checkout['checkout_url'],
            'test_mode' => true,
        ) );
    }
    public static function register_test_webhook_route() {
        if ( ! defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) || BH3_ENABLE_STRIPE_TEST_WEBHOOK !== true ) { return; }
        register_rest_route( 'bubba-hub/v3', '/stripe-test-webhook', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receive_test_webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }
    public static function receive_test_webhook( $request ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) || BH3_ENABLE_STRIPE_TEST_WEBHOOK !== true ) {
            return new WP_Error( 'webhook_disabled', 'Webhook unavailable.', array( 'status' => 404 ) );
        }
        $event = self::verify_test_webhook( $request->get_body(), (string) $request->get_header( 'stripe-signature' ) );
        if ( is_wp_error( $event ) ) {
            return new WP_Error( 'signature_rejected', 'Invalid webhook signature or payload.', array( 'status' => 400 ) );
        }
        $result = self::record_verified_test_event( $event );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'event_persistence_failed', 'Event could not be recorded.', array( 'status' => 503 ) );
        }
        // Confirmation is a separate opt-in gate; ingestion remains safe by default.
        $confirmation_enabled = defined( 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION' )
            && BH3_ENABLE_TEST_BOOKING_CONFIRMATION === true;
        $payment_event = in_array( $event['type'] ?? '', array(
            'checkout.session.completed', 'checkout.session.async_payment_succeeded'
        ), true );
        if ( $confirmation_enabled && $payment_event ) {
            $confirmed = self::confirm_verified_test_payment( $event );
            if ( is_wp_error( $confirmed ) ) {
                // Stripe retries 503; preserve the event for investigation.
                return new WP_Error( 'payment_confirmation_pending',
                    'Payment confirmation requires reconciliation.', array( 'status' => 503 ) );
            }
            return rest_ensure_response( array(
                'received' => true,
                'duplicate' => $result['duplicate'],
                'booking_confirmed' => ! empty( $confirmed['confirmed'] ),
                'already_paid' => ! empty( $confirmed['already_paid'] ),
                'notifications' => $confirmed['notifications'] ?? array(),
            ) );
        }
        return rest_ensure_response( array(
            'received' => true,
            'duplicate' => $result['duplicate'],
            'booking_confirmed' => false,
        ) );
    }
    public static function verify_test_webhook( $raw_body, $signature_header ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' ) ) {
            return new WP_Error( 'webhook_disabled', 'Stripe test webhook is not configured.' );
        }
        $secret = constant( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' );
        if ( ! is_string( $secret ) || strpos( $secret, 'whsec_' ) !== 0
            || ! is_string( $raw_body ) || ! is_string( $signature_header )
            || strlen( $raw_body ) > 1048576 ) {
            return new WP_Error( 'invalid_webhook', 'Invalid webhook request.' );
        }
        $timestamp = null;
        $signatures = array();
        foreach ( explode( ',', $signature_header ) as $part ) {
            $pair = explode( '=', trim( $part ), 2 );
            if ( count( $pair ) !== 2 ) { continue; }
            if ( $pair[0] === 't' && ctype_digit( $pair[1] ) ) { $timestamp = (int) $pair[1]; }
            if ( $pair[0] === 'v1' && preg_match( '/^[a-f0-9]{64}$/i', $pair[1] ) ) {
                $signatures[] = strtolower( $pair[1] );
            }
        }
        if ( ! $timestamp || abs( time() - $timestamp ) > 300 || ! $signatures ) {
            return new WP_Error( 'webhook_signature_invalid', 'Invalid or expired Stripe signature.' );
        }
        $expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
        $matched = false;
        foreach ( $signatures as $candidate ) {
            $matched = hash_equals( $expected, $candidate ) || $matched;
        }
        if ( ! $matched ) {
            return new WP_Error( 'webhook_signature_invalid', 'Stripe signature verification failed.' );
        }
        $event = json_decode( $raw_body, true );
        if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] )
            || empty( $event['data']['object'] ) || empty( $event['livemode'] ) === false ) {
            return new WP_Error( 'invalid_stripe_event', 'Invalid Stripe test event.' );
        }
        return $event;
    }
    /**
     * Record a verified Stripe event exactly once. This is a ledger-only primitive;
     * it never confirms payment or changes capacity.
     */
    public static function record_verified_test_event( $event ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) || BH3_ENABLE_STRIPE_TEST_WEBHOOK !== true ) {
            return new WP_Error( 'webhook_disabled', 'Test webhook processing is disabled.' );
        }
        if ( ! is_array( $event ) || ! isset( $event['id'], $event['type'], $event['livemode'] )
            || $event['livemode'] !== false || ! is_string( $event['id'] )
            || ! preg_match( '/^evt_[A-Za-z0-9]+$/', $event['id'] )
            || ! is_string( $event['type'] ) || strlen( $event['type'] ) > 100 ) {
            return new WP_Error( 'invalid_event', 'Invalid Stripe test event.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_stripe_events';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return new WP_Error( 'event_table_missing', 'Stripe event storage is not installed.' );
        }
        $session = $event['data']['object']['id'] ?? null;
        $session = is_string( $session ) && preg_match( '/^cs_test_[A-Za-z0-9_]+$/', $session ) ? $session : null;
        $inserted = $wpdb->insert( $table, array(
            'stripe_event_id' => $event['id'],
            'event_type' => $event['type'],
            'checkout_session_id' => $session,
            'processing_status' => 'received',
            'received_at' => current_time( 'mysql', true ),
        ) );
        if ( $inserted === 1 ) {
            return array( 'event_id' => $event['id'], 'recorded' => true, 'duplicate' => false, 'booking_confirmed' => false );
        }
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT stripe_event_id,processing_status FROM `{$table}` WHERE stripe_event_id=%s LIMIT 1", $event['id']
        ), ARRAY_A );
        if ( $existing ) {
            return array( 'event_id' => $event['id'], 'recorded' => false, 'duplicate' => true,
                'processing_status' => $existing['processing_status'], 'booking_confirmed' => false );
        }
        return new WP_Error( 'event_record_failed', 'Could not record Stripe event.' );
    }
    /**
     * Read-only payment reconciliation preflight for a verified test event.
     * Never infer a paid booking from a redirect or unchecked client metadata.
     */
    public static function inspect_verified_checkout_payment( $event ) {
        if ( ! is_array( $event ) || ( $event['livemode'] ?? null ) !== false
            || ! in_array( $event['type'] ?? '', array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
            return new WP_Error( 'unsupported_payment_event', 'Not a successful test checkout event.' );
        }
        $object = $event['data']['object'] ?? array();
        $checkout_id = $object['id'] ?? '';
        if ( ! is_string( $checkout_id ) || ! preg_match( '/^cs_test_[A-Za-z0-9_]+$/', $checkout_id )
            || ( $object['payment_status'] ?? '' ) !== 'paid'
            || ( $object['mode'] ?? '' ) !== 'payment' ) {
            return new WP_Error( 'unpaid_checkout', 'Checkout has not confirmed payment.' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bh3_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT id,public_reference,payment_status,currency,gross_pence,commission_pence,stripe_checkout_session_id,stripe_payment_intent_id
             FROM `{$table}` WHERE stripe_checkout_session_id=%s LIMIT 1", $checkout_id
        ), ARRAY_A );
        $metadata = isset( $object['metadata'] ) && is_array( $object['metadata'] ) ? $object['metadata'] : array();
        $class_price = isset( $metadata['bh3_class_price_pence'] ) ? absint( $metadata['bh3_class_price_pence'] ) : 0;
        $booking_fee = isset( $metadata['bh3_booking_fee_pence'] ) ? absint( $metadata['bh3_booking_fee_pence'] ) : 0;
        $platform_component = isset( $metadata['bh3_platform_component_pence'] ) ? absint( $metadata['bh3_platform_component_pence'] ) : -1;
        $processing_estimate = isset( $metadata['bh3_estimated_processing_pence'] ) ? absint( $metadata['bh3_estimated_processing_pence'] ) : -1;
        $parent_total = isset( $object['amount_total'] ) && is_numeric( $object['amount_total'] ) ? (int) $object['amount_total'] : -1;
        if ( ! $booking || ! in_array( $booking['payment_status'], array( 'pending', 'paid' ), true )
            || strtolower( (string) ( $object['currency'] ?? '' ) ) !== $booking['currency']
            || $class_price !== (int) $booking['gross_pence']
            || $platform_component !== (int) $booking['commission_pence']
            || $booking_fee < 1 || $processing_estimate < 0
            || $booking_fee !== $platform_component + $processing_estimate
            || $parent_total !== $class_price + $booking_fee ) {
            return new WP_Error( 'payment_mismatch', 'Checkout does not match the saved class price and combined booking fee.' );
        }
        $intent = $object['payment_intent'] ?? '';
        if ( ! is_string( $intent ) || ! preg_match( '/^pi_[A-Za-z0-9]+$/', $intent )
            || ( ! empty( $booking['stripe_payment_intent_id'] ) && $booking['stripe_payment_intent_id'] !== $intent ) ) {
            return new WP_Error( 'payment_intent_mismatch', 'Payment intent is missing or mismatched.' );
        }
        return array(
            'booking_id' => (int) $booking['id'],
            'reference' => $booking['public_reference'],
            'checkout_session_id' => $checkout_id,
            'payment_intent_id' => $intent,
            'gross_pence' => (int) $booking['gross_pence'],
            'already_paid' => $booking['payment_status'] === 'paid',
            'payment_confirmed_in_database' => $booking['payment_status'] === 'paid',
            'safe_to_auto_confirm' => false,
        );
    }
    /**
     * Send one parent receipt and, only to a verified leader address, a minimal
     * attendee notification. Per-recipient markers make webhook retries safe.
     */
    public static function send_booking_confirmation_notifications( $booking_id ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return new WP_Error( 'notifications_disabled', 'Booking notifications are staging-only.' );
        }
        global $wpdb;
        $bookings = $wpdb->prefix . 'bh3_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$bookings}` WHERE id=%d AND payment_status='paid' LIMIT 1", absint( $booking_id )
        ), ARRAY_A );
        if ( ! $booking || empty( $booking['customer_user_id'] ) || empty( $booking['booking_submission_id'] ) ) {
            return new WP_Error( 'notification_booking_missing', 'Paid booking details are unavailable.' );
        }
        $parent = get_userdata( (int) $booking['customer_user_id'] );
        if ( ! $parent || ! is_email( $parent->user_email ) ) {
            return new WP_Error( 'notification_parent_email_missing', 'Parent account email is unavailable.' );
        }
        $activity_table = $wpdb->prefix . 'activities';
        $activity = $wpdb->get_var( $wpdb->prepare(
            "SELECT title FROM `{$activity_table}` WHERE id=%d LIMIT 1", (int) $booking['activity_id']
        ) );
        $title = is_string( $activity ) && $activity !== '' ? $activity : 'Your Bubba Hub class';
        $attendees_table = $wpdb->prefix . 'bh3_booking_attendees';
        $attendees = $wpdb->get_results( $wpdb->prepare(
            "SELECT attendee_type,attendee_name,gender,year_of_birth FROM `{$attendees_table}` WHERE booking_id=%d ORDER BY attendee_number ASC",
            (int) $booking['id']
        ), ARRAY_A );
        $attendee_lines = array();
        foreach ( (array) $attendees as $person ) {
            $line = ( $person['attendee_type'] === 'bump' ? 'Pregnancy profile' : (string) $person['attendee_name'] );
            if ( $person['attendee_type'] !== 'bump' ) {
                if ( ! empty( $person['gender'] ) ) { $line .= ' — ' . sanitize_text_field( $person['gender'] ); }
                if ( ! empty( $person['year_of_birth'] ) ) { $line .= ', born ' . (int) $person['year_of_birth']; }
            }
            $attendee_lines[] = $line;
        }
        $reference = (string) $booking['public_reference'];
        $date = (string) $booking['occurrence_date'];
        $amount = '£' . number_format( (int) $booking['gross_pence'] / 100, 2 );
        $parent_subject = 'Booking confirmed – ' . $title;
        $parent_message = "Your Bubba Hub booking is confirmed.\n\n"
            . "Class: {$title}\nDate: {$date}\nPlaces: " . (int) $booking['places']
            . "\nBooking reference: {$reference}\nClass price: {$amount}\n\n"
            . "You can view your family bookings in My Hub: " . home_url( '/my-hub/' )
            . "\nBooking terms: " . home_url( '/legal/bookings/' )
            . "\n\nThank you for booking with Bubba Hub.";
        $parent_sent = self::send_booking_confirmation_email_once(
            (int) $booking['id'], 'parent', $parent->user_email, $parent_subject, $parent_message
        );
        if ( is_wp_error( $parent_sent ) ) { return $parent_sent; }

        $leader_sent = false;
        $recipient = self::verified_booking_leader_recipient( (int) $booking['organiser_id'] );
        if ( ! is_wp_error( $recipient ) ) {
            $leader_subject = 'New paid booking – ' . $title;
            $leader_message = "A parent booking has been paid and confirmed.\n\n"
                . "Class: {$title}\nDate: {$date}\nPlaces: " . (int) $booking['places']
                . "\nBooking reference: {$reference}\nAttendees:\n- "
                . implode( "\n- ", $attendee_lines )
                . "\n\nView your bookings: " . home_url( '/leader/bookings/' )
                . "\n\nPlease handle attendee information in accordance with Bubba Hub's privacy and safeguarding requirements.";
            $leader_result = self::send_booking_confirmation_email_once(
                (int) $booking['id'], 'leader', $recipient['email'], $leader_subject, $leader_message
            );
            if ( is_wp_error( $leader_result ) ) { return $leader_result; }
            $leader_sent = true;
        }
        return array( 'parent_sent' => true, 'leader_sent' => $leader_sent,
            'leader_skipped_unverified' => is_wp_error( $recipient ) );
    }

    public static function send_booking_confirmation_email_once( $booking_id, $recipient_key, $to, $subject, $message ) {
        if ( ! in_array( $recipient_key, array( 'parent', 'leader' ), true ) || ! is_email( $to ) ) {
            return new WP_Error( 'notification_recipient_invalid', 'Booking notification recipient is invalid.' );
        }
        global $wpdb;
        $lock = 'bh3_email_' . md5( $wpdb->prefix . ':' . absint( $booking_id ) . ':' . $recipient_key );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            return new WP_Error( 'notification_busy', 'Booking confirmation email is being prepared.' );
        }
        try {
            $marker = 'bh3_booking_email_' . absint( $booking_id ) . '_' . $recipient_key . '_sent';
            if ( get_option( $marker, false ) ) { return true; }
            $sent = wp_mail( $to, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
            if ( ! $sent ) { return new WP_Error( 'notification_send_failed', 'Booking confirmation email could not be sent.' ); }
            update_option( $marker, current_time( 'mysql', true ), false );
            return true;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    /**
     * Explicitly gated staging payment transition. Caller MUST supply an event
     * already authenticated by verify_test_webhook(), not untrusted request JSON.
     * The webhook controller does not invoke this until end-to-end QA is approved.
     */
    public static function confirm_verified_test_payment( $event ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION' )
            || BH3_ENABLE_TEST_BOOKING_CONFIRMATION !== true ) {
            return new WP_Error( 'confirmation_disabled', 'Booking confirmation is disabled.' );
        }
        $preflight = self::inspect_verified_checkout_payment( $event );
        if ( is_wp_error( $preflight ) ) { return $preflight; }
        global $wpdb;
        $bookings = $wpdb->prefix . 'bh3_bookings';
        $events = $wpdb->prefix . 'bh3_stripe_events';
        $event_id = $event['id'] ?? '';
        if ( ! is_string( $event_id ) || ! preg_match( '/^evt_[A-Za-z0-9]+$/', $event_id ) ) {
            return new WP_Error( 'invalid_event', 'Invalid Stripe event ID.' );
        }
        // Share the booking-level lock with lifecycle transitions to prevent expiry/payment races.
        $lock = 'bh3_status_' . md5( $wpdb->prefix . ':' . $preflight['booking_id'] );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== 1 ) {
            return new WP_Error( 'payment_busy', 'Payment confirmation is busy.' );
        }
        try {
            // Recheck while holding the lock; the earlier preflight may be stale.
            $checked = self::inspect_verified_checkout_payment( $event );
            if ( is_wp_error( $checked ) ) { return $checked; }
            $record = $wpdb->get_row( $wpdb->prepare(
                "SELECT id,booking_status,payment_status,reservation_expires_at FROM `{$bookings}` WHERE id=%d LIMIT 1",
                $checked['booking_id']
            ), ARRAY_A );
            if ( ! $record ) { return new WP_Error( 'booking_missing', 'Booking not found.' ); }
            if ( $record['payment_status'] === 'paid' ) {
                $notifications = self::send_booking_confirmation_notifications( $checked['booking_id'] );
                if ( is_wp_error( $notifications ) ) { return $notifications; }
                return array( 'booking_id' => $checked['booking_id'], 'already_paid' => true,
                    'confirmed' => false, 'notifications' => $notifications );
            }
            if ( $record['booking_status'] !== 'reserved' ) {
                return new WP_Error( 'hold_not_active', 'Reservation is no longer active; manual payment review required.' );
            }
            // Expired holds require manual review to avoid overselling.
            if ( empty( $record['reservation_expires_at'] ) || strtotime( $record['reservation_expires_at'] . ' UTC' ) <= time() ) {
                return new WP_Error( 'hold_expired', 'Payment arrived after the booking hold expired; manual review required.' );
            }
            $ledger = $wpdb->get_row( $wpdb->prepare(
                "SELECT processing_status FROM `{$events}` WHERE stripe_event_id=%s LIMIT 1", $event_id
            ), ARRAY_A );
            if ( ! $ledger || $ledger['processing_status'] !== 'received' ) {
                return new WP_Error( 'event_not_ready', 'Verified event is not ready for processing.' );
            }
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE `{$bookings}` SET booking_status='confirmed', payment_status='paid', stripe_payment_intent_id=%s, updated_at=%s
                 WHERE id=%d AND booking_status='reserved' AND payment_status='pending' AND reservation_expires_at > %s",
                $checked['payment_intent_id'], current_time( 'mysql', true ),
                $checked['booking_id'], current_time( 'mysql', true )
            ) );
            if ( $updated !== 1 ) {
                return new WP_Error( 'confirmation_race', 'Booking status changed; review payment.' );
            }
            $wpdb->update( $events, array( 'processing_status' => 'processed',
                'processed_at' => current_time( 'mysql', true ) ),
                array( 'stripe_event_id' => $event_id, 'processing_status' => 'received' ) );
            $notifications = self::send_booking_confirmation_notifications( $checked['booking_id'] );
            if ( is_wp_error( $notifications ) ) { return $notifications; }
            return array( 'booking_id' => $checked['booking_id'], 'already_paid' => false,
                'confirmed' => true, 'notifications' => $notifications );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }
    public static function stripe_status() {
        $mode = get_option( 'bh3_stripe_mode', 'test' );
        $mode = $mode === 'live' ? 'live' : 'test';
        $secret_constant = $mode === 'live' ? 'BH3_STRIPE_LIVE_SECRET_KEY' : 'BH3_STRIPE_TEST_SECRET_KEY';
        $publishable_constant = $mode === 'live' ? 'BH3_STRIPE_LIVE_PUBLISHABLE_KEY' : 'BH3_STRIPE_TEST_PUBLISHABLE_KEY';
        $secret = defined( $secret_constant ) ? constant( $secret_constant ) : '';
        $public = defined( $publishable_constant ) ? constant( $publishable_constant ) : '';
        $prefix = $mode === 'live' ? 'live' : 'test';
        $valid = is_string( $secret ) && strpos( $secret, 'sk_' . $prefix . '_' ) === 0
            && is_string( $public ) && strpos( $public, 'pk_' . $prefix . '_' ) === 0;
        return array( 'mode' => $mode, 'keys_configured' => $valid, 'connection_verified' => false );
    }
    public static function verify_stripe() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_verify_stripe' );
        $status = self::stripe_status();
        $constant = $status['mode'] === 'live' ? 'BH3_STRIPE_LIVE_SECRET_KEY' : 'BH3_STRIPE_TEST_SECRET_KEY';
        $result = 'missing';
        if ( $status['keys_configured'] && defined( $constant ) ) {
            $response = wp_remote_get( 'https://api.stripe.com/v1/account', array(
                'timeout' => 12,
                'redirection' => 0,
                'headers' => array( 'Authorization' => 'Bearer ' . constant( $constant ) ),
            ) );
            if ( is_wp_error( $response ) ) {
                $result = 'network_error';
            } elseif ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                $payload = json_decode( wp_remote_retrieve_body( $response ), true );
                $result = is_array( $payload ) && ! empty( $payload['id'] ) && strpos( $payload['id'], 'acct_' ) === 0
                    && ( ! isset( $payload['livemode'] ) || (bool) $payload['livemode'] === ( $status['mode'] === 'live' ) )
                    ? 'verified' : 'unexpected_response';
            } else {
                $result = 'rejected';
            }
        }
        // Persist only verification metadata, never Stripe keys or response payloads.
        update_option( 'bh3_stripe_verification', array(
            'mode' => $status['mode'], 'result' => $result, 'checked_at' => time(),
        ), false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-marketplace&stripe_check=' . rawurlencode( $result ) ) ); exit;
    }
    public static function stripe_onboard() {
        if ( ! is_user_logged_in() || ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) {
            wp_die( 'Linked leader account required.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_stripe_onboard' );
        $status = self::stripe_status();
        if ( $status['mode'] !== 'test' || ! $status['keys_configured']
            || ! defined( 'BH3_ENABLE_STRIPE_TEST_ONBOARDING' ) || BH3_ENABLE_STRIPE_TEST_ONBOARDING !== true ) {
            wp_die( 'Stripe test onboarding disabled.', '', array( 'response' => 403 ) );
        }
        $key = constant( 'BH3_STRIPE_TEST_SECRET_KEY' );
        $user_id = get_current_user_id();
        $account = get_user_meta( $user_id, 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            $created = wp_remote_post( 'https://api.stripe.com/v1/accounts', array(
                'timeout' => 20, 'redirection' => 0,
                'headers' => array( 'Authorization' => 'Bearer ' . $key ),
                'body' => array( 'type' => 'express', 'country' => 'GB', 'capabilities[transfers][requested]' => 'true', 'metadata[wp_user_id]' => (string) $user_id ),
            ) );
            $data = ! is_wp_error( $created ) && wp_remote_retrieve_response_code( $created ) === 200 ? json_decode( wp_remote_retrieve_body( $created ), true ) : null;
            if ( ! is_array( $data ) || empty( $data['id'] ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $data['id'] ) ) {
                wp_die( 'Stripe test account creation failed.', '', array( 'response' => 502 ) );
            }
            $account = $data['id'];
            update_user_meta( $user_id, 'bh3_stripe_test_account_id', $account );
        }
        $return = home_url( '/leader/account/' );
        $response = wp_remote_post( 'https://api.stripe.com/v1/account_links', array(
            'timeout' => 20, 'redirection' => 0,
            'headers' => array( 'Authorization' => 'Bearer ' . $key ),
            'body' => array(
                'account' => $account, 'type' => 'account_onboarding',
                'refresh_url' => add_query_arg( 'stripe_return', 'refresh', $return ),
                'return_url' => add_query_arg( 'stripe_return', 'complete', $return ),
            ),
        ) );
        $data = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
        if ( ! is_array( $data ) || empty( $data['url'] ) || strpos( $data['url'], 'https://connect.stripe.com/' ) !== 0 ) {
            wp_die( 'Stripe test onboarding link failed.', '', array( 'response' => 502 ) );
        }
        wp_redirect( esc_url_raw( $data['url'] ), 303 ); exit;
    }
    public static function leader_stripe_status( $user_id ) {
        $account = get_user_meta( absint( $user_id ), 'bh3_stripe_test_account_id', true );
        if ( ! is_string( $account ) || ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ) {
            return array( 'state' => 'not_connected', 'label' => 'Not connected' );
        }
        $config = self::stripe_status();
        if ( $config['mode'] !== 'test' || ! $config['keys_configured'] ) {
            return array( 'state' => 'unavailable', 'label' => 'Test connection unavailable' );
        }
        $key = constant( 'BH3_STRIPE_TEST_SECRET_KEY' );
        $response = wp_remote_get( 'https://api.stripe.com/v1/accounts/' . rawurlencode( $account ), array(
            'timeout' => 12, 'redirection' => 0,
            'headers' => array( 'Authorization' => 'Bearer ' . $key ),
        ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return array( 'state' => 'unavailable', 'label' => 'Unable to verify with Stripe' );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || ( $data['id'] ?? '' ) !== $account || ! empty( $data['livemode'] ) ) {
            return array( 'state' => 'unavailable', 'label' => 'Unexpected Stripe account response' );
        }
        if ( ! empty( $data['charges_enabled'] ) && ! empty( $data['payouts_enabled'] ) && ! empty( $data['details_submitted'] ) ) {
            return array( 'state' => 'ready_test', 'label' => 'Test account ready (no live payments)' );
        }
        if ( ! empty( $data['requirements']['disabled_reason'] ) ) {
            return array( 'state' => 'restricted', 'label' => 'Action required in Stripe' );
        }
        return array( 'state' => 'pending', 'label' => 'Onboarding incomplete or under review' );
    }
    public static function menu() {
        // Registered by BH_Manager_3 after the parent admin menu.
        // Booking QA menu is registered by BH_Manager_3 after the parent menu.
    }
    /**
     * Staging-only administrator QA: uses the normal reservation path, not a
     * direct INSERT, to preserve capacity, occurrence and price safeguards.
     * No test payment is fabricated and no customer details are collected.
     */
    public static function test_booking_page() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { wp_die( 'Staging only.', '', array( 'response'=>403 ) ); }
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response'=>403 ) ); }
        echo '<div class="wrap"><h1>Booking test generator</h1>';
        echo '<p>Staging QA only. Creates one unpaid 35-minute reservation through the normal capacity-checked reservation service. The current administrator account is used as the required customer reference; no new account or customer/child details are created. No payment is taken or marked paid.</p>';
        if ( ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true ) {
            echo '<div class="notice notice-warning"><p>Reservations are disabled. Enable BH3_ENABLE_BOOKING_RESERVATIONS in staging wp-config.php to use this tool.</p></div>';
        }
        if ( isset( $_GET['bh3_test_booking_id'] ) ) {
            echo '<div class="notice notice-success"><p>Test reservation created: #' . esc_html( (string) absint( $_GET['bh3_test_booking_id'] ) ) . '. View it in the matching leader calendar session date.</p></div>';
        }
        if ( isset( $_GET['bh3_test_booking_error'] ) ) {
            echo '<div class="notice notice-error"><p>Reservation could not be created: ' . esc_html( sanitize_key( wp_unslash( $_GET['bh3_test_booking_error'] ) ) ) . '</p></div>';
        }
        $stripe_ready = self::stripe_status();
        $checks = array(
            'Stripe mode is test' => $stripe_ready['mode'] === 'test',
            'Stripe test keys configured' => $stripe_ready['keys_configured'],
            'Test checkout enabled' => defined( 'BH3_ENABLE_STRIPE_TEST_CHECKOUT' ) && BH3_ENABLE_STRIPE_TEST_CHECKOUT === true,
            'Test webhook enabled' => defined( 'BH3_ENABLE_STRIPE_TEST_WEBHOOK' ) && BH3_ENABLE_STRIPE_TEST_WEBHOOK === true,
            'Test-payment confirmation enabled' => defined( 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION' ) && BH3_ENABLE_TEST_BOOKING_CONFIRMATION === true,
            'Destination charge fee policy supported' => self::policy()['processing_fees'] === 'platform',
        );
        global $submenu, $_registered_pages;
        $marketplace_hook = get_plugin_page_hookname( 'bh3-marketplace', 'bubba-hub-3-status' );
        $submenu_found = false;
        foreach ( (array) ( $submenu['bubba-hub-3-status'] ?? array() ) as $entry ) {
            if ( isset( $entry[2] ) && $entry[2] === 'bh3-marketplace' ) { $submenu_found = true; break; }
        }
        echo '<h2>Marketplace admin access diagnostics</h2><ul>';
        echo '<li>Administrator capability: ' . esc_html( current_user_can( 'manage_options' ) ? 'PASS' : 'FAIL' ) . '</li>';
        echo '<li>Marketplace submenu registered: ' . esc_html( $submenu_found ? 'YES' : 'NO' ) . '</li>';
        echo '<li>Marketplace page hook registered: ' . esc_html( ! empty( $_registered_pages[ $marketplace_hook ] ) ? 'YES' : 'NO' ) . '</li>';
        echo '<li>Marketplace callback available: ' . esc_html( is_callable( array( __CLASS__, 'admin_form' ) ) ? 'YES' : 'NO' ) . '</li>';
        echo '</ul>';
        echo '<h2>Stripe test-payment readiness (read-only)</h2><ul>';
        foreach ( $checks as $label => $ready ) {
            echo '<li>' . esc_html( $ready ? 'PASS' : 'NOT READY' ) . ' — ' . esc_html( $label ) . '</li>';
        }
        echo '</ul><p>This checklist does not verify Stripe connectivity, checkout, webhook delivery, consent binding or settlement. Never mark unpaid reservations paid.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_create_test_booking' );
        echo '<input type="hidden" name="action" value="bh3_create_test_booking">';
        echo '<p><label>Session ID <input type="number" name="session_id" min="1" required value="2"></label></p>';
        echo '<p><label>Occurrence date <input type="date" name="occurrence_date" required value="2026-11-16"></label></p>';
        echo '<p><label>Places <input type="number" name="places" min="1" max="10" required value="1"></label></p>';
        submit_button( 'Create unpaid test reservation' );
        echo '</form><p><strong>Important:</strong> This generator does not create paid or confirmed bookings. Enrolment testing needs a separately verified test payment flow.</p></div>';
    }

    public static function create_test_booking() {
        if ( ! current_user_can( 'manage_options' )
            || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk'
            || ! defined( 'BH3_ENABLE_BOOKING_RESERVATIONS' ) || BH3_ENABLE_BOOKING_RESERVATIONS !== true
            || ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
            wp_die( 'Test booking generator unavailable.', '', array( 'response'=>403 ) );
        }
        check_admin_referer( 'bh3_create_test_booking' );
        $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
        $places = isset( $_POST['places'] ) ? absint( wp_unslash( $_POST['places'] ) ) : 0;
        $date = isset( $_POST['occurrence_date'] ) ? sanitize_text_field( wp_unslash( $_POST['occurrence_date'] ) ) : '';
        if ( ! $session_id || $places < 1 || $places > 10 ) {
            wp_die( 'Invalid test reservation inputs.', '', array( 'response'=>400 ) );
        }
        $result = self::reserve_occurrence( $session_id, $date, $places, get_current_user_id() );
        $url = admin_url( 'admin.php?page=bh3-booking-test-generator' );
        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'bh3_test_booking_error', sanitize_key( $result->get_error_code() ), $url ) );
        } else {
            wp_safe_redirect( add_query_arg( 'bh3_test_booking_id', absint( $result['booking_id'] ), $url ) );
        }
        exit;
    }

    public static function admin_form() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><h1>Marketplace commission</h1><p>Booking fee preview only. No live charges or payouts are enabled.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_save_commission' );
        echo '<input type="hidden" name="action" value="bh3_save_commission"><label>Commission (%) <input type="number" name="rate" required min="0" max="100" step="0.01" value="' . esc_attr( number_format( self::rate(), 2, '.', '' ) ) . '"></label>';
        echo '<h2>Staging checkout readiness</h2>';
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk' ) {
            $readiness_flags = array(
                'Reservations' => 'BH3_ENABLE_BOOKING_RESERVATIONS',
                'Form binding' => 'BH3_ENABLE_BOOKING_FORM_BINDING',
                'Stripe test checkout' => 'BH3_ENABLE_STRIPE_TEST_CHECKOUT',
                'Stripe test webhook' => 'BH3_ENABLE_STRIPE_TEST_WEBHOOK',
                'Test confirmation emails' => 'BH3_ENABLE_TEST_BOOKING_CONFIRMATION',
            );
            echo '<table class="widefat striped" style="max-width:680px"><thead><tr><th>Requirement</th><th>Status</th></tr></thead><tbody>';
            foreach ( $readiness_flags as $label => $constant_name ) {
                $ready = defined( $constant_name ) && constant( $constant_name ) === true;
                echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( $ready ? 'Enabled' : 'Disabled' ) . '</td></tr>';
            }
            $stripe_readiness = self::stripe_status();
            $readiness_items = array(
                'Stripe test mode' => $stripe_readiness['mode'] === 'test',
                'Stripe test API keys' => ! empty( $stripe_readiness['keys_configured'] ),
                'Webhook signing secret' => defined( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' )
                    && is_string( constant( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' ) )
                    && constant( 'BH3_STRIPE_TEST_WEBHOOK_SECRET' ) !== '',
                'Processing fees paid by Bubba Hub' => self::policy()['processing_fees'] === 'platform',
                'Fluent Forms booking form exists' => (bool) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
                    "SELECT id FROM `{$GLOBALS['wpdb']->prefix}fluentform_forms` WHERE id=%d LIMIT 1",
                    absint( get_option( 'bh3_booking_form_id', 8 ) )
                ) ),
            );
            foreach ( $readiness_items as $label => $ready ) {
                echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( $ready ? 'Ready' : 'Needs setup' ) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '<p class="description">Read-only staging checks. No keys or signing secrets are displayed, and no test payment is created.</p>';
        } else {
            echo '<p>Checkout diagnostics are available on staging only.</p>';
        }
        echo '<h2>Parent booking form</h2>';
        $booking_form_id = absint( get_option( 'bh3_booking_form_id', 8 ) );
        echo '<p><label for="bh3-booking-form-id">Fluent Forms booking form ID</label><br><input id="bh3-booking-form-id" type="number" name="booking_form_id" min="1" max="999999" step="1" required value="' . esc_attr( $booking_form_id ) . '"></p>';
        echo '<p class="description">The updated checkout form is ID 8 on staging. Keep the checkout page shortcode [bh_parent_booking]; do not embed the Fluent Form separately.</p>';
        echo '<h2>Parent-paid booking fee (preview only)</h2>';
        echo '<p>Parents will see one Bubba Hub Booking Fee on top of the class price. Leaders retain their advertised class price. The processing assumptions below are editable and are used in the server-side fee quote; they do not change existing bookings.</p>';
        $fee_settings = self::parent_booking_fee_settings();
        echo '<p><label for="bh3-processing-percent">Estimated Stripe processing (%)</label><br><input id="bh3-processing-percent" type="number" name="processing_percent" min="0" max="99.99" step="0.01" required value="' . esc_attr( $fee_settings['processing_percent'] ) . '"></p>';
        echo '<p><label for="bh3-processing-fixed">Estimated fixed processing charge (pence)</label><br><input id="bh3-processing-fixed" type="number" name="processing_fixed_pence" min="0" max="10000" step="1" required value="' . esc_attr( (string) $fee_settings['processing_fixed_pence'] ) . '"></p>';
        $example = self::parent_paid_fee_preview( 500 );
        if ( ! is_wp_error( $example ) ) {
            echo '<p><strong>Example £5.00 class:</strong> class £5.00 + one booking fee £'
                . esc_html( number_format( $example['booking_fee_pence'] / 100, 2 ) )
                . ' = parent total £' . esc_html( number_format( $example['parent_total_pence'] / 100, 2 ) )
                . '; leader receives £5.00.</p>';
        }
        echo '<p><em>Illustrative processing assumption: ' . esc_html( $fee_settings['processing_percent'] ) . '% + ' . esc_html( (string) $fee_settings['processing_fixed_pence'] ) . 'p. Actual Stripe pricing depends on card and payment method; confirm your account rates before activation. Stripe test checkout remains gated by the staging readiness checks below; live payments are disabled.</em></p>';
        $policy = self::policy();
        echo '<p><strong>Parent-facing disclosure:</strong> Class price + one Bubba Hub Booking Fee = total payable. The leader receives the full class price. Stripe charges the platform under destination charges; the booking fee is intended to cover estimated processing costs and Bubba Hub commission.</p>';
        $choices = array(
            'processing_fees' => array( 'Stripe processing costs charged to', array( 'leader' => 'Leader (legacy; incompatible with current checkout)', 'platform' => 'Bubba Hub (recovered through parent booking fee)' ) ),
            'payout_schedule' => array( 'Preferred payout schedule', array( 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'manual' => 'Manual review' ) ),
            'refund_policy' => array( 'Refund handling', array( 'manual_review' => 'Manual review before refund', 'leader_approval' => 'Leader approval required' ) ),
        );
        foreach ( $choices as $field => $definition ) {
            echo '<p><label for="bh3-' . esc_attr( $field ) . '">' . esc_html( $definition[0] ) . '</label><br><select id="bh3-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
            foreach ( $definition[1] as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy[$field], $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></p>';
        }
        $stripe = self::stripe_status();
        echo '<h2>Stripe Connect</h2><p><label for="bh3-stripe-mode">Environment</label><br><select id="bh3-stripe-mode" name="stripe_mode">';
        foreach ( array( 'test' => 'Test mode', 'live' => 'Live mode (checkout still disabled)' ) as $mode => $label ) {
            echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $stripe['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p><p><strong>Credentials:</strong> ' . esc_html( $stripe['keys_configured'] ? 'Configured in server environment (not yet verified with Stripe)' : 'Not configured' ) . '</p>';
        echo '<p>Set BH3_STRIPE_TEST_SECRET_KEY and BH3_STRIPE_TEST_PUBLISHABLE_KEY securely in wp-config.php or server environment. Live equivalents: BH3_STRIPE_LIVE_SECRET_KEY and BH3_STRIPE_LIVE_PUBLISHABLE_KEY. Never paste secret keys into a page or form.</p>';
        echo '<p><strong>Connection:</strong> Not verified. Stripe Checkout and automatic payouts remain disabled.</p><p><strong>PayPal / Invoice:</strong> Manual settlement workflow not configured.</p>';
        submit_button( 'Save payment settings' ); echo '</form>';
        $last = get_option( 'bh3_stripe_verification', array() );
        if ( is_array( $last ) && ! empty( $last['result'] ) ) {
            echo '<p><strong>Last verification:</strong> ' . esc_html( $last['result'] ) . ' (' . esc_html( $last['mode'] ?? '' ) . ' mode). Reverify after changing credentials.</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_verify_stripe' );
        echo '<input type="hidden" name="action" value="bh3_verify_stripe">';
        submit_button( 'Verify Stripe API connection', 'secondary' );
        echo '</form><p>Fluent Forms collects booking details only. Direct Stripe Connect checkout, payment verification, refunds and reconciliation must pass staging QA before activation.</p></div>';
    }
    public static function save_commission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_save_commission' );
        $raw = isset( $_POST['rate'] ) && is_scalar( $_POST['rate'] ) ? trim( (string) wp_unslash( $_POST['rate'] ) ) : '';
        if ( ! preg_match( '/^(?:100(?:\\.0{1,2})?|[0-9]{1,2}(?:\\.[0-9]{1,2})?)$/', $raw ) ) { wp_die( 'Invalid percentage', '', array( 'response' => 400 ) ); }
        $allowed = array(
            'processing_fees' => array( 'leader', 'platform' ),
            'payout_schedule' => array( 'weekly', 'monthly', 'manual' ),
            'refund_policy' => array( 'manual_review', 'leader_approval' ),
        );
        $policy = self::policy();
        foreach ( $allowed as $key => $values ) {
            $input = isset( $_POST[$key] ) && is_scalar( $_POST[$key] ) ? sanitize_key( wp_unslash( $_POST[$key] ) ) : '';
            if ( ! in_array( $input, $values, true ) ) { wp_die( 'Invalid payment policy', '', array( 'response' => 400 ) ); }
            $policy[$key] = $input;
        }
        $processing_raw = isset( $_POST['processing_percent'] ) && is_scalar( $_POST['processing_percent'] ) ? trim( (string) wp_unslash( $_POST['processing_percent'] ) ) : '';
        $fixed_raw = isset( $_POST['processing_fixed_pence'] ) && is_scalar( $_POST['processing_fixed_pence'] ) ? trim( (string) wp_unslash( $_POST['processing_fixed_pence'] ) ) : '';
        if ( ! preg_match( '/^(?:0|[1-9][0-9]?)(?:\\.[0-9]{1,2})?$/', $processing_raw )
            || (float) $processing_raw >= 100
            || ! preg_match( '/^(?:0|[1-9][0-9]{0,4})$/', $fixed_raw )
            || (int) $fixed_raw > 10000 ) {
            wp_die( 'Invalid processing fee assumptions', '', array( 'response' => 400 ) );
        }
        $booking_form_raw = isset( $_POST['booking_form_id'] ) && is_scalar( $_POST['booking_form_id'] )
            ? trim( (string) wp_unslash( $_POST['booking_form_id'] ) ) : '';
        if ( ! preg_match( '/^[1-9][0-9]{0,5}$/', $booking_form_raw ) ) {
            wp_die( 'Invalid Fluent Forms booking form ID', '', array( 'response' => 400 ) );
        }
        $booking_form_id = (int) $booking_form_raw;
        global $wpdb;
        $form_table = $wpdb->prefix . 'fluentform_forms';
        $form_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$form_table}` WHERE id=%d LIMIT 1", $booking_form_id
        ) );
        if ( (int) $form_exists !== $booking_form_id ) {
            wp_die( 'Booking form ID not found in Fluent Forms', '', array( 'response' => 400 ) );
        }
        update_option( 'bh3_booking_form_id', $booking_form_id, false );
        update_option( 'bh3_parent_booking_fee_settings', array(
            'processing_percent' => number_format( (float) $processing_raw, 2, '.', '' ),
            'processing_fixed_pence' => (int) $fixed_raw,
        ), false );
        update_option( 'bh3_commission_percent', number_format( (float) $raw, 2, '.', '' ), false );
        update_option( 'bh3_marketplace_policy', $policy, false );
        $mode = isset( $_POST['stripe_mode'] ) && is_scalar( $_POST['stripe_mode'] ) ? sanitize_key( wp_unslash( $_POST['stripe_mode'] ) ) : '';
        if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) { wp_die( 'Invalid Stripe mode', '', array( 'response' => 400 ) ); }
        update_option( 'bh3_stripe_mode', $mode, false );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-marketplace' ) ); exit;
    }
    public static function leader_form() {
        if ( ! is_user_logged_in() ) { return '<p>Sign in to set payout preferences.</p>'; }
        if ( ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) { return '<p>A linked leader account is required.</p>'; }
        $current = get_user_meta( get_current_user_id(), 'bh3_payout_method', true );
        ob_start();
        echo '<section class="bh3-payout-settings"><h2>Payout settings</h2><p>Select your preferred method. Payments and payouts are not active yet; this does not connect a payment account.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'bh3_save_payout_method' );
        echo '<input type="hidden" name="action" value="bh3_save_payout_method"><label for="bh3-method">Payout preference</label><select id="bh3-method" name="method">';
        foreach ( array( 'stripe' => 'Stripe Connect (automated setup pending)', 'paypal' => 'PayPal (manual settlement pending)', 'invoice' => 'Invoice (manual settlement pending)' ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><button type="submit">Save preference</button></form>';
        if ( self::stripe_status()['mode'] === 'test' && self::stripe_status()['keys_configured'] && defined( 'BH3_ENABLE_STRIPE_TEST_ONBOARDING' ) && BH3_ENABLE_STRIPE_TEST_ONBOARDING === true ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'bh3_stripe_onboard' );
            echo '<input type="hidden" name="action" value="bh3_stripe_onboard"><button type="submit">Set up Stripe test payouts</button></form>';
            echo '<p>Test onboarding only. Payouts remain disabled.</p>';
        }
        if ( $current === 'stripe' ) {
            $stripe_account = self::leader_stripe_status( get_current_user_id() );
            echo '<p role="status"><strong>Stripe test payout status:</strong> ' . esc_html( $stripe_account['label'] ) . '</p>';
        }
        echo '</section>';
        return ob_get_clean();
    }
    public static function save_method() {
        if ( ! is_user_logged_in() || ! absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_save_payout_method' );
        $method = isset( $_POST['method'] ) && is_scalar( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
        if ( ! in_array( $method, array( 'stripe', 'paypal', 'invoice' ), true ) ) { wp_die( 'Invalid payout method', '', array( 'response' => 400 ) ); }
        update_user_meta( get_current_user_id(), 'bh3_payout_method', $method );
        wp_safe_redirect( wp_get_referer() ?: home_url( '/leader/account/' ) ); exit;
    }
}
