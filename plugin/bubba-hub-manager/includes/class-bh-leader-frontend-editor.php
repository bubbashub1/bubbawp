<?php
/** Staging-only owner-scoped front-end draft editor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Frontend_Editor_3 {
    private static function enabled() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk' && is_user_logged_in() && current_user_can( 'read' );
    }
    private static function draft( $id ) {
        if ( ! self::enabled() || ! $id ) { return null; }
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser ) { return null; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,title,description,category,age_min_months,age_max_months,price_from,is_free,booking_url,image_id FROM `{$table}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $id, $organiser ), ARRAY_A );
    }
    public static function register() {
        add_shortcode( 'bh_leader_edit_activity', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_leader_frontend_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_bh3_leader_image_upload', array( __CLASS__, 'upload_image' ) );
        add_action( 'admin_post_bh3_leader_image_remove', array( __CLASS__, 'remove_image' ) );
    }
    public static function render() {
        if ( ! self::enabled() ) { return '<p>Please sign in to edit your draft activity.</p>'; }
        $id = absint( $_GET['activity_id'] ?? 0 );
        $row = self::draft( $id );
        if ( ! $row ) { return '<p>This draft is unavailable or is not assigned to your organiser.</p>'; }
        global $wpdb;
        $activities_table = $wpdb->prefix . 'activities';
        $categories = $wpdb->get_col( "SELECT DISTINCT category FROM `{$activities_table}` WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC LIMIT 150" );
        $categories = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $categories ) ) ) );
        if ( $row['category'] && ! in_array( $row['category'], $categories, true ) ) { $categories[] = $row['category']; sort( $categories, SORT_NATURAL | SORT_FLAG_CASE ); }
        ob_start(); ?>
        <section class="bh-leader-edit">

        <h1>Edit <?php echo esc_html( $row['title'] ); ?></h1>
        
        <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?>
          <div class="bh-edit-success" role="status">
            <strong>Draft saved successfully.</strong>
            <p>Your activity details are up to date. You can now continue to venues or timetable.</p>
            <div class="bh-edit-next-actions">
              <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/venues/' ) ) ); ?>">Continue to venues</a>
              <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/timetable/' ) ) ); ?>">Go to timetable</a>
            </div>
          </div>
        <?php endif; ?>
        <p>Draft details only. Publishing and deletion remain with the Bubba Hub team.</p>
        <section class="bh-image-card" aria-label="Listing image">
          <h2>Listing image</h2>
          <?php if ( isset( $_GET['image_saved'] ) ) : ?><p class="bh-edit-success" role="status">Listing image updated.</p><?php endif; ?>
          <?php if ( isset( $_GET['image_removed'] ) ) : ?><p class="bh-edit-success" role="status">Listing image removed.</p><?php endif; ?>
          <div class="bh-image-layout">
            <div>
              <?php if ( ! empty( $row['image_id'] ) && wp_attachment_is_image( absint( $row['image_id'] ) ) ) : ?>
                <?php echo wp_get_attachment_image( absint( $row['image_id'] ), 'medium', false, array( 'class' => 'bh-image-thumb', 'alt' => 'Current listing image' ) ); ?>
              <?php else : ?><div class="bh-image-thumb" role="img" aria-label="No listing image"></div><?php endif; ?>
            </div>
            <div>
              <p class="bh-edit-hint">Choose a clear photo for your activity. JPG, PNG or WebP, maximum 5 MB.</p>
              <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bh3_leader_image_upload">
                <input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
                <?php wp_nonce_field( 'bh3_leader_image_' . $id ); ?>
                <label for="bh-leader-image-file">Choose a new image</label>
                <input id="bh-leader-image-file" name="bh_leader_image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="bh-image-file-help" required>
                <p id="bh-image-file-help" class="bh-edit-hint" aria-live="polite">No new image selected.</p>
                <img id="bh-image-selected-preview" alt="Preview of selected image" hidden style="display:none;max-width:220px;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;margin-top:10px">
                <button type="submit"><?php echo ! empty( $row['image_id'] ) ? 'Replace image' : 'Upload image'; ?></button>
              </form>
              <?php if ( ! empty( $row['image_id'] ) ) : ?>
                <form method="post" onsubmit="return confirm('Remove this image from the listing? The original file will remain in the Media Library.');" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="bh3_leader_image_remove">
                  <input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
                  <?php wp_nonce_field( 'bh3_leader_remove_image_' . $id ); ?>
                  <button class="bh-image-remove" type="submit">Remove from listing</button>
                </form>
              <?php endif; ?>
              <p class="bh-edit-hint">Replacing or removing an image does not delete older photos from the Media Library.</p>
            </div>
          </div>
        </section>
        <script>
        (function(){
          const field = document.getElementById('bh-leader-image-file');
          const preview = document.getElementById('bh-image-selected-preview');
          const help = document.getElementById('bh-image-file-help');
          if (!field || !preview || !help) return;
          let objectUrl = null;
          field.addEventListener('change', function(){
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            preview.hidden = true; preview.style.display = 'none'; preview.removeAttribute('src');
            const file = field.files && field.files[0];
            if (!file) { help.textContent = 'No new image selected.'; return; }
            if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5242880 || file.size === 0) {
              help.textContent = 'Please select a JPG, PNG or WebP image under 5 MB.';
              field.value = ''; return;
            }
            help.textContent = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB) — preview before uploading';
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl; preview.hidden = false; preview.style.display = 'block';
          });
        })();
        </script>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="bh3_leader_frontend_save">
            <input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
            <?php wp_nonce_field( 'bh3_leader_frontend_' . $id ); ?>
            <section class="bh-editor-section" aria-labelledby="bh-details-title">
              <h2 id="bh-details-title">Activity details</h2>
              <p class="bh-edit-hint">Start with the name and category families will search for.</p>
              <p><label for="bh-edit-title">Activity title</label><input id="bh-edit-title" name="title" type="text" maxlength="255" required value="<?php echo esc_attr( $row['title'] ); ?>"></p>
              <p><label for="bh-edit-category">Category</label>
              <select id="bh-edit-category" name="category">
                <option value="">Choose a category</option>
                <?php foreach ( $categories as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $row['category'], $option ); ?>><?php echo esc_html( $option ); ?></option><?php endforeach; ?>
              </select></p>
              <p class="bh-edit-hint">Can't find the right category? Contact the Bubba Hub team.</p>
            </section>
            <section class="bh-editor-section" aria-labelledby="bh-description-title">
              <h2 id="bh-description-title">About the activity</h2>
              <p class="bh-edit-hint">Tell families what to expect, what to bring and who the activity is for.</p>
              <label for="bh-edit-description">Description</label>
              <textarea id="bh-edit-description" name="description" rows="8"><?php echo esc_textarea( $row['description'] ); ?></textarea>
            </section>
            <section class="bh-editor-section" aria-labelledby="bh-pricing-title">
              <h2 id="bh-pricing-title">Age range &amp; pricing</h2>
              <div class="bh-editor-grid">
                <p><label for="bh-edit-age-min">Minimum age (months)</label><input id="bh-edit-age-min" name="age_min_months" type="number" min="0" max="216" step="1" value="<?php echo esc_attr( (string) $row['age_min_months'] ); ?>"></p>
                <p><label for="bh-edit-age-max">Maximum age (months)</label><input id="bh-edit-age-max" name="age_max_months" type="number" min="0" max="216" step="1" value="<?php echo esc_attr( (string) $row['age_max_months'] ); ?>"></p>
              </div>
              <p class="bh-edit-hint">Use 0 months for newborns.</p>
              <p><label><input id="bh-edit-free" type="checkbox" name="is_free" value="1" <?php checked( (int) $row['is_free'], 1 ); ?>> This activity is free</label></p>
              <p><label for="bh-edit-price">Starting price (£)</label><input id="bh-edit-price" name="price_from" type="number" min="0" step="0.01" value="<?php echo esc_attr( (string) $row['price_from'] ); ?>" <?php disabled( (int) $row['is_free'], 1 ); ?>></p>
            </section>
            <section class="bh-editor-section" aria-labelledby="bh-booking-title">
              <h2 id="bh-booking-title">Booking information</h2>
              <p class="bh-edit-hint">Optional: link to the booking page families should use.</p>
              <p><label for="bh-edit-booking">Booking website</label><input id="bh-edit-booking" name="booking_url" type="url" placeholder="https://" value="<?php echo esc_attr( $row['booking_url'] ); ?>"></p>
            </section>
            <div class="bh-editor-save"><span class="bh-edit-hint">Only this draft will be updated.</span><button type="submit">Save draft changes</button></div>
            <script>
            (function(){
              const free = document.getElementById('bh-edit-free');
              const price = document.getElementById('bh-edit-price');
              if (!free || !price) return;
              free.addEventListener('change', function(){
                price.disabled = free.checked;
                if (free.checked) price.value = '0';
              });
            })();
            </script>
        </form></section>
        <?php return ob_get_clean();
    }
    public static function remove_image() {
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_remove_image_' . $id );
        if ( ! self::draft( $id ) ) { wp_die( 'Draft access denied.', '', array( 'response' => 403 ) ); }
        global $wpdb;
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        $result = $wpdb->update( $wpdb->prefix . 'activities',
            array( 'image_id' => null, 'updated_at' => current_time( 'mysql', true ) ),
            array( 'id' => $id, 'organiser_id' => $owner, 'status' => 'draft' ),
            array( '%d', '%s' ), array( '%d', '%d', '%s' ) );
        if ( $result === false ) { wp_die( 'Could not remove listing image.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'activity_id' => $id, 'image_removed' => 1 ), home_url( '/leader/edit-activity/' ) ) );
        exit;
    }
    public static function upload_image() {
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_image_' . $id );
        if ( ! self::draft( $id ) ) { wp_die( 'Draft access denied.', '', array( 'response' => 403 ) ); }
        $file = $_FILES['bh_leader_image'] ?? null;
        if ( ! is_array( $file ) || (int) ( $file['error'] ?? -1 ) !== UPLOAD_ERR_OK ||
            (int) ( $file['size'] ?? 0 ) < 1 || (int) $file['size'] > 5242880 ) {
            wp_die( 'Choose an image under 5 MB.', '', array( 'response' => 400 ) );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $image = media_handle_upload( 'bh_leader_image', 0, array(), array(
            'test_form' => false,
            'mimes' => array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ),
        ) );
        if ( is_wp_error( $image ) ) { wp_die( 'Image upload failed.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $owner = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        $updated = $wpdb->update( $wpdb->prefix . 'activities',
            array( 'image_id' => $image, 'updated_at' => current_time( 'mysql', true ) ),
            array( 'id' => $id, 'organiser_id' => $owner, 'status' => 'draft' ) );
        if ( $updated !== 1 ) {
            wp_delete_attachment( $image, true );
            wp_die( 'Unable to attach image to draft.', '', array( 'response' => 500 ) );
        }
        wp_safe_redirect( add_query_arg( array( 'activity_id' => $id, 'image_saved' => 1 ), home_url( '/leader/edit-activity/' ) ) );
        exit;
    }
    public static function save() {
        if ( ! self::enabled() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_frontend_' . $id );
        if ( ! self::draft( $id ) ) { wp_die( 'Draft not found or access denied.', '', array( 'response' => 403 ) ); }
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
        global $wpdb;
        $activities_table = $wpdb->prefix . 'activities';
        $valid_categories = $wpdb->get_col( "SELECT DISTINCT category FROM `{$activities_table}` WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC LIMIT 150" );
        $existing_draft = self::draft( $id );
        if ( $category !== '' && $category !== $existing_draft['category'] && ! in_array( $category, (array) $valid_categories, true ) ) { wp_die( 'Please select an existing category.', '', array( 'response' => 400 ) ); }
        if ( $title === '' || strlen( $title ) > 255 || strlen( $category ) > 150 ) { wp_die( 'Invalid title or category.', '', array( 'response' => 400 ) ); }
        $ages = array();
        foreach ( array( 'age_min_months', 'age_max_months' ) as $field ) {
            $raw = trim( (string) wp_unslash( $_POST[$field] ?? '' ) );
            $value = $raw === '' ? null : filter_var( $raw, FILTER_VALIDATE_INT );
            if ( $value !== null && ( $value === false || $value < 0 || $value > 216 ) ) { wp_die( 'Invalid age range.', '', array( 'response' => 400 ) ); }
            $ages[$field] = $value;
        }
        if ( $ages['age_min_months'] !== null && $ages['age_max_months'] !== null && $ages['age_min_months'] > $ages['age_max_months'] ) { wp_die( 'Invalid age order.', '', array( 'response' => 400 ) ); }
        $free = ! empty( $_POST['is_free'] ) ? 1 : 0;
        $price = trim( (string) wp_unslash( $_POST['price_from'] ?? '' ) );
        if ( $price !== '' && ( ! preg_match( '/^\d{1,8}(?:\.\d{1,2})?$/', $price ) || (float) $price > 99999999.99 ) ) { wp_die( 'Invalid price.', '', array( 'response' => 400 ) ); }
        $raw_url = trim( (string) wp_unslash( $_POST['booking_url'] ?? '' ) );
        $url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
        if ( strlen( $raw_url ) > 2048 || ( $raw_url !== '' && ( $url === '' || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) ) { wp_die( 'Invalid booking URL.', '', array( 'response' => 400 ) ); }
        global $wpdb;
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        $result = $wpdb->update( $wpdb->prefix . 'activities', array(
            'title' => $title, 'category' => $category, 'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'age_min_months' => $ages['age_min_months'], 'age_max_months' => $ages['age_max_months'],
            'price_from' => $free ? 0 : ( $price === '' ? null : (float) $price ),
            'is_free' => $free, 'booking_url' => $url, 'updated_at' => current_time( 'mysql', true )
        ), array( 'id' => $id, 'organiser_id' => $organiser, 'status' => 'draft' ) );
        if ( $result === false ) { wp_die( 'Save failed.', '', array( 'response' => 500 ) ); }
        wp_safe_redirect( add_query_arg( array( 'activity_id' => $id, 'saved' => '1' ), home_url( '/leader/edit-activity/' ) ) );
        exit;
    }
}
