<?php
/**
 * Owner-scoped seven-step class-leader listing wizard.
 * Staging only. Creates drafts; moderation/publishing remains an administrator action.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Leader_Listing_Wizard_3 {
    private static function enabled() {
        return is_user_logged_in() && current_user_can( 'read' )
            && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }

    public static function register() {
        add_shortcode( 'bh_leader_new_listing', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_leader_listing_save', array( __CLASS__, 'save' ) );
    }

    private static function owner_id() {
        return absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
    }

    private static function organiser() {
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        if ( ! self::owner_id() || ! self::table_exists( $table ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT id,name,website_url,contact_email FROM `{$table}` WHERE id=%d LIMIT 1",
            self::owner_id()
        ), ARRAY_A );
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
    }

    private static function columns( $table ) {
        global $wpdb;
        $rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
        return array_fill_keys( array_map( static function( $row ) { return $row['Field']; }, (array) $rows ), true );
    }

    private static function value( $key, $max = 255 ) {
        if ( ! isset( $_POST[$key] ) || ! is_scalar( $_POST[$key] ) ) { return ''; }
        return substr( sanitize_text_field( wp_unslash( (string) $_POST[$key] ) ), 0, $max );
    }

    private static function url( $key ) {
        $raw = self::value( $key, 2048 );
        if ( $raw === '' ) { return ''; }
        $url = esc_url_raw( $raw, array( 'http', 'https' ) );
        return $url && wp_http_validate_url( $url ) ? $url : '';
    }

    private static function field( $name, $label, $value = '', $type = 'text', $attrs = '' ) {
        echo '<label class="bh3-wizard-field"><span>' . esc_html( $label ) . '</span>';
        if ( $type === 'textarea' ) {
            echo '<textarea name="' . esc_attr( $name ) . '" rows="5" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $attrs . '>';
        }
        echo '</label>';
    }

    public static function render() {
        if ( ! self::enabled() ) {
            return '<div class="bh3-wizard-message">Please sign in with your class leader account to create a listing.</div>';
        }
        $organiser = self::organiser();
        if ( ! $organiser ) {
            return '<div class="bh3-wizard-message"><h2>Organiser account needed</h2><p>Your account needs to be linked to an approved Bubba Hub organiser before you can create a listing. Please contact the Bubba Hub team.</p></div>';
        }
        global $wpdb;
        $activities = $wpdb->prefix . 'activities';
        if ( ! self::table_exists( $activities ) ) {
            return '<p>The listing service is not ready yet. Please contact Bubba Hub support.</p>';
        }
        $id = absint( $_GET['activity_id'] ?? 0 );
        $record = null;
        if ( $id ) {
            $record = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM `{$activities}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1",
                $id, (int) $organiser['id']
            ), ARRAY_A );
            if ( ! $record ) { return '<p>This draft is unavailable or is not assigned to your organiser.</p>'; }
        }
        $defaults = array(
            'title' => '', 'description' => '', 'category' => '', 'tags' => '',
            'age_min_months' => '', 'age_max_months' => '', 'price_from' => '',
            'is_free' => 0, 'booking_url' => '', 'website' => $organiser['website_url'] ?? '',
            'contact_email' => $organiser['contact_email'] ?? '', 'contact_phone' => '',
            'leader_email' => $organiser['contact_email'] ?? '', 'what_to_bring' => '',
            'good_to_know' => '', 'booking_method' => 'external', 'image_id' => 0,
        );
        $data = array_merge( $defaults, (array) $record );
        $step = min( 7, max( 1, absint( $_GET['step'] ?? 1 ) ) );
        $steps = array( 'Basics', 'About', 'Extra information', 'Schedule & booking', 'Venues', 'Photos & media', 'Review & submit' );
        ob_start();
        ?>
        <section class="bh3-leader-wizard" aria-labelledby="bh3-wizard-title">
          <style>
          .bh3-leader-wizard{max-width:1050px;margin:0 auto;color:#263b32}.bh3-wizard-head{background:#f0f5ef;border-radius:18px;padding:24px;margin-bottom:20px}.bh3-wizard-head h1{margin:0 0 8px}.bh3-wizard-steps{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;margin:18px 0}.bh3-wizard-step{font-size:12px;text-align:center;padding:9px 4px;border-radius:9px;background:#f4f5f3;color:#53665b}.bh3-wizard-step[aria-current=step]{background:#245b43;color:white;font-weight:700}.bh3-wizard-card{background:#fff;border:1px solid #dce6df;border-radius:16px;padding:24px;box-shadow:0 5px 20px #1e3a2b0a}.bh3-wizard-field{display:block;margin:0 0 18px}.bh3-wizard-field span{display:block;font-weight:650;margin-bottom:7px}.bh3-wizard-field input,.bh3-wizard-field textarea,.bh3-wizard-field select{box-sizing:border-box;width:100%;padding:12px;border:1px solid #bdcbc2;border-radius:9px;font:inherit;background:white}.bh3-wizard-help{color:#627369;font-size:14px}.bh3-wizard-actions{display:flex;justify-content:space-between;gap:12px;margin-top:24px;flex-wrap:wrap}.bh3-wizard-actions a,.bh3-wizard-actions button{display:inline-block;border:0;border-radius:9px;padding:12px 18px;background:#245b43;color:white;text-decoration:none;font-weight:650;cursor:pointer}.bh3-wizard-actions .secondary{background:#edf2ee;color:#294537}.bh3-wizard-review{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.bh3-wizard-review div{padding:12px;background:#f6f8f6;border-radius:9px;overflow-wrap:anywhere}.bh3-wizard-review strong{display:block;font-size:13px;color:#607267}.bh3-wizard-check{display:flex;gap:9px;align-items:flex-start;margin:15px 0}.bh3-wizard-check input{margin-top:5px}@media(max-width:680px){.bh3-wizard-steps{grid-template-columns:repeat(4,minmax(0,1fr))}.bh3-wizard-card{padding:17px}.bh3-wizard-review{grid-template-columns:1fr}.bh3-wizard-step{font-size:11px}}
          </style>
          <header class="bh3-wizard-head">
            <p class="bh3-wizard-help">YOUR LEADER HUB · LISTING CREATOR</p>
            <h1 id="bh3-wizard-title"><?php echo $record ? 'Continue your listing' : 'Create a new listing'; ?></h1>
            <p>Work through the steps at your own pace. You can save a draft and come back later. Your listing will be reviewed before it is published.</p>
            <div class="bh3-wizard-steps" aria-label="Listing steps">
              <?php foreach ( $steps as $index => $label ) : ?><div class="bh3-wizard-step" <?php echo $step === $index + 1 ? 'aria-current="step"' : ''; ?>><strong><?php echo esc_html( $index + 1 ); ?></strong><br><?php echo esc_html( $label ); ?></div><?php endforeach; ?>
            </div>
          </header>
          <?php if ( isset( $_GET['saved'] ) ) : ?><p role="status" class="bh3-wizard-message">Your draft has been saved.</p><?php endif; ?>
          <form class="bh3-wizard-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="bh3_leader_listing_save">
            <input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
            <input type="hidden" name="wizard_step" value="<?php echo esc_attr( $step ); ?>">
            <?php wp_nonce_field( 'bh3_leader_listing_' . (int) $organiser['id'] ); ?>
            <?php if ( $step === 1 ) : ?>
              <h2>Let's start with the basics</h2>
              <p class="bh3-wizard-help">Your listing will be connected to <?php echo esc_html( $organiser['name'] ); ?>.</p>
              <?php self::field( 'title', 'Activity or class name', $data['title'], 'text', 'required maxlength="255" autocomplete="off"' ); ?>
              <?php self::field( 'source_url', 'Website or information page (optional)', $data['website'], 'url', 'placeholder="https://" maxlength="2048"' ); ?>
            <?php elseif ( $step === 2 ) : ?>
              <h2>Tell families about your activity</h2>
              <?php self::field( 'description', 'Description', $data['description'], 'textarea', 'required' ); ?>
              <?php self::field( 'category', 'Main category', $data['category'], 'text', 'maxlength="150" placeholder="e.g. Baby & toddler groups"' ); ?>
              <?php self::field( 'tags', 'Additional categories and keywords', $data['tags'], 'text', 'maxlength="1000" placeholder="e.g. baby, toddler, music, stay and play"' ); ?>
            <?php elseif ( $step === 3 ) : ?>
              <h2>Extra information</h2><p class="bh3-wizard-help">Contact details start with your organiser storefront. You can add activity-specific contact details here without changing your main organiser profile.</p>
              <?php self::field( 'age_min_months', 'Minimum age (months)', $data['age_min_months'], 'number', 'min="0" max="216"' ); ?>
              <?php self::field( 'age_max_months', 'Maximum age (months)', $data['age_max_months'], 'number', 'min="0" max="216"' ); ?>
              <?php self::field( 'contact_email', 'Public activity contact email', $data['contact_email'], 'email', 'maxlength="254"' ); ?>
              <?php self::field( 'contact_phone', 'Contact phone (optional)', $data['contact_phone'], 'tel', 'maxlength="100"' ); ?>
              <?php self::field( 'website', 'Activity website', $data['website'], 'url', 'placeholder="https://"' ); ?>
              <?php self::field( 'what_to_bring', 'What should families bring?', $data['what_to_bring'], 'text', 'maxlength="250"' ); ?>
              <?php self::field( 'good_to_know', 'Anything else useful to know?', $data['good_to_know'], 'textarea' ); ?>
            <?php elseif ( $step === 4 ) : ?>
              <h2>Schedule &amp; booking</h2><p class="bh3-wizard-help">You can add weekly sessions in My Timetable after saving. Choose how families should contact you or book.</p>
              <label class="bh3-wizard-field"><span>How do families join?</span><select name="booking_method"><option value="external" <?php selected( $data['booking_method'], 'external' ); ?>>Book through my external booking page</option><option value="enquiry" <?php selected( $data['booking_method'], 'enquiry' ); ?>>Send me an enquiry through Bubba Hub</option><option value="drop_in" <?php selected( $data['booking_method'], 'drop_in' ); ?>>Drop in — no booking needed</option></select></label>
              <?php self::field( 'booking_url', 'External booking link (if applicable)', $data['booking_url'], 'url', 'placeholder="https://"' ); ?>
              <p class="bh3-wizard-help">Weekly timetable sessions are managed separately, so you can update times without rebuilding your listing. The enquiry option will only be available to families once the verified leader contact and enquiry form have passed testing.</p>
            <?php elseif ( $step === 5 ) : ?>
              <h2>Venues</h2><p>Save your draft first, then use My Venues to search and assign existing shared venues. New venue requests will be added here once the approval workflow is enabled.</p>
              <p class="bh3-wizard-help">Please don't create a duplicate venue. Search by venue name or town in My Venues.</p>
            <?php elseif ( $step === 6 ) : ?>
              <h2>Photos &amp; media</h2><p>Save your draft first, then add a cover photo and gallery from the listing editor. We keep photos separate from contact and schedule details.</p>
              <p class="bh3-wizard-help">Use images you own or have permission to use. Image upload and gallery selection will be connected in the media step.</p>
            <?php else : ?>
              <h2>Review your listing</h2><p>Check the key information before submitting your draft for review.</p>
              <div class="bh3-wizard-review">
                <?php foreach ( array( 'Activity name'=>'title','Description'=>'description','Category'=>'category','Age from (months)'=>'age_min_months','Age to (months)'=>'age_max_months','Contact email'=>'contact_email','Website'=>'website','Booking method'=>'booking_method','Booking link'=>'booking_url' ) as $label=>$key ) : ?>
                  <div><strong><?php echo esc_html( $label ); ?></strong><?php echo esc_html( (string) ( $data[$key] ?? '' ) ?: 'Not provided' ); ?></div>
                <?php endforeach; ?>
              </div>
              <p class="bh3-wizard-help">Saving creates or updates a private draft. It does not publish the listing or confirm any bookings.</p>
            <?php endif; ?>
            <div class="bh3-wizard-actions">
              <?php if ( $step > 1 ) : ?><a class="secondary" href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>$id, 'step'=>$step - 1 ), get_permalink() ) ); ?>">Back</a><?php else : ?><a class="secondary" href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">Cancel</a><?php endif; ?>
              <button type="submit" name="intent" value="<?php echo $step < 7 ? 'next' : 'submit'; ?>"><?php echo $step < 7 ? 'Save &amp; continue' : 'Save draft for review'; ?></button>
            </div>
          </form>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function save() {
        if ( ! self::enabled() ) { wp_die( 'Leader access is unavailable.', '', array( 'response' => 403 ) ); }
        $organiser = self::organiser();
        if ( ! $organiser ) { wp_die( 'A linked organiser account is required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_leader_listing_' . (int) $organiser['id'] );
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( ! self::table_exists( $table ) ) { wp_die( 'Listing storage is unavailable.', '', array( 'response' => 503 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $step = min( 7, max( 1, absint( $_POST['wizard_step'] ?? 1 ) ) );
        $intent = isset( $_POST['intent'] ) && is_scalar( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : 'next';
        $existing = null;
        if ( $id ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $id, (int) $organiser['id'] ), ARRAY_A );
            if ( ! $existing ) { wp_die( 'Draft not found or not owned by this organiser.', '', array( 'response' => 404 ) ); }
        }
        $title = self::value( 'title' );
        if ( $step === 1 && $title === '' && ! $id ) { wp_die( 'Please enter an activity name.', '', array( 'response' => 400 ) ); }
        $min = self::value( 'age_min_months', 3 ); $max = self::value( 'age_max_months', 3 );
        if ( $min !== '' && ( ! ctype_digit( $min ) || (int) $min > 216 ) ) { wp_die( 'Minimum age must be between 0 and 216 months.', '', array( 'response' => 400 ) ); }
        if ( $max !== '' && ( ! ctype_digit( $max ) || (int) $max > 216 ) ) { wp_die( 'Maximum age must be between 0 and 216 months.', '', array( 'response' => 400 ) ); }
        if ( $min !== '' && $max !== '' && (int) $min > (int) $max ) { wp_die( 'Minimum age cannot exceed maximum age.', '', array( 'response' => 400 ) ); }
        $email = sanitize_email( self::value( 'contact_email', 254 ) );
        $method = self::value( 'booking_method', 20 );
        if ( ! in_array( $method, array( 'external', 'enquiry', 'drop_in' ), true ) ) { $method = 'external'; }
        $booking_url = self::url( 'booking_url' );
        if ( $method === 'external' && self::value( 'booking_url', 2048 ) !== '' && ! $booking_url ) { wp_die( 'Please enter a valid booking URL.', '', array( 'response' => 400 ) ); }
        $values = array(
            'organiser_id' => (int) $organiser['id'],
            'title' => $title ?: 'Untitled listing draft',
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'category' => self::value( 'category', 150 ),
            'age_min_months' => $min === '' ? null : (int) $min,
            'age_max_months' => $max === '' ? null : (int) $max,
            'price_from' => null,
            'is_free' => 0,
            'booking_url' => $method === 'external' ? $booking_url : '',
            'website' => self::url( 'website' ),
            'contact_email' => $email,
            'contact_phone' => self::value( 'contact_phone', 100 ),
            'what_to_bring' => self::value( 'what_to_bring', 250 ),
            'good_to_know' => sanitize_textarea_field( wp_unslash( $_POST['good_to_know'] ?? '' ) ),
            'tags' => sanitize_textarea_field( wp_unslash( $_POST['tags'] ?? '' ) ),
            'booking_method' => $method,
            'updated_at' => current_time( 'mysql', true ),
        );
        $columns = self::columns( $table );
        $data = array_intersect_key( $values, $columns );
        if ( ! isset( $columns['organiser_id'], $columns['title'], $columns['status'] ) ) { wp_die( 'The activity table is missing required fields.', '', array( 'response' => 409 ) ); }
        if ( $step === 7 && $intent === 'submit' ) {
            // The request is submitted for moderation, never published automatically.
            $data['status'] = 'pending';
        } else {
            $data['status'] = 'draft';
        }
        if ( $id ) {
            unset( $data['organiser_id'] );
            $saved = $wpdb->update( $table, $data, array( 'id' => $id, 'organiser_id' => (int) $organiser['id'], 'status' => 'draft' ) );
            if ( $saved === false ) { wp_die( 'Could not save your listing draft.', '', array( 'response' => 500 ) ); }
        } else {
            if ( ! isset( $columns['created_at'] ) ) { /* optional timestamp */ } else { $data['created_at'] = current_time( 'mysql', true ); }
            if ( ! isset( $columns['slug'] ) ) { wp_die( 'The activity table is missing its slug field.', '', array( 'response' => 409 ) ); }
            $base = sanitize_title( $title ?: 'new-listing-' . get_current_user_id() );
            $slug = $base; $suffix = 2;
            while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug=%s LIMIT 1", $slug ) ) ) { $slug = $base . '-' . $suffix++; if ( $suffix > 1000 ) { wp_die( 'Unable to create a unique listing URL.', '', array( 'response' => 409 ) ); } }
            $data['slug'] = $slug;
            if ( $wpdb->insert( $table, $data ) === false ) { wp_die( 'Could not create your listing draft.', '', array( 'response' => 500 ) ); }
            $id = (int) $wpdb->insert_id;
        }
        $next = ( $step < 7 && $intent !== 'submit' ) ? $step + 1 : 7;
        $url = add_query_arg( array( 'activity_id' => $id, 'step' => $next, 'saved' => 1 ), home_url( '/leader/new/' ) );
        if ( $step === 7 && $intent === 'submit' ) { $url = add_query_arg( 'submitted', 1, home_url( '/leader/' ) ); }
        wp_safe_redirect( $url );
        exit;
    }
}
