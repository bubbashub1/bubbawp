<?php
/** Staging-only administrator linking of WordPress users to BH3 organisers. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Linking_3 {
    public static function register() {
        if ( BH_Leader_Access_3::is_staging() ) {
            add_submenu_page( 'bubba-hub-3-status', 'Leader Account Links', 'Leader Account Links', 'manage_options', 'bh3-leader-links', array( __CLASS__, 'render' ) );
        }
    }
    public static function save() {
        if ( ! BH_Leader_Access_3::is_staging() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Not permitted.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'bh3_leader_link' );
        $user_id = absint( $_POST['user_id'] ?? 0 );
        $organiser_id = absint( $_POST['organiser_id'] ?? 0 );
        if ( ! $user_id || ! get_userdata( $user_id ) ) {
            wp_die( 'WordPress user not found.', '', array( 'response' => 400 ) );
        }
        if ( $organiser_id ) {
            if ( ! BH_Leader_Access_3::table_exists( 'organisers' ) ) {
                wp_die( 'BH3 organiser table unavailable.', '', array( 'response' => 409 ) );
            }
            global $wpdb;
            $table = $wpdb->prefix . 'organisers';
            $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$table}` WHERE id = %d LIMIT 1", $organiser_id ) );
            if ( ! $name ) {
                wp_die( 'BH3 organiser not found.', '', array( 'response' => 400 ) );
            }
        }
        $previous = absint( get_user_meta( $user_id, 'bh3_organiser_id', true ) );
        if ( $organiser_id ) {
            update_user_meta( $user_id, 'bh3_organiser_id', $organiser_id );
        } else {
            delete_user_meta( $user_id, 'bh3_organiser_id' );
        }
        error_log( sprintf( 'BH3 organiser link: actor=%d user=%d from=%d to=%d', get_current_user_id(), $user_id, $previous, $organiser_id ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-leader-links', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render() {
        if ( ! BH_Leader_Access_3::is_staging() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Not permitted.', '', array( 'response' => 403 ) );
        }
        echo '<div class="wrap" style="max-width:850px"><h1>Leader account links</h1><p>Link a verified WordPress account to an organiser in the new BH3 WordPress tables. Verify the claim before saving. Enter 0 to revoke a link.</p>';
        if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success"><p>Account link saved.</p></div>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bh3_leader_link">';
        wp_nonce_field( 'bh3_leader_link' );
        echo '<table class="form-table"><tr><th><label for="bh3_link_user">WordPress user ID</label></th><td><input id="bh3_link_user" type="number" name="user_id" min="1" required></td></tr>';
        echo '<tr><th><label for="bh3_link_org">BH3 organiser ID</label></th><td><input id="bh3_link_org" type="number" name="organiser_id" min="0" required><p class="description">Use the organiser ID from the new WordPress tables, not the legacy database.</p></td></tr></table>';
        submit_button( 'Save verified link' );
        echo '</form></div>';
    }
}
