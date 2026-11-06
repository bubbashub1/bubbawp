<?php
/**
 * Optional Cloudflare Turnstile server-side verifier for public booking forms.
 * This class is NOT wired to form submissions until staging configuration is ready.
 * Store the secret outside public HTML; never expose it to the browser.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Booking_Turnstile_3 {
    public static function verify( $token, $secret, $remote_ip = '' ) {
        if ( ! is_string( $token ) || '' === trim( $token )
            || ! is_string( $secret ) || '' === trim( $secret ) ) {
            return array( 'ok' => false, 'reason' => 'missing_token_or_secret' );
        }
        if ( strlen( $token ) > 2048 ) {
            return array( 'ok' => false, 'reason' => 'invalid_token' );
        }
        $body = array( 'secret' => $secret, 'response' => $token );
        if ( is_string( $remote_ip ) && filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
            $body['remoteip'] = $remote_ip;
        }
        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array( 'timeout' => 10, 'redirection' => 0, 'body' => $body )
        );
        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'reason' => 'verification_unavailable' );
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return array( 'ok' => false, 'reason' => 'verification_unavailable' );
        }
        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $result ) || empty( $result['success'] ) ) {
            return array( 'ok' => false, 'reason' => 'verification_failed' );
        }
        return array( 'ok' => true, 'reason' => 'verified' );
    }
}
