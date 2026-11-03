<?php
/** Small, staging-only styling for organiser dashboard screens. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_UI_3 {
    public static function enqueue() {
        if ( strtolower( (string ) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        if ( ! in_array( $page, array( 'bh3-my-listings', 'bh3-leader-edit', 'bh3-leader-sessions', 'bh3-leader-venues' ), true ) ) { return; }
        wp_register_style( 'bh3-leader-ui', false, array(), '0.1.0' );
        wp_enqueue_style( 'bh3-leader-ui' );
        wp_add_inline_style( 'bh3-leader-ui', '
            #wpbody-content > .wrap { max-width: 1160px; }
            #wpbody-content > .wrap h1 { font-size: 26px; font-weight: 700; line-height: 1.3; margin: 16px 0; }
            #wpbody-content > .wrap h2 { font-size: 19px; margin: 24px 0 12px; }
            #wpbody-content > .wrap p { line-height: 1.6; }
            #wpbody-content > .wrap .widefat { border: 1px solid #dce3e2; border-radius: 10px; border-spacing: 0; overflow: hidden; background: #fff; box-shadow: 0 2px 8px rgba(16,40,32,.04); }
            #wpbody-content > .wrap .widefat th { font-weight: 650; background: #f4f7f5; color: #263b33; }
            #wpbody-content > .wrap .widefat th, #wpbody-content > .wrap .widefat td { padding: 12px 14px; vertical-align: middle; }
            #wpbody-content > .wrap .button { border-radius: 7px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center; padding: 3px 13px; }
            #wpbody-content > .wrap .button-primary { background: #245e4a; border-color: #245e4a; }
            #wpbody-content > .wrap .form-table { background: #fff; border: 1px solid #dce3e2; border-radius: 10px; display: block; padding: 12px 20px; box-sizing: border-box; max-width: 850px; }
            #wpbody-content > .wrap .form-table tbody { display: block; }
            #wpbody-content > .wrap .form-table tr { display: grid; grid-template-columns: minmax(140px, 210px) minmax(0, 1fr); gap: 12px; align-items: center; padding: 8px 0; border-bottom: 1px solid #eef1ef; }
            #wpbody-content > .wrap .form-table tr:last-child { border-bottom: 0; }
            #wpbody-content > .wrap .form-table th, #wpbody-content > .wrap .form-table td { display: block; width: auto; padding: 2px 0; }
            #wpbody-content > .wrap .form-table th { font-weight: 600; }
            #wpbody-content > .wrap .form-table input[type=time], #wpbody-content > .wrap .form-table input[type=date], #wpbody-content > .wrap .form-table select { min-height: 38px; min-width: 160px; max-width: 100%; }
            #wpbody-content > .wrap .form-table .regular-text, #wpbody-content > .wrap .form-table .large-text { width: 100%; max-width: 540px; }
            #wpbody-content > .wrap form[method=get] input[type=search] { min-height: 38px; min-width: min(320px, 100%); border-radius: 7px; }
            @media (max-width: 782px) {
                #wpbody-content > .wrap { margin: 12px 10px; }
                #wpbody-content > .wrap .form-table { padding: 10px 14px; }
                #wpbody-content > .wrap .form-table tr { grid-template-columns: 1fr; gap: 4px; }
                #wpbody-content > .wrap .widefat { display: block; overflow-x: auto; white-space: nowrap; }
                #wpbody-content > .wrap .widefat th, #wpbody-content > .wrap .widefat td { padding: 10px; }
            }
        ' );
    }
}
