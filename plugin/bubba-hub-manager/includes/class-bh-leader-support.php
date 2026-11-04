<?php
/** Staging-only leader Help & Support page, reusing verified Ask Support modules. */
defined( 'ABSPATH' ) || exit;
final class BH_Leader_Support_3 {
    public static function register() {
        add_shortcode( 'bh_leader_support', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return ''; }
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return '<p>Please sign in to access Leader Help & Support.</p>';
        }
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser ) { return '<p>Link an organiser account to access Leader Help & Support.</p>'; }
        ob_start(); ?>
        <section class="bh-leader-support-page">
            <header class="bh-leader-support-hero">
                <p>LEADER HUB</p><h1>Help &amp; Support</h1>
                <p>Find help with your listings and manage your interest in answering family questions.</p>
            </header>
            <nav class="bh-leader-support-jump" aria-label="Help and support sections">
                <a href="#bh-leader-help-listings">Listing help</a>
                <a href="#leader-ask-inbox">Family questions</a>
                <a href="#leader-ask-support">Specialist settings</a>
            </nav>
            <div class="bh-leader-support-card" id="bh-leader-help-listings">
                <h2>Need help with your listings?</h2>
                <p>Manage your activity details, venues and timetable from your Leader Dashboard. For a problem you cannot resolve, contact the Bubba Hub team.</p>
                <a class="bh-leader-support-link" href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">Open Leader Dashboard</a>
                <a class="bh-leader-support-link" href="<?php echo esc_url( home_url( '/help-support/' ) ); ?>">Help &amp; guidance</a>
            </div>
            <?php
            // Existing modules enforce their own linked-organiser and approval checks.
            echo do_shortcode( '[bh_leader_ask_inbox]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo do_shortcode( '[bh_leader_ask_support]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </section>
        <?php return (string) ob_get_clean();
    }
}
