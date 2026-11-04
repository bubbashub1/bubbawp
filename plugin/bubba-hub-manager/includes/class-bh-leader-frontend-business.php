<?php
/** Staging-only, organiser-owned front-end business profile editor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_Business_3 {
    private static function permitted() {
        return is_user_logged_in() && current_user_can( 'read' ) &&
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    private static function profile() {
        if ( ! self::permitted() ) { return null; }
        $id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $id ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,name,description,website_url,contact_email FROM `{$table}` WHERE id=%d LIMIT 1", $id ), ARRAY_A );
    }
    public static function register() {
        add_shortcode( 'bh_leader_business', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_leader_business_save', array( __CLASS__, 'save' ) );
    }
    public static function render() {
        $profile = self::profile();
        if ( ! $profile ) { return '<p>Please sign in with a linked organiser account to manage your business profile.</p>'; }
        ob_start(); ?>
        <section class="bh-leader-business">
        <style>.bh-leader-business{max-width:900px;margin:32px auto;padding:0 20px;color:#203c31;font-family:inherit}.bh-leader-business *{box-sizing:border-box}.bh-leader-business .bh-business-card{background:#fff;border:1px solid #dbe5df;border-radius:16px;padding:24px;margin:20px 0}.bh-leader-business label{display:block;font-weight:600;margin:16px 0 6px}.bh-leader-business input:not([type=checkbox]),.bh-leader-business textarea,.bh-leader-business select{width:100%;max-width:680px;padding:11px;border:1px solid #b6c8bc;border-radius:9px;font:inherit}.bh-leader-business button{background:#245e4a;color:#fff;border:0;border-radius:9px;padding:12px 20px;font:inherit;font-weight:600;cursor:pointer}.bh-leader-business .bh-business-note{color:#53665e} .bh-leader-business .bh-business-hero{background:linear-gradient(120deg,#1e5743,#367d5d);color:#fff;padding:26px;border-radius:20px;margin:14px 0 24px}.bh-leader-business .bh-business-hero h1{color:#fff;margin:5px 0;font-size:clamp(28px,4vw,38px)}.bh-leader-business .bh-business-hero p{color:#edf7ef;margin:5px 0}.bh-leader-business .bh-business-card{box-shadow:0 8px 24px rgba(25,58,40,.05)}.bh-leader-business input:focus-visible,.bh-leader-business textarea:focus-visible,.bh-leader-business button:focus-visible{outline:3px solid #245e4a;outline-offset:2px}@media(max-width:650px){.bh-leader-business .bh-business-hero{padding:20px}.bh-leader-business .bh-business-card{padding:17px}}</style>
        <p><a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">← Leader Dashboard</a> · <a href="<?php echo esc_url( home_url( '/leader/about/' ) ); ?>">Preview storefront</a></p>
        <header class="bh-business-hero"><p>YOUR ORGANISER PROFILE</p><h1>My Business</h1><p>Make it easy for families to learn about your organisation and get in touch.</p></header>
        <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><p role="status">Business profile saved successfully.</p><?php endif; ?>
        <div class="bh-business-card"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_leader_business_save">
        <?php wp_nonce_field( 'bh3_leader_business_' . (int) $profile['id'] ); ?>
        <label for="bh-business-name">Organiser name</label><input id="bh-business-name" value="<?php echo esc_attr( $profile['name'] ); ?>" readonly aria-describedby="bh-name-note">
        <p id="bh-name-note" class="bh-business-note">To change your registered organiser name, contact the Bubba Hub team.</p>
        <label for="bh-business-email">Public contact email</label><input id="bh-business-email" type="email" name="contact_email" maxlength="254" value="<?php echo esc_attr( $profile['contact_email'] ); ?>">
        <label for="bh-business-url">Website</label><input id="bh-business-url" type="url" name="website_url" maxlength="2048" value="<?php echo esc_attr( $profile['website_url'] ); ?>" placeholder="https://">
        <label for="bh-business-description">About your organisation</label><textarea id="bh-business-description" name="description" rows="8"><?php echo esc_textarea( $profile['description'] ); ?></textarea>
        <h2>Storefront appearance</h2>
        <p class="bh-business-note">Add public images using existing WordPress Media Library image URLs. Your organiser profile remains the single source of truth.</p>
        <?php $extras = get_option( 'bh3_storefront_' . (int) $profile['id'], array() ); ?>
        <label for="bh-business-logo">Logo image URL</label><input id="bh-business-logo" name="logo_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['logo_url'] ?? '' ); ?>">
        <label for="bh-business-cover">Cover image URL</label><input id="bh-business-cover" name="cover_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['cover_url'] ?? '' ); ?>">
        <h2>Business directory</h2>
        <p class="bh-business-note">Help families discover your business. Directory publishing will remain disabled until the directory is ready.</p>
        <label for="bh-business-category">Business category</label>
        <select id="bh-business-category" name="business_category">
          <?php foreach ( array( '' => 'Choose a category', 'classes' => 'Classes & activities', 'family-services' => 'Family services', 'pregnancy' => 'Pregnancy & postnatal', 'childcare' => 'Childcare & education', 'shops' => 'Shops & gifts', 'venues' => 'Family-friendly venues', 'other' => 'Other family business' ) as $value => $label ) : ?>
          <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $extras['business_category'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
        <label for="bh-business-area">Areas served</label><input id="bh-business-area" name="areas_served" type="text" maxlength="200" placeholder="e.g. Torbay, Exeter, South Devon" value="<?php echo esc_attr( $extras['areas_served'] ?? '' ); ?>">
        <label><input type="checkbox" name="directory_opt_in" value="1" <?php checked( ! empty( $extras['directory_opt_in'] ) ); ?>> Include my business when the directory launches</label>
        <h2>Social links</h2>
        <label for="bh-business-facebook">Facebook URL</label><input id="bh-business-facebook" name="facebook_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['facebook_url'] ?? '' ); ?>">
        <label for="bh-business-instagram">Instagram URL</label><input id="bh-business-instagram" name="instagram_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['instagram_url'] ?? '' ); ?>">
        <p class="bh-business-note">Your account permissions and linked activities will not change.</p>
        <button type="submit">Save business profile</button></form></div></section>
        <?php return ob_get_clean();
    }
    public static function save() {
        $profile = self::profile();
        if ( ! $profile ) { wp_die( 'Organiser access required.', '', array( 'response'=>403 ) ); }
        check_admin_referer( 'bh3_leader_business_' . (int) $profile['id'] );
        $email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $raw_url = trim( (string) wp_unslash( $_POST['website_url'] ?? '' ) );
        $url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
        $description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
        if ( strlen( $email ) > 254 || ( $email !== '' && ! is_email( $email ) ) ||
            strlen( $raw_url ) > 2048 || ( $raw_url !== '' && ( $url === '' || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) ||
            strlen( $description ) > 20000 ) { wp_die( 'Please check the email, website and description.', '', array( 'response'=>400 ) ); }
        $extras = array();
        foreach ( array( 'logo_url', 'cover_url', 'facebook_url', 'instagram_url' ) as $field ) {
            $raw = trim( (string) wp_unslash( $_POST[ $field ] ?? '' ) );
            $clean = esc_url_raw( $raw, array( 'http', 'https' ) );
            if ( strlen( $raw ) > 2048 || ( $raw !== '' && ( ! $clean || ! in_array( strtolower( (string) wp_parse_url( $clean, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) ) {
                wp_die( 'Please enter valid HTTPS or HTTP storefront URLs.', '', array( 'response' => 400 ) );
            }
            $extras[ $field ] = $clean;
        }
        $categories = array( 'classes', 'family-services', 'pregnancy', 'childcare', 'shops', 'venues', 'other' );
        $category = sanitize_key( wp_unslash( $_POST['business_category'] ?? '' ) );
        if ( $category && ! in_array( $category, $categories, true ) ) { wp_die( 'Invalid business category.', '', array( 'response' => 400 ) ); }
        $areas = sanitize_text_field( wp_unslash( $_POST['areas_served'] ?? '' ) );
        if ( mb_strlen( $areas ) > 200 ) { wp_die( 'Areas served is too long.', '', array( 'response' => 400 ) ); }
        $extras['business_category'] = $category;
        $extras['areas_served'] = $areas;
        $extras['directory_opt_in'] = ! empty( $_POST['directory_opt_in'] ) ? 1 : 0;
        global $wpdb;
        $table = $wpdb->prefix . 'organisers';
        $result = $wpdb->update( $table, array( 'contact_email'=>$email, 'website_url'=>$url, 'description'=>$description, 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>(int) $profile['id'] ) );
        if ( $result === false ) { wp_die( 'Could not save business profile.', '', array( 'response'=>500 ) ); }
        update_option( 'bh3_storefront_' . (int) $profile['id'], $extras, false );
        wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/leader/business/' ) ) );
        exit;
    }
}
