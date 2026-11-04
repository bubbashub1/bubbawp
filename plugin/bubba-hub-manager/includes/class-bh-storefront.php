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
        if ( ! $id && isset( $_GET['business_id'] ) ) {
            $candidate = absint( wp_unslash( $_GET['business_id'] ) );
            $directory_data = get_option( 'bh3_storefront_' . $candidate, array() );
            if ( $candidate && is_array( $directory_data ) && ! empty( $directory_data['directory_opt_in'] ) ) { $id = $candidate; }
        }
        if ( ! $id && is_user_logged_in() ) { $id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ); }
        if ( ! $id ) { return '<p>Select a business profile, or sign in with a linked organiser account.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        $activity_table = $wpdb->prefix . 'activities';
        $profile = $wpdb->get_row( $wpdb->prepare( "SELECT id,name,description,website_url,contact_email FROM `{$table}` WHERE id=%d LIMIT 1", $id ), ARRAY_A );
        if ( ! $profile ) { return '<p>Business profile not found.</p>'; }
        $activities = $wpdb->get_results( $wpdb->prepare( "SELECT id,title,category FROM `{$activity_table}` WHERE organiser_id=%d AND status='published' ORDER BY title LIMIT 30", $id ), ARRAY_A );
        // Public locations are derived only from published activity assignments.
        $venue_table = $wpdb->prefix . 'venues';
        $link_table = $wpdb->prefix . 'activity_venues';
        $locations = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT v.name,v.town FROM `{$venue_table}` v INNER JOIN `{$link_table}` av ON av.venue_id=v.id INNER JOIN `{$activity_table}` a ON a.id=av.activity_id WHERE a.organiser_id=%d AND a.status='published' ORDER BY v.town,v.name LIMIT 30",
            $id
        ), ARRAY_A );
        $extras = get_option( 'bh3_storefront_' . $id, array() );
        if ( ! is_array( $extras ) ) { $extras = array(); }
        // Private previews are restricted to the linked organiser and administrators.
        // Public storefronts require both explicit opt-in and team approval.
        $viewer_organiser = is_user_logged_in() ? absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) : 0;
        $can_preview = current_user_can( 'manage_options' ) || ( $viewer_organiser && $viewer_organiser === $id );
        $is_public = ! empty( $extras['directory_opt_in'] ) && ! empty( $extras['directory_approved'] );
        if ( ! $can_preview && ! $is_public ) {
            return '<p>This business storefront is not available yet.</p>';
        }
        $category_labels = array( 'classes' => 'Classes & activities', 'family-services' => 'Family services', 'pregnancy' => 'Pregnancy & postnatal', 'childcare' => 'Childcare & education', 'shops' => 'Shops & gifts', 'venues' => 'Family-friendly venues', 'other' => 'Other family business' );
        $category = $category_labels[ $extras['business_category'] ?? '' ] ?? '';
        ob_start(); ?>
        <section class="bh-storefront">

          <?php if ( ! empty( $extras['cover_url'] ) ) : ?><img class="bh-scover" src="<?php echo esc_url( $extras['cover_url'] ); ?>" alt="" loading="lazy"><?php endif; ?>
          <header class="bh-shero"><?php if ( ! empty( $extras['logo_url'] ) ) : ?><img class="bh-slogo" src="<?php echo esc_url( $extras['logo_url'] ); ?>" alt="<?php echo esc_attr( $profile['name'] ); ?> logo" loading="lazy"><?php endif; ?><p>BUBBA HUB · BUSINESS PROFILE</p><h1><?php echo esc_html( $profile['name'] ); ?></h1><p>Discover this organiser's activities and get in touch.</p></header>
          <?php if ( $category || ! empty( $extras['areas_served'] ) ) : ?><div class="bh-smeta" style="margin:14px 0"><?php if ( $category ) : ?><span style="background:#245e4a"><?php echo esc_html( $category ); ?></span><?php endif; ?><?php if ( ! empty( $extras['areas_served'] ) ) : ?><span style="background:#245e4a"><?php echo esc_html( $extras['areas_served'] ); ?></span><?php endif; ?></div><?php endif; ?>
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
              <?php foreach ( array( 'facebook_url' => 'Facebook', 'instagram_url' => 'Instagram' ) as $field => $label ) : ?>
                <?php if ( ! empty( $extras[ $field ] ) ) : ?><a class="bh-slink" href="<?php echo esc_url( $extras[ $field ] ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( $label ); ?></a><?php endif; ?>
              <?php endforeach; ?>
              <h2>Where to find us</h2>
              <?php if ( ! $locations ) : ?><p>Venue information will appear when activities are published.</p><?php endif; ?>
              <?php foreach ( $locations as $location ) : ?><p><strong><?php echo esc_html( $location["name"] ); ?></strong><?php if ( $location["town"] ) : ?> · <?php echo esc_html( $location["town"] ); ?><?php endif; ?></p><?php endforeach; ?>
            </aside>
          </div>
        </section>
        <?php return ob_get_clean();
    }
}
