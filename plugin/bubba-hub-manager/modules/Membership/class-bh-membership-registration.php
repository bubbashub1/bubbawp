<?php
/**
 * Bubba Hub public membership pricing and registration page.
 *
 * Shortcode:
 * [bh_membership_registration family_button_id="123" leader_button_id="456"]
 */
defined( 'ABSPATH' ) || exit;

final class BH_Membership_Registration_3 {
    public static function register(): void {
        add_shortcode( 'bh_membership_registration', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'family_button_id' => 0,
                'leader_button_id' => 0,
                'free_url'         => home_url( '/membership-join/' ),
            ),
            $atts,
            'bh_membership_registration'
        );

        $family_button_id = absint( $atts['family_button_id'] );
        $leader_button_id = absint( $atts['leader_button_id'] );
        $free_url         = esc_url( $atts['free_url'] );
        $swpm_ready       = shortcode_exists( 'swpm_payment_button' );

        ob_start();
        ?>
        <section class="bh-membership-page" aria-labelledby="bh-membership-title">
            <header class="bh-membership-intro">
                <p class="bh-membership-eyebrow">BUBBA HUB MEMBERSHIP</p>
                <h1 id="bh-membership-title">Find the membership that fits you</h1>
                <p>Whether you're planning family days out or running local classes, there's a place for you at Bubba Hub.</p>
            </header>

            <div class="bh-membership-cards">
                <article class="bh-membership-card bh-membership-family">
                    <div class="bh-membership-icon" aria-hidden="true">♡</div>
                    <p class="bh-membership-audience">FOR PARENTS &amp; CARERS</p>
                    <h2>Family Pro</h2>
                    <p class="bh-membership-price"><span>£20</span> <small>/ year</small></p>
                    <p class="bh-membership-description">Make the most of your family's Bubba Hub experience.</p>
                    <ul>
                        <li>Family-focused membership</li>
                        <li>Keep your family account in one place</li>
                        <li>Family Pro membership benefits</li>
                    </ul>
                    <div class="bh-membership-action">
                        <?php
                        if ( $swpm_ready && $family_button_id ) {
                            echo wp_kses_post( do_shortcode( '[swpm_payment_button id="' . $family_button_id . '"]' ) );
                        } else {
                            self::render_unconfigured_button( 'Family Pro' );
                        }
                        ?>
                    </div>
                    <p class="bh-membership-note">Annual membership: £20, billed yearly.</p>
                </article>

                <article class="bh-membership-card bh-membership-leader">
                    <div class="bh-membership-icon" aria-hidden="true">✳</div>
                    <p class="bh-membership-audience">FOR CLASS LEADERS &amp; ORGANISERS</p>
                    <h2>Leader Pro</h2>
                    <p class="bh-membership-price"><span>£70</span> <small>/ year</small></p>
                    <p class="bh-membership-description">More ways to manage and grow your presence on Bubba Hub.</p>
                    <ul>
                        <li>Leader Pro membership</li>
                        <li>Tools and benefits for your organiser account</li>
                        <li>Leader Pro membership benefits</li>
                    </ul>
                    <div class="bh-membership-action">
                        <?php
                        if ( $swpm_ready && $leader_button_id ) {
                            echo wp_kses_post( do_shortcode( '[swpm_payment_button id="' . $leader_button_id . '"]' ) );
                        } else {
                            self::render_unconfigured_button( 'Leader Pro' );
                        }
                        ?>
                    </div>
                    <p class="bh-membership-note">Annual membership: £70, billed yearly.</p>
                </article>
            </div>

            <div class="bh-membership-free">
                <div>
                    <h2>Just getting started?</h2>
                    <p>You can still create a free Bubba Hub account. You can explore the platform and upgrade when you're ready.</p>
                </div>
                <a class="bh-membership-free-link" href="<?php echo $free_url; ?>">Explore free registration</a>
            </div>

            <p class="bh-membership-login">Already have an account? <a href="<?php echo esc_url( home_url( '/auth/' ) ); ?>">Log in</a>.</p>
            <p class="bh-membership-terms">Full payment, renewal and cancellation information will be shown before you complete checkout.</p>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_unconfigured_button( string $plan_name ): void {
        if ( current_user_can( 'manage_options' ) ) {
            echo '<p class="bh-membership-setup-notice">Checkout button not connected for ' . esc_html( $plan_name ) . '. Add the Simple Membership payment button ID to this page shortcode.</p>';
        } else {
            echo '<p class="bh-membership-unavailable">Online checkout is being prepared. Please check back shortly.</p>';
        }
    }
}
BH_Membership_Registration_3::register();
