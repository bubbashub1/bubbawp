<?php
/** Staging-only read-only activity detail shortcode, using the new WordPress tables. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Activity_Detail_3 {
    public static function register() {
        add_shortcode( 'bubbahub_activity', array( __CLASS__, 'render' ) );
    }
    public static function render( $atts = array() ) {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            return '<p>Activity detail is available on staging only.</p>';
        }
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bubbahub_activity' );
        $id = absint( $atts['id'] );
        if ( ! $id && isset( $_GET['bh_activity'] ) && is_scalar( $_GET['bh_activity'] ) ) {
            $id = absint( $_GET['bh_activity'] );
        }
        if ( ! $id ) { return '<p>Choose an activity from the directory.</p>'; }
        global $wpdb;
        $a = $wpdb->prefix . 'activities';
        $o = $wpdb->prefix . 'organisers';
        $v = $wpdb->prefix . 'venues';
        $av = $wpdb->prefix . 'activity_venues';
        $s = $wpdb->prefix . 'sessions';
        foreach ( array( $a, $o, $v, $av, $s ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return '<p>Activity tables are not ready on staging.</p>';
            }
        }
        $activity = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,o.name AS organiser_name,o.website_url AS organiser_website FROM `{$a}` a
             LEFT JOIN `{$o}` o ON o.id=a.organiser_id
             WHERE a.id=%d AND a.status IN ('published','publish') LIMIT 1", $id
        ), ARRAY_A );
        if ( ! $activity ) { return '<p>Activity not found or not published.</p>'; }
        $venues = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.id,v.name,v.address_line1,v.town,v.postcode,v.region FROM `{$av}` av
             INNER JOIN `{$v}` v ON v.id=av.venue_id WHERE av.activity_id=%d ORDER BY v.name ASC LIMIT 50", $id
        ), ARRAY_A );
        $sessions = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.venue_id,s.day_of_week,s.start_time,s.end_time,s.frequency,s.term_time_only,s.start_date,s.end_date,s.status,v.name AS venue_name
             FROM `{$s}` s INNER JOIN `{$av}` av ON av.activity_id=s.activity_id AND av.venue_id=s.venue_id
             INNER JOIN `{$v}` v ON v.id=s.venue_id
             WHERE s.activity_id=%d ORDER BY s.day_of_week,s.start_time LIMIT 100", $id
        ), ARRAY_A );
        $days = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
        ob_start();
        echo '<article class="bh3-activity-detail"><style>
          .bh3-activity-detail{max-width:1050px;margin:0 auto;padding:28px 16px;color:#173c34}
          .bh3-activity-detail *{box-sizing:border-box}.bh3-activity-detail header{padding:28px;border-radius:18px;background:#e7f3eb}
          .bh3-activity-detail h1{margin:8px 0}.bh3-activity-detail .bh3-chips{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}
          .bh3-activity-detail .bh3-chip{background:#f2f7f4;border:1px solid #d8e6dc;border-radius:24px;padding:8px 13px}
          .bh3-activity-detail section{margin:26px 0}.bh3-activity-detail .bh3-venues{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
          .bh3-activity-detail .bh3-venue{padding:18px;border:1px solid #d8e6dc;border-radius:12px}
          .bh3-activity-detail .bh3-table-wrap{overflow-x:auto}.bh3-activity-detail table{width:100%;border-collapse:collapse;min-width:550px}
          .bh3-activity-detail th,.bh3-activity-detail td{padding:12px;border-bottom:1px solid #d8e6dc;text-align:left}
          .bh3-activity-detail .bh3-book{display:inline-block;padding:12px 18px;border-radius:10px;background:#175c46;color:#fff;text-decoration:none}
        </style><header><div>' . esc_html( (string) $activity['category'] ) . '</div><h1>' . esc_html( $activity['title'] ) . '</h1>';
        if ( $activity['organiser_name'] ) { echo '<p>By ' . esc_html( $activity['organiser_name'] ) . '</p>'; }
        echo '</header><div class="bh3-chips">';
        echo '<span class="bh3-chip">' . ( (int) $activity['is_free'] ? 'Free' : ( $activity['price_from'] !== null ? 'From £' . esc_html( number_format( (float) $activity['price_from'], 2 ) ) : 'Price on enquiry' ) ) . '</span>';
        if ( $activity['age_min_months'] !== null || $activity['age_max_months'] !== null ) {
            echo '<span class="bh3-chip">Age: ' . esc_html( (string) ( $activity['age_min_months'] ?? '0' ) ) . '–' . esc_html( (string) ( $activity['age_max_months'] ?? 'any' ) ) . ' months</span>';
        }
        echo '</div><section><h2>About this activity</h2><div>' . wp_kses_post( wpautop( (string) $activity['description'] ) ) . '</div></section>';
        if ( $venues ) {
            echo '<section><h2>Locations</h2><div class="bh3-venues">';
            foreach ( $venues as $venue ) {
                echo '<div class="bh3-venue"><strong>' . esc_html( $venue['name'] ) . '</strong><p>' .
                    esc_html( implode( ', ', array_filter( array( $venue['address_line1'], $venue['town'], $venue['postcode'] ) ) ) ) . '</p></div>';
            }
            echo '</div></section>';
        }
        echo '<section><h2>Weekly timetable</h2>';
        if ( $sessions ) {
            echo '<div class="bh3-table-wrap"><table><thead><tr><th>Day</th><th>Time</th><th>Venue</th><th>Details</th></tr></thead><tbody>';
            foreach ( $sessions as $session ) {
                $day = $days[(int) $session['day_of_week']] ?? 'Other';
                $time = substr( (string) $session['start_time'], 0, 5 ) . ( $session['end_time'] ? '–' . substr( (string) $session['end_time'], 0, 5 ) : '' );
                $details = array_filter( array( $session['frequency'], (int) $session['term_time_only'] ? 'Term time' : '', $session['status'] ) );
                echo '<tr><td>' . esc_html( $day ) . '</td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $session['venue_name'] ) . '</td><td>' . esc_html( implode( ' · ', $details ) ) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        } else { echo '<p>No timetable has been added yet.</p>'; }
        echo '</section>';
        $booking = (string) $activity['booking_url'];
        if ( $booking && in_array( strtolower( (string) wp_parse_url( $booking, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
            echo '<p><a class="bh3-book" href="' . esc_url( $booking ) . '" rel="noopener noreferrer" target="_blank">Book with organiser</a></p>';
        }
        if ( $activity['organiser_website'] ) {
            echo '<p><a href="' . esc_url( $activity['organiser_website'] ) . '" rel="noopener noreferrer" target="_blank">Organiser website</a></p>';
        }
        echo '</article>';
        return ob_get_clean();
    }
}
