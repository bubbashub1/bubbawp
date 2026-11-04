<?php
/** Read-only public organiser storefront preview. No account or schema changes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Storefront_3 {
    public static function register() {
        add_shortcode( 'bh_business_storefront', array( __CLASS__, 'render' ) );
    }
    public static function render( $atts = array() ) {
        if ( strtolower( (string ) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bh_business_storefront' );
        $id = absint( $atts['id'] );
        if ( ! $id && is_user_logged_in() ) { $id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ); }
        if ( ! $id ) { return '<p>Select a business profile, or sign in with a linked organiser account.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        $activity_table = $wpdb->prefix . 'activities';
        $profile = $wpdb->get_row( $wpdb->prepare( "SELECT id,name,description,website_url,contact_email FROM `{$table}` WHERE id=%d LIMIT 1", $id ), ARRAY_A );
        if ( ! $profile ) { return '<p>Business profile not found.</p>'; }
        $activities = $wpdb->get_results( $wpdb->prepare( "SELECT id,title,category FROM `{$activity_table}` WHERE organiser_id=%d AND status='published' ORDER BY title LIMIT 30", $id ), ARRAY_A );
        ob_start(); ?>
        <section class="bh-storefront">
          <style>
          .bh-storefront{max-width:1160px;margin:24px auto;padding:0 20px;color:#203c31;font-family:inherit}.bh-storefront *{box-sizing:border-box}
          .bh-storefront .bh-shero{padding:clamp(24px,5vw,48px);border-radius:24px;background:linear-gradient(120deg,#1c5641,#438566);color:#fff}
          .bh-storefront .bh-shero h1{color:#fff;font-size:clamp(30px,5vw,48px);margin:8px 0}.bh-storefront .bh-shero p{color:#e8f5eb}
          .bh-storefront .bh-sgrid{display:grid;grid-template-columns:2fr 1fr;gap:22px;margin:22px 0}
          .bh-storefront .bh-scard{background:#fff;border:1px solid #dbe5df;border-radius:18px;padding:24px;box-shadow:0 8px 24px rgba(20,65,42,.05)}
          .bh-storefront .bh-scard h2{font-size:23px;margin:0 0 16px}.bh-storefront .bh-sactivity{border-top:1px solid #e7eee8;padding:16px 0}
          .bh-storefront .bh-sactivity strong{display:block;font-size:17px}.bh-storefront .bh-sactivity small{color:#567264}
          .bh-storefront .bh-slink{display:inline-block;padding:10px 15px;border-radius:10px;background:#245e4a;color:#fff!important;text-decoration:none;margin:5px 6px 5px 0;overflow-wrap:anywhere}
          .bh-storefront a:focus-visible{outline:3px solid #245e4a;outline-offset:3px}
          @media(max-width:720px){.bh-storefront .bh-sgrid{grid-template-columns:1fr}.bh-storefront .bh-scard{padding:18px}}
          </style>
          <header class="bh-shero"><p>BUBBA HUB · BUSINESS PROFILE</p><h1><?php echo esc_html( $profile['name'] ); ?></h1><p>Discover this organiser's activities and get in touch.</p></header>
          <div class="bh-sgrid">
            <div class="bh-scard"><h2>About us</h2><?php echo wp_kses_post( wpautop( $profile['description'] ?: 'More information coming soon.' ) ); ?>
              <h2>Our activities</h2>
              <?php if ( ! $activities ) : ?><p>No published activities are currently listed.</p><?php endif; ?>
              <?php foreach ( $activities as $activity ) : ?><div class="bh-sactivity"><strong><?php echo esc_html( $activity['title'] ); ?></strong><small><?php echo esc_html( $activity['category'] ); ?></small></div><?php endforeach; ?>
            </div>
            <aside class="bh-scard"><h2>Contact &amp; links</h2>
              <?php if ( $profile['website_url'] ) : ?><a class="bh-slink" href="<?php echo esc_url( $profile['website_url'] ); ?>" rel="noopener noreferrer" target="_blank">Visit website</a><?php endif; ?>
              <?php if ( $profile['contact_email'] ) : ?><a class="bh-slink" href="<?php echo esc_url( 'mailto:' . sanitize_email( $profile['contact_email'] ) ); ?>">Email organiser</a><?php endif; ?>
              <?php if ( ! $profile['website_url'] && ! $profile['contact_email'] ) : ?><p>Contact details are not yet available.</p><?php endif; ?>
            </aside>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
