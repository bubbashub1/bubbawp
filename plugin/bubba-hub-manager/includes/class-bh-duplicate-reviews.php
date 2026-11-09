<?php
/**
 * Team moderation queue for submissions that match an existing title and venue.
 * Review requests are private WordPress records; activity listings remain drafts
 * until a team member approves them.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activity_Duplicate_Reviews_3 {
    const POST_TYPE = 'bh_dup_review';

    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_type' ) );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_post_bh3_duplicate_review_decide', array( __CLASS__, 'decide' ) );
    }

    public static function register_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array( 'name' => 'Duplicate review requests', 'singular_name' => 'Duplicate review request' ),
            'public' => false, 'show_ui' => false, 'show_in_menu' => false,
            'supports' => array( 'title', 'editor', 'author' ),
            'capability_type' => 'post', 'map_meta_cap' => true,
        ) );
    }

    public static function create_request( $activity_id, $matches, $user_id ) {
        $activity = get_user_by( 'id', absint( $user_id ) );
        $review_id = wp_insert_post( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => 'Duplicate review for activity #' . absint( $activity_id ),
            'post_content' => 'Leader has stated that this listing is genuinely different from the existing directory match.',
            'post_author' => absint( $user_id ),
        ), true );
        if ( is_wp_error( $review_id ) ) { return $review_id; }
        update_post_meta( $review_id, '_bh_activity_id', absint( $activity_id ) );
        update_post_meta( $review_id, '_bh_duplicate_matches', $matches );
        update_post_meta( $review_id, '_bh_review_status', 'pending' );
        update_post_meta( $review_id, '_bh_submitted_by', absint( $user_id ) );
        update_post_meta( $review_id, '_bh_submitted_at', current_time( 'mysql', true ) );
        return absint( $review_id );
    }

    public static function admin_menu() {
        if ( ! current_user_can( 'manage_options' ) || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_submenu_page( 'bubba-hub-3-status', 'Duplicate review queue', 'Duplicate reviews', 'manage_options', 'bh3-duplicate-reviews', array( __CLASS__, 'render_queue' ) );
    }

    public static function render_queue() {
        if ( ! current_user_can( 'manage_options' ) || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        $items = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'numberposts' => 100, 'orderby' => 'date', 'order' => 'ASC', 'meta_key' => '_bh_review_status', 'meta_value' => 'pending' ) );
        echo '<div class="wrap"><h1>Duplicate review queue</h1><p>These activities matched an existing title and venue. They remain unpublished until a team member makes a decision.</p>';
        if ( ! $items ) { echo '<p>No duplicate review requests are waiting.</p></div>'; return; }
        foreach ( $items as $item ) {
            $activity_id = absint( get_post_meta( $item->ID, '_bh_activity_id', true ) );
            $activity = self::activity_row( $activity_id );
            $matches = get_post_meta( $item->ID, '_bh_duplicate_matches', true );
            $matches = is_array( $matches ) ? $matches : array();
            $submitter = get_user_by( 'id', absint( get_post_meta( $item->ID, '_bh_submitted_by', true ) ) );
            echo '<article style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:16px 0;max-width:900px">';
            echo '<h2>' . esc_html( $activity ? $activity['title'] : $item->post_title ) . '</h2>';
            echo '<p><strong>Submitted by:</strong> ' . esc_html( $submitter ? $submitter->display_name : 'Unknown user' ) . ' · <strong>Activity ID:</strong> ' . esc_html( (string) $activity_id ) . '</p>';
            echo '<h3>Existing matching listing(s)</h3><ul>';
            foreach ( (array) ( $matches['exact'] ?? array() ) as $match ) {
                echo '<li>' . esc_html( $match['title'] ?? 'Untitled' ) . ' — ' . esc_html( implode( ', ', (array) ( $match['venues'] ?? array() ) ) ) . ' (ID ' . esc_html( (string) absint( $match['id'] ?? 0 ) ) . ')</li>';
            }
            echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bh3_duplicate_review_decide"><input type="hidden" name="review_id" value="' . esc_attr( (string) $item->ID ) . '">';
            wp_nonce_field( 'bh3_duplicate_review_' . $item->ID );
            echo '<p><label>Team note / rejection reason<br><textarea name="review_reason" rows="3" class="large-text" maxlength="1000" placeholder="Explain why this is or is not a duplicate"></textarea></label></p>';
            echo '<button class="button button-primary" name="decision" value="approve">Approve and publish</button> ';
            echo '<button class="button" name="decision" value="reject">Reject and notify leader</button></form></article>';
        }
        echo '</div>';
    }

    private static function activity_row( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT id,title,status FROM `{$wpdb->prefix}activities` WHERE id=%d LIMIT 1", absint( $id ) ), ARRAY_A );
    }

    public static function decide() {
        if ( ! current_user_can( 'manage_options' ) || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { wp_die( 'Staging administrator permission required.', '', array( 'response' => 403 ) ); }
        $review_id = absint( $_POST['review_id'] ?? 0 );
        check_admin_referer( 'bh3_duplicate_review_' . $review_id );
        $review = get_post( $review_id );
        if ( ! $review || $review->post_type !== self::POST_TYPE || get_post_meta( $review_id, '_bh_review_status', true ) !== 'pending' ) { wp_die( 'Review request not found or already decided.', '', array( 'response' => 404 ) ); }
        $decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
        $reason = sanitize_textarea_field( wp_unslash( $_POST['review_reason'] ?? '' ) );
        if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) || ( $decision === 'reject' && $reason === '' ) ) { wp_die( 'Choose a decision and provide a reason when rejecting.', '', array( 'response' => 400 ) ); }
        $activity_id = absint( get_post_meta( $review_id, '_bh_activity_id', true ) );
        global $wpdb;
        $activity = self::activity_row( $activity_id );
        if ( ! $activity || $activity['status'] !== 'draft' ) { wp_die( 'The submitted activity is no longer an unpublished draft.', '', array( 'response' => 409 ) ); }
        if ( $decision === 'approve' ) {
            $matches = get_post_meta( $review_id, '_bh_duplicate_matches', true );
            $venue_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT venue_id FROM `{$wpdb->prefix}activity_venues` WHERE activity_id=%d", $activity_id ) ) );
            $fresh = BH_Activity_Duplicates_3::find_matches( $activity['title'], $venue_ids, $activity_id );
            if ( is_wp_error( $fresh ) || empty( $matches['exact'] ) ) { wp_die( 'Could not recheck the directory. No decision was saved.', '', array( 'response' => 503 ) ); }
            $data = array( 'status' => 'published', 'updated_at' => current_time( 'mysql', true ) );
            $published_column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}activities` LIKE %s", 'published_at' ) );
            if ( $published_column === 'published_at' ) { $data['published_at'] = current_time( 'mysql', true ); }
            if ( false === $wpdb->update( $wpdb->prefix . 'activities', $data, array( 'id' => $activity_id, 'status' => 'draft' ) ) ) { wp_die( 'Could not publish the approved activity.', '', array( 'response' => 500 ) ); }
            update_post_meta( $review_id, '_bh_review_status', 'approved' );
        } else {
            update_post_meta( $review_id, '_bh_review_status', 'rejected' );
            update_post_meta( $review_id, '_bh_review_reason', $reason );
            $author_id = absint( get_post_meta( $review_id, '_bh_submitted_by', true ) );
            $user = get_user_by( 'id', $author_id );
            if ( $user && is_email( $user->user_email ) ) {
                $subject = 'Update on your Bubba Hub activity submission';
                $message = "Thank you for submitting your activity to Bubba Hub.\n\nThe team has reviewed it and has not approved it for the directory.\n\nReason: " . $reason . "\n\nIf you think we have misunderstood, please contact contact@bubbahub.co.uk.";
                wp_mail( $user->user_email, $subject, $message );
            }
        }
        update_post_meta( $review_id, '_bh_reviewed_by', get_current_user_id() );
        update_post_meta( $review_id, '_bh_reviewed_at', current_time( 'mysql', true ) );
        update_post_meta( $review_id, '_bh_review_note', $reason );
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-duplicate-reviews', 'decision' => $decision ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
