<?php
/** Staging-only front-end weekly timetable editor for organiser-owned drafts. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_Sessions_3 {
    private static function enabled() {
        return is_user_logged_in() && current_user_can( 'read' ) &&
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function activity( $id ) {
        if ( ! self::enabled() || ! $id ) { return null; }
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $owner ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,title FROM `{$table}` WHERE id=%d AND organiser_id=%d AND status IN ('draft','published') LIMIT 1", $id, $owner ), ARRAY_A );
    }
    public static function register() {
        add_shortcode( 'bh_leader_timetable', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_frontend_session_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_frontend_session_delete', array( __CLASS__, 'delete' ) );
    }
    private static function back( $id, $saved = '' ) {
        $url = add_query_arg( array( 'activity_id' => $id ), home_url( '/leader/timetable/' ) );
        if ( $saved ) { $url = add_query_arg( 'notice', $saved, $url ); }
        wp_safe_redirect( $url ); exit;
    }
    public static function render() {
        $id = absint( $_GET['activity_id'] ?? 0 );
        $activity = self::activity( $id );
        if ( ! $activity ) { return '<p>Timetable unavailable. Sign in and choose one of your activities.</p>'; }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.id,s.day_of_week,s.start_time,s.end_time,s.venue_id,s.term_time_only,s.start_date,s.end_date,s.frequency,v.name AS venue_name FROM `{$sessions}` s LEFT JOIN `{$venues}` v ON v.id=s.venue_id WHERE s.activity_id=%d ORDER BY s.day_of_week,s.start_time,s.id LIMIT 200", $id ), ARRAY_A );
        $assigned = $wpdb->get_results( $wpdb->prepare( "SELECT v.id,v.name,v.town FROM `{$venues}` v INNER JOIN `{$links}` av ON av.venue_id=v.id WHERE av.activity_id=%d ORDER BY v.name LIMIT 200", $id ), ARRAY_A );
        $edit_id = absint( $_GET['edit_session'] ?? 0 );
        $editing = null;
        foreach ( $rows as $row ) { if ( (int) $row['id'] === $edit_id && $row['frequency'] === 'weekly' ) { $editing = $row; } }
        $owner_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        $activity_table = $wpdb->prefix . 'activities';
        $owned_listings = $wpdb->get_results( $wpdb->prepare( "SELECT id,title FROM `{$activity_table}` WHERE organiser_id=%d ORDER BY title LIMIT 200", $owner_id ), ARRAY_A );
        $assignment_table = $wpdb->prefix . 'session_listing_links';
        $assignments_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $assignment_table ) ) ) === $assignment_table;
        $linked_ids = array();
        if ( $assignments_ready && $editing ) {
            $linked_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT activity_id FROM `{$assignment_table}` WHERE session_id=%d", (int) $editing['id'] ) ) );
        }
        $session_titles = get_option( 'bh3_session_titles', array() );
        if ( ! is_array( $session_titles ) ) { $session_titles = array(); }
        $booking_form_id = max( 1, absint( get_option( 'bh3_booking_form_id', 7 ) ) );
        $booking_settings = get_option( 'bh3_session_booking_config', array() );
        if ( ! is_array( $booking_settings ) ) { $booking_settings = array(); }
        $booking = $booking_settings[(int) ( $editing['id'] ?? 0 )] ?? array();
        $enquiry_settings = get_option( 'bh3_session_enquiry_enabled', array() );
        if ( ! is_array( $enquiry_settings ) ) { $enquiry_settings = array(); }
        $picked_date = isset( $_GET['session_date'] ) && is_string( $_GET['session_date'] ) ? sanitize_text_field( wp_unslash( $_GET['session_date'] ) ) : '';
        $picked = $picked_date ? DateTimeImmutable::createFromFormat( '!Y-m-d', $picked_date, wp_timezone() ) : false;
        if ( ! $picked || $picked->format( 'Y-m-d' ) !== $picked_date ) { $picked_date = ''; }
        $days = array( 1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday' );
        ob_start(); ?>
        <section class="bh-leader-timetable">

        
        <header class="bh-thero"><p>BOOKINGS & TIMETABLE</p><h1><?php echo esc_html( $activity['title'] ); ?></h1><p>Plan weekly sessions and keep your schedule accurate.</p></header>
        <p class="bh-thint">These are timetable entries, not bookings. Times follow your WordPress site's time format.</p>
        <?php if ( isset( $_GET['notice'] ) ) : ?><p class="bh-tnotice" role="status"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['notice'] ) ) ); ?></p><?php endif; ?>
        <div class="bh-panel"><h2>Current sessions (<?php echo esc_html( count( $rows ) ); ?>)</h2>
        <?php if ( ! $rows ) : ?><p>No sessions yet.</p><?php else : ?><ul>
        <?php foreach ( $rows as $row ) : ?><li>
        <strong><?php echo esc_html( ( $days[(int) $row['day_of_week']] ?? 'Other' ) . ' ' . wp_date( get_option( 'time_format' ), strtotime( '2000-01-01 ' . $row['start_time'] ) ) . '–' . wp_date( get_option( 'time_format' ), strtotime( '2000-01-01 ' . $row['end_time'] ) ) ); ?></strong>
        <span><?php echo esc_html( $row['venue_name'] ?: 'No venue' ); ?></span><small><?php echo ! empty( $enquiry_settings[(int) $row['id']] ) ? ' · Enquiries enabled' : ' · Enquiries off'; ?></small>
        <div class="bh-session-meta"><?php echo esc_html( $row['term_time_only'] ? 'Term time only' : 'All year' ); ?><?php if ( $row['start_date'] || $row['end_date'] ) : ?> · <?php echo esc_html( $row['start_date'] ? wp_date( get_option( 'date_format' ), strtotime( $row['start_date'] . ' 12:00:00' ) ) : 'Any start' ); ?> – <?php echo esc_html( $row['end_date'] ? wp_date( get_option( 'date_format' ), strtotime( $row['end_date'] . ' 12:00:00' ) ) : 'Ongoing' ); ?><?php endif; ?></div>
        <?php if ( $row['frequency'] === 'weekly' ) : ?>
        <div class="bh-actions"><a class="bh-action" href="<?php echo esc_url( add_query_arg( array( 'activity_id'=>$id,'edit_session'=>(int) $row['id'] ), home_url( '/leader/timetable/' ) ) ); ?>">Edit</a>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_session_delete"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="session_id" value="<?php echo esc_attr( $row['id'] ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_delete_' . $id . '_' . $row['id'] ); ?>
        <button class="bh-delete" type="submit" onclick="return confirm('Remove this weekly session from the timetable?');">Remove</button></form></div>
        <?php else : ?><small>Read-only non-weekly session</small><?php endif; ?>
        </li><?php endforeach; ?></ul><?php endif; ?></div>
        <p class="bh-thint"><a href="<?php echo esc_url( home_url( '/leader/bookings/' ) ); ?>">← Back to calendar</a></p>
        <div class="bh-panel" id="bh-session-editor"><h2><?php echo $editing ? 'Edit weekly session' : 'Add weekly session'; ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_frontend_session_save"><input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="session_id" value="<?php echo esc_attr( $editing['id'] ?? 0 ); ?>">
        <?php wp_nonce_field( 'bh3_frontend_session_' . $id ); ?>
        <p><label for="bh-session-title">Session title</label><input id="bh-session-title" type="text" name="session_title" maxlength="160" required value="<?php echo esc_attr( $session_titles[(int) ( $editing['id'] ?? 0 )] ?? $activity['title'] ); ?>" placeholder="e.g. Little Explorers"></p>
        <div class="bh-session-grid"><div class="bh-wide"><label for="bh-day">Day</label><select id="bh-day" name="day_of_week" required><?php foreach ( $days as $num=>$day ) : ?><option value="<?php echo esc_attr( $num ); ?>" <?php selected( (int) ( $editing['day_of_week'] ?? ( $picked_date ? (int) $picked->format( 'N' ) : 1 ) ), $num ); ?>><?php echo esc_html( $day ); ?></option><?php endforeach; ?></select></div>
        <div><label for="bh-start">Start time</label><input id="bh-start" type="time" name="start_time" value="<?php echo esc_attr( $editing ? substr( $editing['start_time'], 0, 5 ) : '' ); ?>" required></div>
        <div><label for="bh-end">End time</label><input id="bh-end" type="time" name="end_time" value="<?php echo esc_attr( $editing ? substr( $editing['end_time'], 0, 5 ) : '' ); ?>" required></div>
        <div class="bh-wide"><label for="bh-venue">Venue</label><select id="bh-venue" name="venue_id"><option value="0">No venue</option><?php foreach ( $assigned as $venue ) : ?><option value="<?php echo esc_attr( $venue['id'] ); ?>" <?php selected( (int) ( $editing['venue_id'] ?? 0 ), (int) $venue['id'] ); ?>><?php echo esc_html( $venue['name'] . ( $venue['town'] ? ' — ' . $venue['town'] : '' ) ); ?></option><?php endforeach; ?></select></div>
        <div><label for="bh-first">First date (optional)</label><input id="bh-first" type="date" name="start_date" value="<?php echo esc_attr( $editing['start_date'] ?? $picked_date ); ?>">
        </div><div><label for="bh-last">Last date (optional)</label><input id="bh-last" type="date" name="end_date" value="<?php echo esc_attr( $editing['end_date'] ?? $picked_date ); ?>">
        </div></div>
        <fieldset class="bh-panel" style="padding:16px;margin:16px 0;border:1px solid #dce9e0;border-radius:12px">
          <legend><strong>Show this session on your listings</strong></legend>
          <?php if ( $assignments_ready ) : ?>
            <p>Choose all listings that should display this shared session. Editing it updates every assigned listing.</p>
            <?php foreach ( $owned_listings as $listing ) : ?>
              <p><label><input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing['id'] ); ?>" <?php checked( (int) $listing['id'] === $id || in_array( (int) $listing['id'], $linked_ids, true ) ); ?> <?php disabled( (int) $listing['id'] === $id ); ?>> <?php echo esc_html( $listing['title'] ); ?><?php if ( (int) $listing['id'] === $id ) : ?> (primary listing)<?php endif; ?></label></p>
            <?php endforeach; ?>
          <?php else : ?>
            <p>Shared listing assignment will become available after the administrator installs the new assignment table. Existing sessions remain linked to their original listing.</p>
          <?php endif; ?>
        </fieldset>
        <p><label><input type="checkbox" name="allow_enquiry" value="1" <?php checked( ! empty( $enquiry_settings[(int) ( $editing['id'] ?? 0 )] ) ); ?>> Allow families to enquire about this session (Fluent Form 6)</label></p>
        <fieldset class="bh-panel" style="margin:16px 0;padding:16px;border:1px solid #dce9e0;border-radius:12px">
          <legend><strong>Optional booking module</strong></legend>
          <p>Leave this disabled for drop-in classes. Enquiries above are separate from confirmed bookings.</p>
          <p><label><input type="checkbox" name="enable_booking" value="1" <?php checked( ! empty( $booking['enabled'] ) ); ?>> Enable bookings and payments for this session (Fluent Form <?php echo esc_html( $booking_form_id ); ?>)</label></p>
          <div class="bh-session-grid">
            <div><label for="bh-booking-price">Price (£ per place)</label><input id="bh-booking-price" type="number" name="booking_price" min="0" max="100000" step="0.01" value="<?php echo esc_attr( $booking['price'] ?? '' ); ?>" placeholder="0.00"></div>
            <div><label for="bh-booking-capacity">Total spaces</label><input id="bh-booking-capacity" type="number" name="booking_capacity" min="1" max="100000" step="1" value="<?php echo esc_attr( $booking['capacity'] ?? '' ); ?>" placeholder="Optional"></div>
          </div>
          <p class="bh-thint">Fluent Form <?php echo esc_html( $booking_form_id ); ?> is the planned booking and payment form. Until the form integration and payment confirmation are tested, enabling this setting does not accept bookings, collect payments or reserve places.</p>
        </fieldset>
        <p><label><input type="checkbox" name="term_time_only" value="1" <?php checked( (int) ( $editing['term_time_only'] ?? 0 ), 1 ); ?>> Term time only</label></p>
        <?php if ( ! $assigned ) : ?><p class="bh-thint">No venues linked yet. <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/venues/' ) ) ); ?>">Add a venue</a> before assigning a location to sessions.</p><?php endif; ?>
        <div class="bh-session-actions-footer"><button type="submit"><?php echo $editing ? 'Save session' : 'Add session'; ?></button>
        <?php if ( $editing ) : ?><a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/timetable/' ) ) ); ?>">Cancel editing</a><?php endif; ?></div>
        </form></div></section>
        <?php return ob_get_clean();
    }
    public static function save() {
        if ( ! self::enabled() ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_frontend_session_' . $id );
        if ( ! self::activity( $id ) ) { wp_die( 'Activity not found.', '', array( 'response'=>403 ) ); }
        $title = isset( $_POST['session_title'] ) && is_string( $_POST['session_title'] ) ? sanitize_text_field( wp_unslash( $_POST['session_title'] ) ) : '';
        if ( $title === '' || strlen( $title ) > 160 ) { wp_die( 'Enter a session title (up to 160 characters).', '', array( 'response'=>400 ) ); }
        $day = filter_var( wp_unslash( $_POST['day_of_week'] ?? '' ), FILTER_VALIDATE_INT );
        $start = (string) wp_unslash( $_POST['start_time'] ?? '' );
        $end = (string) wp_unslash( $_POST['end_time'] ?? '' );
        if ( $day === false || $day < 1 || $day > 7 || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end ) || $end <= $start ) { wp_die( 'Invalid day or times.', '', array( 'response'=>400 ) ); }
        $dates = array();
        foreach ( array( 'start_date', 'end_date' ) as $field ) {
            $value = (string) wp_unslash( $_POST[$field] ?? '' );
            $date = $value ? DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : null;
            if ( $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) { wp_die( 'Invalid date.', '', array( 'response'=>400 ) ); }
            $dates[$field] = $value ?: null;
        }
        if ( $dates['start_date'] && $dates['end_date'] && $dates['start_date'] === $dates['end_date'] ) {
            $single_day = DateTimeImmutable::createFromFormat( '!Y-m-d', $dates['start_date'], wp_timezone() );
            if ( ! $single_day || (int) $single_day->format( 'N' ) !== (int) $day ) { wp_die( 'The selected day must match the single session date.', '', array( 'response'=>400 ) ); }
        }
        if ( $dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date'] ) { wp_die( 'Invalid date range.', '', array( 'response'=>400 ) ); }
        $enabled = ! empty( $_POST['enable_booking'] );
        $raw_price = isset( $_POST['booking_price'] ) && is_scalar( $_POST['booking_price'] ) ? trim( (string) wp_unslash( $_POST['booking_price'] ) ) : '';
        $raw_capacity = isset( $_POST['booking_capacity'] ) && is_scalar( $_POST['booking_capacity'] ) ? trim( (string) wp_unslash( $_POST['booking_capacity'] ) ) : '';
        if ( $enabled ) {
            if ( $raw_price === '' || ! preg_match( '/^[0-9]{1,6}(\\.[0-9]{1,2})?$/', $raw_price ) || (float) $raw_price > 100000 ) { wp_die( 'Enter a valid price, including 0 for free sessions.', '', array( 'response'=>400 ) ); }
            if ( $raw_capacity !== '' && ( ! ctype_digit( $raw_capacity ) || (int) $raw_capacity < 1 || (int) $raw_capacity > 100000 ) ) { wp_die( 'Spaces must be a positive whole number.', '', array( 'response'=>400 ) ); }
        }
        global $wpdb;
        $venue = absint( $_POST['venue_id'] ?? 0 );
        if ( $venue ) {
            $links = $wpdb->prefix . 'activity_venues';
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$links}` WHERE activity_id=%d AND venue_id=%d LIMIT 1", $id, $venue ) ) ) { wp_die( 'Venue not assigned.', '', array( 'response'=>400 ) ); }
        }
        $table = $wpdb->prefix . 'sessions';
        $session = absint( $_POST['session_id'] ?? 0 );
        if ( $session && $wpdb->get_var( $wpdb->prepare( "SELECT frequency FROM `{$table}` WHERE id=%d AND activity_id=%d", $session, $id ) ) !== 'weekly' ) { wp_die( 'Session not editable.', '', array( 'response'=>403 ) ); }
        // Validate listing assignments before writing any session data.
        $assignment_table = $wpdb->prefix . 'session_listing_links';
        $assignments_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $assignment_table ) ) ) === $assignment_table;
        $selected = array( $id );
        if ( $assignments_ready ) {
            $posted = isset( $_POST['listing_ids'] ) && is_array( $_POST['listing_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['listing_ids'] ) ) : array();
            $selected = array_values( array_unique( array_filter( array_merge( $selected, $posted ) ) ) );
            $owner_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
            $activity_table = $wpdb->prefix . 'activities';
            $allowed = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$activity_table}` WHERE organiser_id=%d", $owner_id ) ) );
            if ( array_diff( $selected, $allowed ) ) { wp_die( 'You can only assign your own listings.', '', array( 'response'=>403 ) ); }
        }
        $now = current_time( 'mysql', true );
        $data = array( 'activity_id'=>$id, 'venue_id'=>$venue ?: null, 'day_of_week'=>$day, 'start_time'=>$start . ':00', 'end_time'=>$end . ':00', 'start_date'=>$dates['start_date'], 'end_date'=>$dates['end_date'], 'frequency'=>'weekly', 'term_time_only'=>empty( $_POST['term_time_only'] ) ? 0 : 1, 'updated_at'=>$now );
        if ( ! self::activity( $id ) ) { wp_die( 'Ownership changed.', '', array( 'response'=>403 ) ); }
        if ( $session ) { $result = $wpdb->update( $table, $data, array( 'id'=>$session, 'activity_id'=>$id ) ); }
        else { $data['status']='scheduled'; $data['created_at']=$now; $result=$wpdb->insert( $table, $data ); }
        if ( $result === false ) { wp_die( 'Could not save session.', '', array( 'response'=>500 ) ); }
        $saved_session_id = $session ?: (int) $wpdb->insert_id;
        $titles = get_option( 'bh3_session_titles', array() );
        if ( ! is_array( $titles ) ) { $titles = array(); }
        $titles[$saved_session_id] = $title;
        update_option( 'bh3_session_titles', $titles, false );
        if ( $saved_session_id ) {
            $flags = get_option( 'bh3_session_enquiry_enabled', array() );
            if ( ! is_array( $flags ) ) { $flags = array(); }
            if ( ! empty( $_POST['allow_enquiry'] ) ) { $flags[$saved_session_id] = 1; }
            else { unset( $flags[$saved_session_id] ); }
            update_option( 'bh3_session_enquiry_enabled', $flags, false );
        }

        $booking_configs = get_option( 'bh3_session_booking_config', array() );
        if ( ! is_array( $booking_configs ) ) { $booking_configs = array(); }
        if ( $enabled ) {
            $booking_configs[$saved_session_id] = array( 'enabled'=>1, 'price'=>number_format( (float) $raw_price, 2, '.', '' ), 'capacity'=>$raw_capacity === '' ? null : (int) $raw_capacity );
        } else { unset( $booking_configs[$saved_session_id] ); }
        update_option( 'bh3_session_booking_config', $booking_configs, false );
        if ( $assignments_ready ) {
            // Insert missing assignments first; retain existing links if an insert fails.
            $existing = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT activity_id FROM `{$assignment_table}` WHERE session_id=%d", $saved_session_id ) ) );
            foreach ( array_diff( $selected, $existing ) as $listing_id ) {
                if ( false === $wpdb->insert( $assignment_table, array( 'session_id'=>$saved_session_id, 'activity_id'=>$listing_id ), array( '%d', '%d' ) ) ) {
                    wp_die( 'Could not assign session to listings. Existing assignments were retained.', '', array( 'response'=>500 ) );
                }
            }
            foreach ( array_diff( $existing, $selected ) as $listing_id ) {
                $wpdb->delete( $assignment_table, array( 'session_id'=>$saved_session_id, 'activity_id'=>$listing_id ), array( '%d', '%d' ) );
            }
        }
        self::back( $id, 'Session saved.' );
    }
    public static function delete() {
        if ( ! self::enabled() ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $session = absint( $_POST['session_id'] ?? 0 );
        check_admin_referer( 'bh3_frontend_delete_' . $id . '_' . $session );
        if ( ! self::activity( $id ) || ! $session ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        global $wpdb;
        $table = $wpdb->prefix . 'sessions';
        $result = $wpdb->delete( $table, array( 'id'=>$session, 'activity_id'=>$id, 'frequency'=>'weekly' ) );
        if ( $result === false ) { wp_die( 'Could not remove session.', '', array( 'response'=>500 ) ); }
        if ( $result ) {
            $assignment_table = $wpdb->prefix . 'session_listing_links';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $assignment_table ) ) ) === $assignment_table ) {
                $wpdb->delete( $assignment_table, array( 'session_id'=>$session ), array( '%d' ) );
            }
        }
        if ( $result ) {
            $flags = get_option( 'bh3_session_enquiry_enabled', array() );
            if ( is_array( $flags ) ) { unset( $flags[$session] ); update_option( 'bh3_session_enquiry_enabled', $flags, false ); }
        }
        if ( $result ) {
            $configs = get_option( 'bh3_session_booking_config', array() );
            if ( is_array( $configs ) ) { unset( $configs[$session] ); update_option( 'bh3_session_booking_config', $configs, false ); }
        }
        if ( $result ) {
            $titles = get_option( 'bh3_session_titles', array() );
            if ( is_array( $titles ) ) { unset( $titles[$session] ); update_option( 'bh3_session_titles', $titles, false ); }
        }
        self::back( $id, 'Session removed.' );
    }
}
