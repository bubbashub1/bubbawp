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
        <section class="bh-leader-edit" style="max-width:850px;margin:32px auto;padding:24px;background:#fff;border:1px solid #dbe5df;border-radius:16px">
        <style>
        .bh-leader-edit{font-family:inherit;color:#203c31;box-sizing:border-box}
        .bh-leader-edit *{box-sizing:border-box}
        .bh-leader-edit .bh-edit-links{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 22px}
        .bh-leader-edit .bh-edit-links a{display:inline-flex;padding:9px 14px;border:1px solid #cadbcf;border-radius:9px;color:#245e4a;text-decoration:none;font-weight:600}
        .bh-leader-edit label{display:block;font-weight:600;margin-bottom:6px}
        .bh-leader-edit input:not([type=checkbox]),.bh-leader-edit textarea{width:100%;max-width:680px;padding:11px;border:1px solid #b8c9bd;border-radius:9px;font:inherit}
        .bh-leader-edit input:focus-visible,.bh-leader-edit textarea:focus-visible,.bh-leader-edit a:focus-visible,.bh-leader-edit button:focus-visible{outline:3px solid #245e4a;outline-offset:2px}
        .bh-leader-edit .bh-edit-success{padding:12px 16px;border-left:4px solid #245e4a;background:#f0f6f0}
.bh-leader-edit .bh-edit-hint{font-size:.92em;color:#53665e}
        .bh-leader-edit .bh-image-card{padding:18px;border:1px solid #dbe5df;border-radius:14px;background:#f8fbf9;margin:20px 0}
        .bh-leader-edit .bh-image-layout{display:grid;grid-template-columns:minmax(0,220px) minmax(0,1fr);gap:20px;align-items:start}
        .bh-leader-edit .bh-image-thumb{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;background:#e7eee9}
        .bh-leader-edit .bh-image-card form{margin:0 0 12px}
        .bh-leader-edit .bh-image-card input[type=file]{font-size:14px;max-width:100%}
        .bh-leader-edit .bh-image-card button{background:#245e4a;color:#fff;border:0;border-radius:9px;padding:10px 16px;cursor:pointer;font:inherit;font-weight:600;margin-top:10px}
        .bh-leader-edit .bh-image-card .bh-image-remove{background:#fff;color:#7a2f2f;border:1px solid #cbaaaa}
        @media(max-width:650px){.bh-leader-edit .bh-image-layout{grid-template-columns:1fr}.bh-leader-edit .bh-image-thumb{max-width:260px}}
        @media(max-width:650px){.bh-leader-edit{padding:18px!important;margin:16px auto!important}}
        </style>
        <h1>Edit <?php echo esc_html( $row['title'] ); ?></h1>
        <p><a href="<?php echo esc_url( home_url( '/leader/' ) ); ?>">← Leader Dashboard</a></p>
        <nav class="bh-edit-links" aria-label="Activity management">
          <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/venues/' ) ) ); ?>">Manage venues</a>
          <a href="<?php echo esc_url( add_query_arg( 'activity_id', $id, home_url( '/leader/timetable/' ) ) ); ?>">Manage timetable</a>
        </nav>
        <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><p class="bh-edit-success" role="status">Draft saved successfully.</p><?php endif; ?>
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
                <input id="bh-leader-image-file" name="bh_leader_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                <button type="submit"><?php echo ! empty( $row['image_id'] ) ? 'Replace image' : 'Upload image'; ?></button>
              </form>
              <?php if ( ! empty( $row['image_id'] ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="bh3_leader_frontend_save">
            <input type="hidden" name="activity_id" value="<?php echo esc_attr( $id ); ?>">
            <?php wp_nonce_field( 'bh3_leader_frontend_' . $id ); ?>
            <?php foreach ( array( 'title' => 'Activity title', 'category' => 'Category', 'age_min_months' => 'Minimum age (months)', 'age_max_months' => 'Maximum age (months)', 'price_from' => 'Price from (£)', 'booking_url' => 'Booking website' ) as $key => $label ) :
                $numeric = in_array( $key, array( 'age_min_months', 'age_max_months', 'price_from' ), true );
                $type = $numeric ? 'number' : ( $key === 'booking_url' ? 'url' : 'text' ); ?>
                <p><label for="bh-edit-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                <?php if ( $key === 'category' ) : ?>
                <select id="bh-edit-category" name="category" style="display:block;width:100%;max-width:680px;padding:11px;border:1px solid #b8c9bd;border-radius:9px;font:inherit">
                    <option value="">Choose a category</option>
                    <?php foreach ( $categories as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $row['category'], $option ); ?>><?php echo esc_html( $option ); ?></option><?php endforeach; ?>
                </select>
                <small class="bh-edit-hint">Choose an existing Bubba Hub category. Contact the team if a new one is needed.</small>
                <?php else : ?>
                <input style="display:block;width:100%;max-width:600px;padding:10px" id="bh-edit-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( (string) ( $row[$key] ?? '' ) ); ?>" <?php if ( $numeric ) : ?>min="0" step="<?php echo $key === 'price_from' ? '0.01' : '1'; ?>"<?php endif; ?> <?php if ( $key === 'title' ) : ?>required<?php endif; ?>>
                <?php endif; ?></p>
            <?php endforeach; ?>
            <p class="bh-edit-hint">Age ranges are entered in months. Use 0 for newborns. The booking link must begin with https:// or http://.</p>
            <p><label for="bh-edit-description">Description</label><textarea id="bh-edit-description" name="description" rows="8" style="display:block;width:100%"><?php echo esc_textarea( $row['description'] ); ?></textarea></p>
            <p><label><input type="checkbox" name="is_free" value="1" <?php checked( (int) $row['is_free'], 1 ); ?>> This activity is free</label></p>
            <p class="bh-edit-hint">Ticking Free will save the starting price as £0. Changes remain in draft until approved for publishing.</p>
            <button type="submit" style="background:#245e4a;color:#fff;border:0;border-radius:8px;padding:12px 20px;cursor:pointer">Save draft</button>
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
