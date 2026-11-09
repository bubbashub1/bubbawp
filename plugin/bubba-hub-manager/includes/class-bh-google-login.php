<?php
/**
 * Google OpenID Connect sign-in for Bubba Hub.
 *
 * Configuration (wp-config.php):
 * define( 'BH_GOOGLE_CLIENT_ID', '...apps.googleusercontent.com' );
 * define( 'BH_GOOGLE_CLIENT_SECRET', '...' );
 *
 * Shortcode: [bh_google_login]
 */
defined( 'ABSPATH' ) || exit;

final class BH_Google_Login_3 {
    const STATE_PREFIX = 'bh_google_state_';
    const LINK_PREFIX  = 'bh_google_link_';
    const META_SUB     = 'bh_google_subject';
    const META_TYPE    = 'bh_account_type';
    const META_STATUS  = 'bh_account_status';

    public static function register(): void {
        add_action( 'admin_post_nopriv_bh_google_start', array( __CLASS__, 'start' ) );
        add_action( 'admin_post_bh_google_start', array( __CLASS__, 'start' ) );
        add_action( 'admin_post_nopriv_bh_google_callback', array( __CLASS__, 'callback' ) );
        add_action( 'admin_post_bh_google_callback', array( __CLASS__, 'callback' ) );
        add_action( 'admin_post_nopriv_bh_google_link_confirm', array( __CLASS__, 'confirm_link' ) );
        add_action( 'admin_post_bh_google_link_confirm', array( __CLASS__, 'confirm_link' ) );
        add_shortcode( 'bh_google_login', array( __CLASS__, 'render' ) );
        add_action( 'login_form', array( __CLASS__, 'native_login_button' ) );
    }

    private static function configured(): bool {
        return defined( 'BH_GOOGLE_CLIENT_ID' ) && is_string( BH_GOOGLE_CLIENT_ID )
            && BH_GOOGLE_CLIENT_ID !== '' && defined( 'BH_GOOGLE_CLIENT_SECRET' )
            && is_string( BH_GOOGLE_CLIENT_SECRET ) && BH_GOOGLE_CLIENT_SECRET !== '';
    }

    private static function callback_url(): string {
        return admin_url( 'admin-post.php?action=bh_google_callback' );
    }

    public static function render( $atts = array() ): string {
        if ( is_user_logged_in() ) {
            return '<p class="bh-google-signed-in">You are already signed in to Bubba Hub.</p>';
        }
        if ( ! self::configured() ) {
            return current_user_can( 'manage_options' )
                ? '<p class="bh-google-setup-warning">Google sign-in is not configured yet. Add BH_GOOGLE_CLIENT_ID and BH_GOOGLE_CLIENT_SECRET to wp-config.php.</p>'
                : '';
        }
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=bh_google_start' ), 'bh_google_start' );
        $html = '<section class="bh-google-login" aria-label="Google sign in">';
        $html .= '<p class="bh-google-heading">Continue with Google</p>';
        $html .= '<p class="bh-google-help">Choose the type of Bubba Hub account you want to use. New leader accounts are reviewed before leader features are enabled.</p>';
        $html .= '<div class="bh-google-actions">';
        $html .= '<a class="bh-google-button" href="' . esc_url( add_query_arg( 'bh_account_type', 'family', $url ) ) . '"><span aria-hidden="true">G</span> Continue as a family</a>';
        $html .= '<a class="bh-google-button" href="' . esc_url( add_query_arg( 'bh_account_type', 'leader', $url ) ) . '"><span aria-hidden="true">G</span> Continue as a class leader</a>';
        $html .= '</div><p class="bh-google-privacy">Google shares your name, email address and a secure account identifier. Bubba Hub never receives your Google password.</p>';
        $html .= '<style>.bh-google-login{margin:20px 0;padding:20px;border:1px solid #dce5df;border-radius:14px;background:#fff}.bh-google-heading{font-size:1.1rem;font-weight:700;margin:0 0 6px}.bh-google-help,.bh-google-privacy{font-size:.9rem;color:#58665f}.bh-google-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:16px 0}.bh-google-button{display:flex;align-items:center;justify-content:center;gap:10px;padding:13px 16px;border:1px solid #cbd5cf;border-radius:9px;color:#24352c;text-decoration:none;font-weight:650;background:#fff}.bh-google-button:hover,.bh-google-button:focus{border-color:#54836a;box-shadow:0 0 0 2px #dcebe1}.bh-google-button span{font-weight:800;font-size:1.15rem;color:#4285f4}.bh-google-privacy{font-size:.78rem;margin-bottom:0}.bh-google-setup-warning{padding:12px;background:#fff4d6;border-left:4px solid #b7791f}</style>';
        $html .= '</section>';
        return $html;
    }

