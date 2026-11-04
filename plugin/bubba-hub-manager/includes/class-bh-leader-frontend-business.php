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
        add_action( 'admin_post_bh3_leader_business_image', array( __CLASS__, 'upload_image' ) );
    }
    public static function render() {
        $profile = self::profile();
        if ( ! $profile ) { return '<p>Please sign in with a linked organiser account to manage your business profile.</p>'; }
        ob_start(); ?>
        <section class="bh-leader-business" id="bh-business-editor">
        
        <div class="bh-business-main">
        <nav class="bh-business-sections" aria-label="Business profile sections">
          <a href="#bh-business-details">Details</a>
          <a href="#bh-business-appearance">Branding</a>
          <a href="#bh-business-directory">Directory</a>
          <a href="#bh-business-social">Social links</a>
          <a href="<?php echo esc_url( home_url( '/leader/about/' ) ); ?>">Preview storefront</a>
        </nav>
        <header class="bh-business-hero"><p>YOUR ORGANISER PROFILE</p><h1>My Business</h1><p>Make it easy for families to learn about your organisation and get in touch.</p></header>
        <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><p role="status">Business profile saved successfully.</p><?php endif; ?>
        <div class="bh-business-card"><form id="bh-business-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_leader_business_save">
        <?php wp_nonce_field( 'bh3_leader_business_' . (int) $profile['id'] ); ?>
        <h2 id="bh-business-details">Business details</h2>
        <label for="bh-business-name">Organiser name</label><input id="bh-business-name" value="<?php echo esc_attr( $profile['name'] ); ?>" readonly aria-describedby="bh-name-note">
        <p id="bh-name-note" class="bh-business-note">To change your registered organiser name, contact the Bubba Hub team.</p>
        <label for="bh-business-email">Public contact email</label><input id="bh-business-email" type="email" name="contact_email" maxlength="254" value="<?php echo esc_attr( $profile['contact_email'] ); ?>">
        <label for="bh-business-url">Website</label><input id="bh-business-url" type="url" name="website_url" maxlength="2048" value="<?php echo esc_attr( $profile['website_url'] ); ?>" placeholder="https://">
        <label for="bh-business-description">About your organisation</label><textarea id="bh-business-description" name="description" rows="8"><?php echo esc_textarea( $profile['description'] ); ?></textarea>
        <h2 id="bh-business-appearance">Storefront appearance</h2>
        <p class="bh-business-note">Add public images using existing WordPress Media Library image URLs. Your organiser profile remains the single source of truth.</p>
        <?php $extras = get_option( 'bh3_storefront_' . (int) $profile['id'], array() ); ?>
        <label for="bh-business-logo">Logo image URL</label><input id="bh-business-logo" name="logo_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['logo_url'] ?? '' ); ?>">
        <label for="bh-business-cover">Cover image URL</label><input id="bh-business-cover" name="cover_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['cover_url'] ?? '' ); ?>">
        <p class="bh-business-note">Or upload an image directly from your device using the separate image uploader below.</p>
        <h2 id="bh-business-directory">Business directory</h2>
        <p class="bh-business-note">Help families discover your business. Directory publishing will remain disabled until the directory is ready.</p>
        <label for="bh-business-category">Business category</label>
        <select id="bh-business-category" name="business_category">
          <?php foreach ( array( '' => 'Choose a category', 'classes' => 'Classes & activities', 'family-services' => 'Family services', 'pregnancy' => 'Pregnancy & postnatal', 'childcare' => 'Childcare & education', 'shops' => 'Shops & gifts', 'venues' => 'Family-friendly venues', 'other' => 'Other family business' ) as $value => $label ) : ?>
          <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $extras['business_category'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
          <?php endforeach; ?>
        </select>
        <label for="bh-business-area">Areas served</label><input id="bh-business-area" name="areas_served" type="text" maxlength="200" placeholder="e.g. Torbay, Exeter, South Devon" value="<?php echo esc_attr( $extras['areas_served'] ?? '' ); ?>">
        <label><input type="checkbox" name="directory_opt_in" value="1" <?php checked( ! empty( $extras['directory_opt_in'] ) ); ?>> Include my business when the directory launches</label>
        <h2 id="bh-business-social">Social links</h2>
        <label for="bh-business-facebook">Facebook URL</label><input id="bh-business-facebook" name="facebook_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['facebook_url'] ?? '' ); ?>">
        <label for="bh-business-instagram">Instagram URL</label><input id="bh-business-instagram" name="instagram_url" type="url" maxlength="2048" placeholder="https://" value="<?php echo esc_attr( $extras['instagram_url'] ?? '' ); ?>">
        <p class="bh-business-note">Your account permissions and linked activities will not change.</p>
        <button type="submit">Save business profile</button></form>
        <?php if ( isset( $_GET['image_saved'] ) ) : ?><p role="status">Storefront image uploaded successfully.</p><?php endif; ?>
        <div class="bh-business-upload">
          <h2>Upload branding</h2>
          <p class="bh-business-note">JPG, PNG or WebP, maximum 5 MB. Uploading a new image replaces the corresponding storefront image, not the original Media Library file.</p>
          <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="bh3_leader_business_image">
            <?php wp_nonce_field( 'bh3_leader_business_image_' . (int) $profile['id'] ); ?>
            <label for="bh-branding-type">Image type</label>
            <select id="bh-branding-type" name="image_type"><option value="logo_url">Business logo</option><option value="cover_url">Cover photo</option></select>
            <label for="bh-branding-file">Choose image</label>
            <input id="bh-branding-file" type="file" name="branding_image" accept="image/jpeg,image/png,image/webp" aria-describedby="bh-branding-feedback" required>
            <p id="bh-branding-feedback" class="bh-business-note" aria-live="polite">No image selected.</p>
            <img id="bh-branding-preview" alt="Selected branding image preview" hidden style="display:none;width:180px;max-height:180px;object-fit:contain;border-radius:12px;border:1px solid #dbe5df;padding:8px">
            <p><button type="submit">Upload image</button></p>
          </form>
        </div></div>
        <script>
        (function(){
          const input=document.getElementById('bh-branding-file'),preview=document.getElementById('bh-branding-preview'),feedback=document.getElementById('bh-branding-feedback');
          if(!input||!preview||!feedback)return;
          let url;
          input.addEventListener('change',function(){
            if(url){URL.revokeObjectURL(url);url=null;}
            preview.hidden=true;preview.style.display='none';preview.removeAttribute('src');
            const file=input.files&&input.files[0];
            if(!file){feedback.textContent='No image selected.';return;}
            if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size<1||file.size>5242880){
              input.value='';feedback.textContent='Please choose a JPG, PNG or WebP under 5 MB.';return;
            }
            feedback.textContent=file.name+' ('+(file.size/1048576).toFixed(1)+' MB)';
            url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;preview.style.display='block';
          });
        })();
        </script></div><div class="bh-business-mobile-save"><button type="submit" form="bh-business-profile-form">Save changes</button></div></section>
        <?php return ob_get_clean();
    }
    public static function upload_image() {
        $profile = self::profile();
        if ( ! $profile ) { wp_die( 'Organiser access required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'bh3_leader_business_image_' . (int) $profile['id'] );
        $field = sanitize_key( wp_unslash( $_POST['image_type'] ?? '' ) );
        if ( ! in_array( $field, array( 'logo_url', 'cover_url' ), true ) ) { wp_die( 'Invalid image type.', '', array( 'response' => 400 ) ); }
        $file = $_FILES['branding_image'] ?? null;
        if ( ! is_array( $file ) || ! isset( $file['error'], $file['size'], $file['tmp_name'] ) ||
            (int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] < 1 || (int) $file['size'] > 5242880 ) {
            wp_die( 'Choose an image smaller than 5 MB.', '', array( 'response' => 400 ) );
        }
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array(
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'
        ) );
        if ( empty( $checked['type'] ) || ! in_array( $checked['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
            wp_die( 'Only JPG, PNG and WebP images are supported.', '', array( 'response' => 400 ) );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload( 'branding_image', 0, array(), array(
            'test_form' => false,
            'mimes' => array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' )
        ) );
        if ( is_wp_error( $attachment_id ) ) { wp_die( esc_html( $attachment_id->get_error_message() ), '', array( 'response' => 400 ) ); }
        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) { wp_die( 'Could not retrieve the uploaded image.', '', array( 'response' => 500 ) ); }
        $key = 'bh3_storefront_' . (int) $profile['id'];
        $extras = get_option( $key, array() );
        if ( ! is_array( $extras ) ) { $extras = array(); }
        $extras[ $field ] = esc_url_raw( $url );
        update_option( $key, $extras, false );
        wp_safe_redirect( add_query_arg( 'image_saved', '1', home_url( '/leader/business/' ) ) );
        exit;
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
