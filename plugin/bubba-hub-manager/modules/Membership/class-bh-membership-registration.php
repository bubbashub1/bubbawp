<?php
/**
 * Bubba Hub public membership comparison and registration page.
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
                <h1 id="bh-membership-title">Choose the membership that fits you</h1>
                <p>Whether you're here as a parent or carer, or you're running local classes, there's a place for you at Bubba Hub.</p>
            </header>

            <div class="bh-membership-table-wrap" role="region" aria-label="Membership price comparison" tabindex="0">
                <table class="bh-membership-comparison">
                    <thead>
                        <tr class="bh-membership-group-row">
                            <th class="bh-membership-corner" scope="col"></th>
                            <th class="bh-membership-group bh-membership-family-group" colspan="2" scope="colgroup">Family</th>
                            <th class="bh-membership-group bh-membership-leader-group" colspan="2" scope="colgroup">Leader</th>
                        </tr>
                        <tr class="bh-membership-plan-row">
                            <th class="bh-membership-feature-heading" scope="col">Membership</th>
                            <th scope="col">Free</th>
                            <th scope="col" class="bh-membership-pro-heading">Family Pro</th>
                            <th scope="col">Free</th>
                            <th scope="col" class="bh-membership-pro-heading">Leader Pro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bh-membership-price-row">
                            <th scope="row">Annual price</th>
                            <td><strong>£0</strong><span class="bh-membership-period">Always free</span></td>
                            <td class="bh-membership-pro-cell"><strong>£20</strong><span class="bh-membership-period">Per year</span></td>
                            <td><strong>£0</strong><span class="bh-membership-period">Always free</span></td>
                            <td class="bh-membership-pro-cell"><strong>£70</strong><span class="bh-membership-period">Per year</span></td>
                        </tr>
                        <tr class="bh-membership-action-row">
                            <th scope="row">Get started</th>
                            <td><a class="bh-membership-choose bh-membership-choose-free" href="<?php echo $free_url; ?>">Join free</a></td>
                            <td class="bh-membership-pro-cell">
                                <?php
                                if ( $swpm_ready && $family_button_id ) {
                                    echo do_shortcode( '[swpm_payment_button id="' . $family_button_id . '"]' );
                                } else {
                                    self::render_unconfigured_button( 'Family Pro' );
                                }
                                ?>
                            </td>
                            <td><a class="bh-membership-choose bh-membership-choose-free" href="<?php echo $free_url; ?>">Join free</a></td>
                            <td class="bh-membership-pro-cell">
                                <?php
                                if ( $swpm_ready && $leader_button_id ) {
                                    echo do_shortcode( '[swpm_payment_button id="' . $leader_button_id . '"]' );
                                } else {
                                    self::render_unconfigured_button( 'Leader Pro' );
                                }
                                ?>
                            </td>
                        </tr>
                        <?php
                        /*
                         * Reserved comparison rows. Add feature labels and per-plan
                         * values here when the owner has confirmed the Pro benefits.
                         * Intentionally no invented feature claims are displayed.
                         */
                        for ( $row = 0; $row < 5; $row++ ) :
                            ?>
                            <tr class="bh-membership-benefit-placeholder" aria-hidden="true">
                                <th scope="row"></th>
                                <td></td><td class="bh-membership-pro-cell"></td>
                                <td></td><td class="bh-membership-pro-cell"></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <p class="bh-membership-comparison-note">Start with a free account and upgrade whenever you're ready. Pro membership is billed annually.</p>
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
