<?php
/**
 * Family Hub 3.0 presentation-only foundation.
 * No legacy auth assumptions, schema writes, or personal data reads.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Family_Hub_3 {
    public static function register() {
        add_shortcode( 'bubbahub_family_hub', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
    }

    public static function styles() {
        if ( ! is_singular() ) { return; }
        $post = get_post();
        if ( ! $post || ! has_shortcode( $post->post_content, 'bubbahub_family_hub' ) ) { return; }
        wp_enqueue_style(
            'bh3-family-hub',
            plugins_url( 'assets/family-hub.css', dirname( __DIR__, 2 ) . '/bubba-hub-manager.php' ),
            array(),
            defined( 'BUBBA_HUB_MANAGER_VERSION' ) ? BUBBA_HUB_MANAGER_VERSION : '0.1.0'
        );
    }

    public static function render() {
        // FluentAuth should establish the standard WordPress session; no plugin-specific hooks assumed.
        // This is an authentication UX gate, NOT permission to access legacy bh_users records.
        $wp_signed_in = is_user_logged_in();
        $return_url = is_singular() ? get_permalink() : home_url( '/' );
        $login_url = wp_login_url( $return_url );
        $sections = array(
            array( 'My family', 'Family profiles will appear here once account mapping is verified.' ),
            array( 'Saved activities', 'Your favourites will connect to existing activity IDs.' ),
            array( 'Family planner', 'Calendar and recurring sessions are coming after planner parity testing.' ),
            array( 'Discover', 'Find age-suitable activities without exposing family details.' ),
        );
        ob_start();
        ?>
        <section class="bh3-family" aria-labelledby="bh3-family-heading">
          <div class="bh3-family__hero">
            <p class="bh3-family__eyebrow"><?php echo esc_html__( 'Bubba Hub · My Hub', 'bubba-hub-manager' ); ?></p>
            <h1 id="bh3-family-heading"><?php echo esc_html__( 'Your family, all in one place', 'bubba-hub-manager' ); ?></h1>
            <p><?php echo esc_html__( 'Plan days out, keep favourite activities together and make family time easier.', 'bubba-hub-manager' ); ?></p>
          </div>
          <?php if ( ! $wp_signed_in ) : ?>
            <div class="bh3-family__notice" role="status">
              <p><?php echo esc_html__( 'Sign in to access your Family Hub. Your existing family data will be connected after account verification.', 'bubba-hub-manager' ); ?></p>
              <p><a class="bh3-family__login" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Sign in', 'bubba-hub-manager' ); ?></a></p>
            </div>
          <?php else : ?>
            <div class="bh3-family__notice" role="status"><?php echo esc_html__( 'Signed in to WordPress. Family account linking is not yet enabled; no private family data is displayed.', 'bubba-hub-manager' ); ?></div>
          <?php endif; ?>
          <div class="bh3-family__grid">
            <?php foreach ( $sections as $section ) : ?>
              <article class="bh3-family__card">
                <h2><?php echo esc_html( $section[0] ); ?></h2>
                <p><?php echo esc_html( $section[1] ); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
