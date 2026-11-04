<?php
/** Staging-only Leader Account Settings. WordPress account is the source of truth. */
defined( 'ABSPATH' ) || exit;
final class BH_Leader_Account_3 {
    public static function register() {
        add_shortcode( 'bh_leader_account', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_leader_account_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_leader_notifications_save', array( __CLASS__, 'save_notifications' ) );
    }
    private static function allowed(): bool {
        return is_user_logged_in() && current_user_can( 'read' )
            && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function save(): void {
        if ( ! self::allowed() ) wp_die( 'Not authorised.', '', array( 'response' => 403 ) );
        check_admin_referer( 'bh3_leader_account_save' );
        $name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        if ( $name === '' || mb_strlen( $name ) > 100 ) wp_die( 'Enter a display name of up to 100 characters.', '', array( 'response' => 400 ) );
        $user_id = get_current_user_id();
        $result = wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        if ( is_wp_error( $result ) ) wp_die( 'Your account could not be updated.', '', array( 'response' => 503 ) );
        wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/leader/account/' ) ) );
        exit;
    }
    private static function notification_options(): array {
        return array(
            'booking_alerts' => 'Booking updates',
            'listing_updates' => 'Listing and approval updates',
            'support_messages' => 'Ask Support messages',
            'weekly_digest' => 'Weekly summary',
        );
    }
    public static function save_notifications(): void {
        if ( ! self::allowed() ) wp_die( 'Not authorised.', '', array( 'response' => 403 ) );
        check_admin_referer( 'bh3_leader_notifications_save' );
        $selected = isset( $_POST['bh_leader_notifications'] ) && is_array( $_POST['bh_leader_notifications'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['bh_leader_notifications'] ) ) : array();
        $preferences = array();
        foreach ( self::notification_options() as $key => $label ) {
            $preferences[ $key ] = in_array( $key, $selected, true );
        }
        $preferences['updated_at'] = current_time( 'mysql', true );
        update_user_meta( get_current_user_id(), 'bh3_leader_notification_preferences', $preferences );
        wp_safe_redirect( add_query_arg( 'notifications_saved', '1', home_url( '/leader/account/' ) ) . '#bh-leader-notifications' );
        exit;
    }
    public static function render(): string {
        if ( ! self::allowed() ) return '<p>Please sign in to manage your account.</p>';
        $user = wp_get_current_user();
        $organiser_id = absint( get_user_meta( $user->ID, 'bh3_organiser_id', true ) );
        $organiser_name = '';
        if ( $organiser_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'organisers';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
                $organiser_name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$table}` WHERE id=%d LIMIT 1", $organiser_id ) );
            }
        }
        ob_start(); ?>
        <section class="bh-leader-account">
          <header class="bh-leader-account-hero"><p>LEADER HUB</p><h1>Account settings</h1><p>Keep your personal sign-in details separate from your public business profile.</p></header>
          <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><p class="bh-leader-account-success" role="status">Your display name has been updated.</p><?php endif; ?>
          <div class="bh-leader-account-card">
            <h2>Your account</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <input type="hidden" name="action" value="bh3_leader_account_save">
              <?php wp_nonce_field( 'bh3_leader_account_save' ); ?>
              <label for="bh-leader-display-name">Display name</label>
              <input id="bh-leader-display-name" name="display_name" maxlength="100" required value="<?php echo esc_attr( $user->display_name ); ?>">
              <label for="bh-leader-account-email">Sign-in email</label>
              <input id="bh-leader-account-email" type="email" readonly value="<?php echo esc_attr( $user->user_email ); ?>" aria-describedby="bh-leader-email-note">
              <p id="bh-leader-email-note" class="bh-leader-account-note">Your sign-in email is managed through WordPress account security, not your public organiser contact details.</p>
              <button type="submit">Save display name</button>
            </form>
          </div>
          <div class="bh-leader-account-card" id="bh-leader-notifications">
            <h2>Notifications &amp; preferences</h2>
            <p>Choose which updates you would like to receive. These preferences are saved for future notification services; they do not send emails or enable reminders yet.</p>
            <?php if ( isset( $_GET['notifications_saved'] ) && $_GET['notifications_saved'] === '1' ) : ?><p class="bh-leader-account-success" role="status">Notification preferences saved.</p><?php endif; ?>
            <?php $notification_prefs = (array) get_user_meta( $user->ID, 'bh3_leader_notification_preferences', true ); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <input type="hidden" name="action" value="bh3_leader_notifications_save">
              <?php wp_nonce_field( 'bh3_leader_notifications_save' ); ?>
              <fieldset class="bh-leader-notifications-options">
                <legend>Updates I am interested in</legend>
                <?php foreach ( self::notification_options() as $key => $label ) : ?>
                  <label><input type="checkbox" name="bh_leader_notifications[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $notification_prefs[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
              </fieldset>
              <button type="submit">Save notification preferences</button>
            </form>
            <p class="bh-leader-account-note">No automatic email, booking alert, or background job is activated by these settings. Essential account and security messages may still be sent by WordPress.</p>
          </div>
          <div class="bh-leader-account-card">
            <h2>Security</h2>
            <p>Change your password securely using WordPress's password reset process.</p>
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Reset password</a>
          </div>
          <div class="bh-leader-account-card">
            <h2>Linked business</h2>
            <?php if ( $organiser_name !== '' ) : ?>
              <p><strong><?php echo esc_html( $organiser_name ); ?></strong></p>
              <p>Business contact details, branding and social links are managed in My Business.</p>
              <a href="<?php echo esc_url( home_url( '/leader/business/' ) ); ?>">Manage My Business</a>
            <?php else : ?>
              <p>No verified organiser is linked to this account. Contact Bubba Hub support to resolve account linking.</p>
              <a href="<?php echo esc_url( home_url( '/leader/support/' ) ); ?>">Get help</a>
            <?php endif; ?>
          </div>
        </section>
        <?php return (string) ob_get_clean();
    }
}
