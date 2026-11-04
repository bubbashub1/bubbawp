<?php
/** Staging-only, opt-in business directory preview. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Business_Directory_3 {
    public static function register() {
        add_shortcode( 'bh_business_directory', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_directory_approve', array( __CLASS__, 'approve' ) );
    }
    public static function approve() {
        if ( ! current_user_can( 'manage_options' ) || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { wp_die( 'Administrator access required.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['business_id'] ?? 0 );
        check_admin_referer( 'bh3_directory_approve_' . $id );
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        if ( ! $id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id=%d", $id ) ) ) { wp_die( 'Business not found.', '', array( 'response' => 404 ) ); }
        $key = 'bh3_storefront_' . $id;
        $extras = get_option( $key, array() );
        if ( ! is_array( $extras ) || empty( $extras['directory_opt_in'] ) ) { wp_die( 'Business has not opted in.', '', array( 'response' => 400 ) ); }
        $extras['directory_approved'] = ! empty( $_POST['approved'] ) ? 1 : 0;
        $extras['directory_reviewed_at'] = current_time( 'mysql', true );
        $extras['directory_reviewed_by'] = get_current_user_id();
        update_option( $key, $extras, false );
        wp_safe_redirect( add_query_arg( 'directory_reviewed', 1, home_url( '/businesses/' ) ) );
        exit;
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        // Until approval and publication workflows exist, only team administrators can preview directory opt-ins.
        if ( ! current_user_can( 'manage_options' ) ) { return '<p>The business directory is coming soon.</p>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        $rows = $wpdb->get_results( "SELECT id,name,description FROM `{$table}` ORDER BY name ASC LIMIT 300", ARRAY_A );
        $cards = array();
        foreach ( (array) $rows as $row ) {
            $extras = get_option( 'bh3_storefront_' . (int) $row['id'], array() );
            if ( ! is_array( $extras ) || empty( $extras['directory_opt_in'] ) ) { continue; }
            $cards[] = array( 'profile' => $row, 'extras' => $extras );
        }
        $categories = array( 'classes' => 'Classes & activities', 'family-services' => 'Family services', 'pregnancy' => 'Pregnancy & postnatal', 'childcare' => 'Childcare & education', 'shops' => 'Shops & gifts', 'venues' => 'Family-friendly venues', 'other' => 'Other family business' );
        ob_start(); ?>
        <section class="bh-business-directory">

          <header class="bh-dir-head"><h2>Business directory preview</h2><p>Administrator-only staging review of opted-in family businesses. Not publicly published.</p></header>
          <div class="bh-dir-controls">
            <input type="search" class="bh-dir-search" aria-label="Search businesses" placeholder="Search businesses or areas">
            <select class="bh-dir-category" aria-label="Filter by category"><option value="">All categories</option>
            <?php foreach ( $categories as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
            </select>
          </div>
          <p class="bh-dir-count" role="status"><?php echo count( $cards ); ?> businesses awaiting directory launch</p>
          <p class="bh-dir-empty" hidden>No businesses match those filters. Try another search or category.</p>
          <div class="bh-dir-grid">
            <?php foreach ( $cards as $card ) : $p = $card['profile']; $e = $card['extras']; ?>
              <article class="bh-dir-card" data-category="<?php echo esc_attr( $e['business_category'] ?? '' ); ?>" data-search="<?php echo esc_attr( strtolower( $p['name'] . ' ' . ( $e['areas_served'] ?? '' ) . ' ' . ( $categories[ $e['business_category'] ?? '' ] ?? '' ) ) ); ?>">
                <?php if ( ! empty( $e['logo_url'] ) ) : ?><img src="<?php echo esc_url( $e['logo_url'] ); ?>" alt="" loading="lazy"><?php endif; ?>
                <h3><?php echo esc_html( $p['name'] ); ?></h3>
                <p><strong><?php echo ! empty( $e['directory_approved'] ) ? 'Approved for launch' : 'Awaiting approval'; ?></strong></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="bh3_directory_approve">
                  <input type="hidden" name="business_id" value="<?php echo esc_attr( $p['id'] ); ?>">
                  <input type="hidden" name="approved" value="<?php echo empty( $e['directory_approved'] ) ? '1' : '0'; ?>">
                  <?php wp_nonce_field( 'bh3_directory_approve_' . (int) $p['id'] ); ?>
                  <button type="submit"><?php echo empty( $e['directory_approved'] ) ? 'Approve for launch' : 'Revoke approval'; ?></button>
                </form>
                <?php if ( ! empty( $e['business_category'] ) ) : ?><p><?php echo esc_html( $categories[ $e['business_category'] ] ?? '' ); ?></p><?php endif; ?>
                <?php if ( ! empty( $e['areas_served'] ) ) : ?><p><?php echo esc_html( $e['areas_served'] ); ?></p><?php endif; ?>
                <p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $p['description'] ?? '' ), 22 ) ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( 'business_id', (int) $p['id'], home_url( '/leader/about/' ) ) ); ?>">View business profile</a>
              </article>
            <?php endforeach; ?>
          </div>
          <script>
          (function(){const root=document.currentScript.closest('.bh-business-directory');if(!root)return;
            const search=root.querySelector('.bh-dir-search'),category=root.querySelector('.bh-dir-category'),count=root.querySelector('.bh-dir-count'),cards=Array.from(root.querySelectorAll('.bh-dir-card'));
            function filter(){const q=search.value.trim().toLowerCase(),c=category.value;let visible=0;cards.forEach(card=>{const show=(!q||card.dataset.search.includes(q))&&(!c||card.dataset.category===c);card.hidden=!show;if(show)visible++;});count.textContent=visible+' businesses';root.querySelector('.bh-dir-empty').hidden=visible!==0;}
            search.addEventListener('input',filter);category.addEventListener('change',filter);filter();
          })();
          </script>
        </section>
        <?php return ob_get_clean();
    }
}
