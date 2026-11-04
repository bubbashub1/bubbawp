<?php
/** Plain-language booking/enquiry terms page content. Legal review required before publication. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Booking_Terms_3 {
    public static function register() { add_shortcode( 'bh_booking_terms', array( __CLASS__, 'render' ) ); }
    public static function render() {
        ob_start(); ?>
        <article class="bh-booking-terms">
          <header><p>BUBBA HUB · FAMILY BOOKINGS</p><h1>Booking Terms &amp; Conditions</h1><p>Last reviewed: <?php echo esc_html( wp_date( get_option('date_format') ) ); ?></p></header>
          <section><h2>1. About Bubba Hub</h2><p>Bubba Hub helps families discover activities and contact the independent class leaders, organisers and venues who provide them. Unless a page clearly says otherwise, the activity is provided by the named organiser, not by Bubba Hub. The organiser is responsible for running the activity, confirming availability and explaining its own participation requirements.</p></section>
          <section><h2>2. An enquiry is not a booking</h2><p>Submitting an enquiry through Bubba Hub does not reserve a place, create a confirmed booking, guarantee availability or confirm enrolment. The class leader or organiser will contact you with further information. Your place is confirmed only when the organiser expressly confirms it and any further steps they require have been completed.</p></section>
          <section><h2>3. Availability and joining instructions</h2><p>Schedules, prices, age guidance, venues and availability can change. Please check the details directly with the organiser before travelling or making arrangements. The organiser will explain any waiting list, trial session, registration or joining requirements.</p></section>
          <section><h2>4. Payments</h2><p>An enquiry submitted through the Bubba Hub enquiry form does not take payment. Any payment arrangements, fees, refunds, deposits or payment deadlines must be agreed directly with the organiser using their stated process. Do not send payment details through an enquiry message.</p></section>
          <section><h2>5. Changes and cancellations</h2><p>Organisers are responsible for notifying families about changes or cancellations to their activities and for explaining any refund or rearrangement arrangements. If you can no longer attend, contact the organiser directly using the contact details they provide.</p></section>
          <section><h2>6. Family information and privacy</h2><p>Only provide information needed to respond to your enquiry. If you choose a family profile and separately consent to sharing a child's gender and year of birth, that limited information may be shared with the relevant class leader to help them respond. The full date of birth, pregnancy due date and unrelated family profile details should not be shared through this consent. Please read the <a href="<?php echo esc_url( get_privacy_policy_url() ?: home_url('/privacy-policy/') ); ?>">Privacy Policy</a> for information about personal data.</p></section>
          <section><h2>7. Safeguarding and suitability</h2><p>Parents and carers should discuss any individual needs, accessibility requirements, allergies or safeguarding questions directly with the organiser before attending. Organisers remain responsible for the safe delivery of their activities and for their own safeguarding arrangements.</p></section>
          <section><h2>8. Problems or complaints</h2><p>For questions about an activity, availability, payment or attendance, contact the organiser first. If your concern relates to the Bubba Hub website, an enquiry form or how Bubba Hub handles your information, contact the Bubba Hub team through the website's Help &amp; Support area.</p></section>
          <section><h2>9. Website information and responsibility</h2><p>Bubba Hub aims to keep directory information useful and accurate but cannot guarantee that third-party activity information, availability or organiser services will remain unchanged. Nothing in these terms limits any rights or remedies that cannot lawfully be excluded under UK law.</p></section>
          <section><h2>10. Updates</h2><p>These terms may be updated as the service develops. The version displayed on this page applies at the time you submit an enquiry. Please check this page again if you make a later enquiry.</p></section>
          <p class="bh-booking-terms__notice"><strong>Important:</strong> This is draft website wording and must be reviewed by a suitably qualified UK legal adviser before publication.</p>
        </article>
        <?php return ob_get_clean();
    }
}
