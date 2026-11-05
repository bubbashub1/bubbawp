<?php
/** Explicitly installed, administrator-reviewed leader venue requests (staging only). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Venue_Requests_3 {
    private static function staging() { return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk'; }
    private static function table() { global $wpdb; return $wpdb->prefix . 'bh_leader_venue_requests'; }
    private static function exists() { global $wpdb; $t=self::table(); return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t; }
    public static function register() {
        add_shortcode( 'bh_leader_new_venue_request', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_venue_request_submit', array( __CLASS__, 'submit' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
        add_action( 'admin_post_bh3_venue_request_install', array( __CLASS__, 'install' ) );
        add_action( 'admin_post_bh3_venue_request_review', array( __CLASS__, 'review' ) );
    }
    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Leader Venue Requests', 'Venue Requests', 'manage_options', 'bh3-venue-requests', array( __CLASS__, 'admin_page' ) );
    }
    public static function install() {
        if ( ! self::staging() || ! current_user_can( 'manage_options' ) ) wp_die( 'Staging administrator required.', '', array( 'response'=>403 ) );
        check_admin_referer( 'bh3_venue_request_install' );
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t=self::table(); $charset=$wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$t} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organiser_id bigint(20) unsigned NOT NULL,
            activity_id bigint(20) unsigned NOT NULL,
            venue_name varchar(255) NOT NULL,
            address_line1 varchar(255) NOT NULL DEFAULT '',
            town varchar(150) NOT NULL DEFAULT '',
            postcode varchar(20) NOT NULL DEFAULT '',
            accessibility text NULL,
            directions text NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            reviewed_by bigint(20) unsigned NULL,
            venue_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY organiser_id (organiser_id),
            KEY activity_id (activity_id),
            KEY status (status)
        ) {$charset};" );
        if ( ! self::exists() ) wp_die( 'Venue request table could not be created.', '', array( 'response'=>500 ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bh3-venue-requests&installed=1' ) ); exit;
    }
    public static function render() {
        if ( ! self::staging() || ! is_user_logged_in() || ! current_user_can( 'read' ) ) return '<p>Please sign in to request a venue.</p>';
        if ( ! self::exists() ) return '<p>Venue requests are being set up. Please contact Bubba Hub support.</p>';
        $activity_id=absint( $_GET['activity_id'] ?? 0 ); $owner=absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        global $wpdb; $a=$wpdb->prefix.'activities';
        $activity=$wpdb->get_row( $wpdb->prepare( "SELECT id,title FROM `{$a}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1", $activity_id, $owner ), ARRAY_A );
        if ( ! $activity ) return '<p>Choose one of your own draft activities before requesting a new venue.</p>';
        ob_start(); ?>
        <section class="bh3-venue-request"><h2>Suggest a new venue</h2><p>Your suggestion will be checked by the Bubba Hub team before it is added to the shared venue directory.</p>
        <?php if ( isset($_GET['venue_request']) && $_GET['venue_request']==='sent' ) : ?><p role="status">Your venue suggestion has been sent for review.</p><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="bh3_venue_request_submit"><input type="hidden" name="activity_id" value="<?php echo esc_attr($activity_id); ?>">
          <?php wp_nonce_field('bh3_venue_request_'.$activity_id); ?>
          <p><label>Venue name <input name="venue_name" maxlength="255" required></label></p>
          <p><label>Address <input name="address_line1" maxlength="255" required></label></p>
          <p><label>Town <input name="town" maxlength="150" required></label></p>
          <p><label>Postcode <input name="postcode" maxlength="20" required></label></p>
          <p><label>Accessibility information <textarea name="accessibility" rows="3" maxlength="3000"></textarea></label></p>
          <p><label>Directions / parking information <textarea name="directions" rows="3" maxlength="3000"></textarea></label></p>
          <p><button type="submit">Send venue for review</button></p>
        </form></section>
        <?php return ob_get_clean();
    }
    public static function submit() {
        if ( ! self::staging() || ! is_user_logged_in() || ! current_user_can('read') || ! self::exists() ) wp_die('Venue request unavailable.','',array('response'=>403));
        $id=absint($_POST['activity_id']??0); check_admin_referer('bh3_venue_request_'.$id);
        $owner=absint(get_user_meta(get_current_user_id(),'bh3_organiser_id',true)); global $wpdb; $a=$wpdb->prefix.'activities';
        if ( ! $owner || ! $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$a}` WHERE id=%d AND organiser_id=%d AND status='draft'",$id,$owner)) ) wp_die('Activity is not owned by this organiser.','',array('response'=>403));
        $name=sanitize_text_field(wp_unslash($_POST['venue_name']??'')); $address=sanitize_text_field(wp_unslash($_POST['address_line1']??'')); $town=sanitize_text_field(wp_unslash($_POST['town']??'')); $postcode=strtoupper(sanitize_text_field(wp_unslash($_POST['postcode']??'')));
        if (!$name||!$address||!$town||!$postcode) wp_die('Complete the venue name and address.','',array('response'=>400));
        $venues=$wpdb->prefix.'venues'; $like='%'.$wpdb->esc_like($name).'%';
        $matches=$wpdb->get_results($wpdb->prepare("SELECT id,name,town,postcode FROM `{$venues}` WHERE name LIKE %s OR postcode=%s LIMIT 10",$like,$postcode),ARRAY_A);
        if ($matches) {
            $message='A similar venue may already exist. Please ask the team to check before submitting another request.';
            wp_die(esc_html($message.' Matches: '.implode('; ',array_map(static function($v){return $v['name'].' — '.$v['town'].' '.$v['postcode'];},$matches))),'',array('response'=>409));
        }
        $t=self::table();
        $pending=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$t}` WHERE activity_id=%d AND organiser_id=%d AND status='pending' AND (postcode=%s OR venue_name=%s) LIMIT 1",$id,$owner,$postcode,$name));
        if ($pending) wp_die('A matching venue request is already awaiting review.','',array('response'=>409));
        $now=current_time('mysql',true);
        $ok=$wpdb->insert($t,array('organiser_id'=>$owner,'activity_id'=>$id,'venue_name'=>$name,'address_line1'=>$address,'town'=>$town,'postcode'=>$postcode,'accessibility'=>sanitize_textarea_field(wp_unslash($_POST['accessibility']??'')),'directions'=>sanitize_textarea_field(wp_unslash($_POST['directions']??'')),'status'=>'pending','created_at'=>$now,'updated_at'=>$now));
        if ($ok===false) wp_die('Unable to submit venue request.','',array('response'=>500));
        wp_safe_redirect(add_query_arg(array('activity_id'=>$id,'venue_request'=>'sent'),home_url('/leader/venues/'))); exit;
    }
    public static function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        echo '<div class="wrap"><h1>Leader venue requests</h1><p>New venues are reviewed before entering the shared venue directory.</p>';
        if (!self::staging()) { echo '<p>Available on staging only.</p></div>'; return; }
        if (!self::exists()) { echo '<p>Request table has not been installed.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bh3_venue_request_install">'; wp_nonce_field('bh3_venue_request_install'); submit_button('Create venue request table'); echo '</form></div>'; return; }
        global $wpdb; $t=self::table(); $rows=$wpdb->get_results("SELECT * FROM `{$t}` ORDER BY CASE status WHEN 'pending' THEN 0 ELSE 1 END, id DESC LIMIT 200",ARRAY_A);
        if (!$rows) { echo '<p>No venue suggestions yet.</p></div>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Venue</th><th>Address</th><th>Accessibility / directions</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach($rows as $row){echo '<tr><td><strong>'.esc_html($row['venue_name']).'</strong><br>Request #'.(int)$row['id'].'</td><td>'.esc_html($row['address_line1'].', '.$row['town'].' '.$row['postcode']).'</td><td>'.esc_html($row['accessibility']).'<br>'.esc_html($row['directions']).'</td><td>'.esc_html($row['status']).'</td><td>';
        if($row['status']==='pending'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bh3_venue_request_review"><input type="hidden" name="request_id" value="'.(int)$row['id'].'">';wp_nonce_field('bh3_venue_review_'.(int)$row['id']);echo '<button class="button button-primary" name="decision" value="approve">Approve &amp; add venue</button> <button class="button" name="decision" value="reject">Reject</button></form>';}
        elseif($row['venue_id']) echo 'Venue #'.(int)$row['venue_id'];
        echo '</td></tr>';}
        echo '</tbody></table></div>';
    }
    public static function review() {
        if (!self::staging()||!current_user_can('manage_options')||!self::exists()) wp_die('Administrator access required.','',array('response'=>403));
        $id=absint($_POST['request_id']??0); check_admin_referer('bh3_venue_review_'.$id); $decision=sanitize_key(wp_unslash($_POST['decision']??'')); if(!in_array($decision,array('approve','reject'),true)) wp_die('Invalid review decision.');
        global $wpdb; $t=self::table(); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE id=%d AND status='pending'",$id),ARRAY_A); if(!$row) wp_die('Request not found or already reviewed.','',array('response'=>404));
        $venue_id=null;
        if($decision==='approve'){
            $a=$wpdb->prefix.'activities';
            $still_owned=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$a}` WHERE id=%d AND organiser_id=%d AND status='draft' LIMIT 1",(int)$row['activity_id'],(int)$row['organiser_id']));
            if (!$still_owned) wp_die('The original draft listing is no longer available to this organiser. Review cannot continue.','',array('response'=>409));
            $v=$wpdb->prefix.'venues'; $now=current_time('mysql',true);
            $ok=$wpdb->insert($v,array('name'=>$row['venue_name'],'address_line1'=>$row['address_line1'],'town'=>$row['town'],'postcode'=>$row['postcode'],'created_at'=>$now,'updated_at'=>$now));
            if($ok===false) wp_die('Could not create approved venue.','',array('response'=>500)); $venue_id=(int)$wpdb->insert_id;
            $link=$wpdb->prefix.'activity_venues';
            $linked=$wpdb->insert($link,array('activity_id'=>(int)$row['activity_id'],'venue_id'=>$venue_id));
            if ($linked===false) {
                $wpdb->delete($v,array('id'=>$venue_id),array('%d'));
                wp_die('The venue could not be linked to the listing. The approval has not been completed.','',array('response'=>500));
            }
        }
        $wpdb->update($t,array('status'=>$decision==='approve'?'approved':'rejected','reviewed_by'=>get_current_user_id(),'venue_id'=>$venue_id,'updated_at'=>current_time('mysql',true)),array('id'=>$id,'status'=>'pending'));
        wp_safe_redirect(admin_url('admin.php?page=bh3-venue-requests&reviewed=1')); exit;
    }
}