    public static function native_login_button(): void {
        echo do_shortcode( '[bh_google_login]' );
    }

    public static function start(): void {
        check_admin_referer( 'bh_google_start' );
        if ( ! self::configured() ) {
            self::fail( 'Google sign-in has not been configured yet.' );
        }
        if ( ! is_ssl() ) {
            self::fail( 'Google sign-in requires HTTPS. Please use the secure Bubba Hub website address.' );
        }
        $type = isset( $_GET['bh_account_type'] ) ? sanitize_key( wp_unslash( $_GET['bh_account_type'] ) ) : 'family';
        if ( ! in_array( $type, array( 'family', 'leader' ), true ) ) {
            $type = 'family';
        }
        $state = self::random_token( 32 );
        $nonce = self::random_token( 32 );
        $verifier = self::random_token( 48 );
        $challenge = self::base64url( hash( 'sha256', $verifier, true ) );
        $payload = array(
            'nonce' => $nonce,
            'verifier' => $verifier,
            'account_type' => $type,
            'created' => time(),
        );
        set_transient( self::STATE_PREFIX . hash( 'sha256', $state ), $payload, 10 * MINUTE_IN_SECONDS );
        self::set_state_cookie( $state );
        $query = array(
            'client_id' => BH_GOOGLE_CLIENT_ID,
            'redirect_uri' => self::callback_url(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        );
        wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) );
        exit;
    }

    public static function callback(): void {
        $state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        $cookie = isset( $_COOKIE['bh_google_state'] ) && is_string( $_COOKIE['bh_google_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['bh_google_state'] ) ) : '';
        if ( $state === '' || $cookie === '' || ! hash_equals( $cookie, $state ) ) {
            self::clear_state_cookie();
            self::fail( 'The Google sign-in session expired or could not be verified. Please try again.' );
        }
        $key = self::STATE_PREFIX . hash( 'sha256', $state );
        $flow = get_transient( $key );
        delete_transient( $key );
        self::clear_state_cookie();
        if ( ! is_array( $flow ) || empty( $flow['nonce'] ) || empty( $flow['verifier'] ) ) {
            self::fail( 'The Google sign-in session expired. Please try again.' );
        }
        if ( isset( $_GET['error'] ) ) {
            self::fail( 'Google sign-in was cancelled or declined.' );
        }
        $code = isset( $_GET['code'] ) && is_string( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        if ( $code === '' ) {
            self::fail( 'Google did not return an authorisation code.' );
        }
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'redirection' => 0,
            'headers' => array( 'Accept' => 'application/json' ),
            'body' => array(
                'code' => $code,
                'client_id' => BH_GOOGLE_CLIENT_ID,
                'client_secret' => BH_GOOGLE_CLIENT_SECRET,
                'redirect_uri' => self::callback_url(),
                'grant_type' => 'authorization_code',
                'code_verifier' => $flow['verifier'],
            ),
        ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            self::fail( 'Google could not complete sign-in. Please try again.' );
        }
        $tokens = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $tokens ) || empty( $tokens['id_token'] ) ) {
            self::fail( 'Google did not return a valid identity token.' );
        }
        $claims = self::verify_id_token( $tokens['id_token'], $flow['nonce'] );
        if ( is_wp_error( $claims ) ) {
            self::fail( 'Google identity verification failed. Please try again.' );
        }
        self::resolve_account( $claims, $flow['account_type'] );
    }

    private static function verify_id_token( string $jwt, string $expected_nonce ) {
        $parts = explode( '.', $jwt );
        if ( count( $parts ) !== 3 ) {
            return new WP_Error( 'bh_google_jwt', 'Malformed token.' );
        }
        $header = json_decode( self::base64url_decode( $parts[0] ), true );
        $claims = json_decode( self::base64url_decode( $parts[1] ), true );
        if ( ! is_array( $header ) || ! is_array( $claims ) || ( $header['alg'] ?? '' ) !== 'RS256' || empty( $header['kid'] ) ) {
            return new WP_Error( 'bh_google_jwt', 'Unsupported token.' );
        }
        $keys = get_transient( 'bh_google_jwks' );
        if ( ! is_array( $keys ) || empty( $keys['keys'] ) ) {
            $key_response = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/certs', array( 'timeout' => 10, 'redirection' => 0 ) );
            if ( is_wp_error( $key_response ) || 200 !== wp_remote_retrieve_response_code( $key_response ) ) {
                return new WP_Error( 'bh_google_keys', 'Unable to retrieve signing keys.' );
            }
            $keys = json_decode( wp_remote_retrieve_body( $key_response ), true );
            if ( ! is_array( $keys ) || empty( $keys['keys'] ) ) {
                return new WP_Error( 'bh_google_keys', 'Invalid signing keys.' );
            }
            set_transient( 'bh_google_jwks', $keys, HOUR_IN_SECONDS );
        }
        $jwk = null;
        foreach ( $keys['keys'] as $candidate ) {
            if ( isset( $candidate['kid'] ) && hash_equals( (string) $header['kid'], (string) $candidate['kid'] ) ) {
                $jwk = $candidate;
                break;
            }
        }
        if ( ! $jwk || empty( $jwk['n'] ) || empty( $jwk['e'] ) ) {
            delete_transient( 'bh_google_jwks' );
            return new WP_Error( 'bh_google_key', 'Signing key not found.' );
        }
        $pem = self::jwk_to_pem( $jwk['n'], $jwk['e'] );
        if ( ! $pem || ! function_exists( 'openssl_verify' ) ) {
            return new WP_Error( 'bh_google_crypto', 'Server cannot verify Google signatures.' );
        }
        $signature = self::base64url_decode( $parts[2] );
        if ( openssl_verify( $parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256 ) !== 1 ) {
            return new WP_Error( 'bh_google_signature', 'Invalid signature.' );
        }
        $now = time();
        $issuer_ok = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
        $aud_ok = isset( $claims['aud'] ) && ( is_string( $claims['aud'] ) ? hash_equals( BH_GOOGLE_CLIENT_ID, $claims['aud'] ) : ( is_array( $claims['aud'] ) && in_array( BH_GOOGLE_CLIENT_ID, $claims['aud'], true ) && isset( $claims['azp'] ) && hash_equals( BH_GOOGLE_CLIENT_ID, (string) $claims['azp'] ) ) );
        $email_ok = ! empty( $claims['email'] ) && ! empty( $claims['email_verified'] );
        $nonce_ok = isset( $claims['nonce'] ) && hash_equals( $expected_nonce, (string) $claims['nonce'] );
        if ( ! $issuer_ok || ! $aud_ok || ! $email_ok || ! $nonce_ok || empty( $claims['sub'] )
            || empty( $claims['exp'] ) || (int) $claims['exp'] <= $now
            || ( isset( $claims['iat'] ) && (int) $claims['iat'] > $now + 60 ) ) {
            return new WP_Error( 'bh_google_claims', 'Identity claims did not pass validation.' );
        }
        return $claims;
    }

    private static function resolve_account( array $claims, string $requested_type ): void {
        $subject = sanitize_text_field( (string) $claims['sub'] );
        $email = sanitize_email( (string) $claims['email'] );
        if ( ! is_email( $email ) || $subject === '' ) {
            self::fail( 'Google did not provide a usable verified email address.' );
        }
        $linked = get_users( array(
            'meta_key' => self::META_SUB,
            'meta_value' => $subject,
            'number' => 2,
            'count_total' => false,
            'fields' => 'ID',
        ) );
        if ( count( $linked ) > 1 ) {
            self::fail( 'This Google account is linked to more than one Bubba Hub account. Please contact support.' );
        }
        if ( count( $linked ) === 1 ) {
            self::login_user( (int) $linked[0] );
        }
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            self::send_link_confirmation( (int) $existing->ID, $subject, $email );
        }
        $username_base = sanitize_user( current( explode( '@', $email ) ), true );
        if ( $username_base === '' ) {
            $username_base = 'bubba-member';
        }
        $username = $username_base;
        if ( username_exists( $username ) ) {
            $username .= '-' . strtolower( wp_generate_password( 5, false, false ) );
        }
        $user_id = wp_insert_user( array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password( 40, true, true ),
            'display_name' => isset( $claims['name'] ) ? sanitize_text_field( (string) $claims['name'] ) : $username,
            'role' => 'subscriber',
        ) );
        if ( is_wp_error( $user_id ) ) {
            self::fail( 'Bubba Hub could not create your account. Please contact support.' );
        }
        update_user_meta( $user_id, self::META_SUB, $subject );
        update_user_meta( $user_id, self::META_TYPE, $requested_type );
        update_user_meta( $user_id, self::META_STATUS, 'leader' === $requested_type ? 'pending_approval' : 'active' );
        update_user_meta( $user_id, 'bh_google_email', $email );
        if ( 'leader' === $requested_type ) {
            update_user_meta( $user_id, 'bh_leader_approval_required', 1 );
        }
        self::login_user( (int) $user_id );
    }

    private static function send_link_confirmation( int $user_id, string $subject, string $email ): void {
        $token = self::random_token( 40 );
        $key = self::LINK_PREFIX . hash( 'sha256', $token );
        set_transient( $key, array( 'user_id' => $user_id, 'subject' => $subject ), 15 * MINUTE_IN_SECONDS );
        $url = add_query_arg( array(
            'action' => 'bh_google_link_confirm',
            'token' => rawurlencode( $token ),
        ), admin_url( 'admin-post.php' ) );
        $sent = wp_mail(
            $email,
            'Confirm your Bubba Hub Google sign-in',
            "A request was made to connect this Google account to your existing Bubba Hub account.\n\nIf this was you, confirm the connection within 15 minutes:\n" . esc_url_raw( $url ) . "\n\nIf you did not request this, ignore this email. Your account has not been changed."
        );
        if ( ! $sent ) {
            delete_transient( $key );
            self::fail( 'We could not send the account-link confirmation email. No account changes were made.' );
        }
        self::message( 'We found an existing Bubba Hub account for this email. Check that inbox and confirm the secure link to connect Google. Your account has not been changed yet.' );
    }

    public static function confirm_link(): void {
        $token = isset( $_REQUEST['token'] ) && is_string( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
        if ( strlen( $token ) < 40 ) {
            self::fail( 'This account-link request is invalid or has expired.' );
        }
        $key = self::LINK_PREFIX . hash( 'sha256', $token );
        $data = get_transient( $key );
        if ( ! is_array( $data ) || empty( $data['user_id'] ) || empty( $data['subject'] ) ) {
            self::fail( 'This account-link request is invalid or has expired.' );
        }
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
            $html = '<p>Confirm that you want to connect Google sign-in to your existing Bubba Hub account.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            $html .= '<input type="hidden" name="action" value="bh_google_link_confirm"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
            ob_start();
            wp_nonce_field( 'bh_google_link_confirm_' . hash( 'sha256', $token ) );
            $html .= ob_get_clean();
            $html .= '<button type="submit">Confirm Google account link</button></form>';
            wp_die( $html, esc_html__( 'Confirm Bubba Hub account link', 'bubba-hub-manager' ), array( 'response' => 200 ) );
        }
        check_admin_referer( 'bh_google_link_confirm_' . hash( 'sha256', $token ) );
        delete_transient( $key );
        $user = get_user_by( 'id', absint( $data['user_id'] ) );
        if ( ! $user || get_user_meta( $user->ID, self::META_SUB, true ) ) {
            self::fail( 'This Bubba Hub account can no longer be linked. Please sign in normally or contact support.' );
        }
        $other = get_users( array( 'meta_key' => self::META_SUB, 'meta_value' => sanitize_text_field( $data['subject'] ), 'number' => 1, 'fields' => 'ID' ) );
        if ( ! empty( $other ) ) {
            self::fail( 'This Google account is already connected to another Bubba Hub account.' );
        }
        update_user_meta( $user->ID, self::META_SUB, sanitize_text_field( $data['subject'] ) );
        update_user_meta( $user->ID, 'bh_google_email', sanitize_email( $user->user_email ) );
        self::login_user( (int) $user->ID );
    }

    private static function login_user( int $user_id ): void {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            self::fail( 'Bubba Hub could not find your account.' );
        }
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );
        $redirect = apply_filters( 'login_redirect', home_url( '/' ), home_url( '/' ), $user );
        wp_safe_redirect( wp_validate_redirect( $redirect, home_url( '/' ) ) );
        exit;
    }

    private static function fail( string $message ): void {
        wp_die( esc_html( $message ), esc_html__( 'Bubba Hub sign-in', 'bubba-hub-manager' ), array( 'response' => 400, 'back_link' => true ) );
    }

    private static function message( string $message ): void {
        wp_die( '<p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( home_url( '/auth/' ) ) . '">Return to sign in</a></p>', esc_html__( 'Bubba Hub sign-in', 'bubba-hub-manager' ), array( 'response' => 200 ) );
    }

    private static function set_state_cookie( string $state ): void {
        setcookie( 'bh_google_state', $state, array(
            'expires' => time() + 600,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
        $_COOKIE['bh_google_state'] = $state;
    }

    private static function clear_state_cookie(): void {
        setcookie( 'bh_google_state', '', array(
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
        unset( $_COOKIE['bh_google_state'] );
    }

    private static function random_token( int $bytes ): string {
        return self::base64url( random_bytes( $bytes ) );
    }

    private static function base64url( string $value ): string {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( string $value ): string {
        $decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
        return is_string( $decoded ) ? $decoded : '';
    }

    private static function der_length( int $length ): string {
        if ( $length < 128 ) {
            return chr( $length );
        }
        $bytes = ltrim( pack( 'N', $length ), chr( 0 ) );
        return chr( 0x80 | strlen( $bytes ) ) . $bytes;
    }

    private static function der_integer( string $bytes ): string {
        $bytes = ltrim( $bytes, chr( 0 ) );
        if ( $bytes === '' ) {
            $bytes = chr( 0 );
        }
        if ( ord( $bytes[0] ) > 0x7f ) {
            $bytes = chr( 0 ) . $bytes;
        }
        return "\x02" . self::der_length( strlen( $bytes ) ) . $bytes;
    }

    private static function jwk_to_pem( string $modulus, string $exponent ): string {
        $n = self::base64url_decode( $modulus );
        $e = self::base64url_decode( $exponent );
        if ( $n === '' || $e === '' ) {
            return '';
        }
        $rsa = "\x30" . self::der_length( strlen( self::der_integer( $n ) . self::der_integer( $e ) ) )
            . self::der_integer( $n ) . self::der_integer( $e );
        $algorithm = hex2bin( '300d06092a864886f70d0101010500' );
        $bit_string = "\x03" . self::der_length( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
        $sequence = "\x30" . self::der_length( strlen( $algorithm . $bit_string ) ) . $algorithm . $bit_string;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $sequence ), 64, "\n" ) . "-----END PUBLIC KEY-----";
    }
}
