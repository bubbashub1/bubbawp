<?php
/** Staging-only WordPress admin editor for site-wide school term date ranges. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Term_Dates_Admin_3 {
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_bh3_save_term_dates', array( __CLASS__, 'save' ) );
    }
    public static function menu() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_options_page( 'Bubba Hub Term Dates', 'Bubba Hub Term Dates', 'manage_options', 'bh3-term-dates', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.' ); }
        $ranges = get_option( 'bh3_term_date_ranges', array() );
        if ( ! is_array( $ranges ) ) { $ranges = array(); }
        $rows = array_pad( array_slice( $ranges, 0, 20 ), min( 20, count( $ranges ) + 3 ), array( 'start'=>'', 'end'=>'' ) );
        ?>
        <div class="wrap"><h1>Bubba Hub · School Term Dates</h1>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>Term dates saved.</p></div><?php endif; ?>
        <p>Set the date ranges when term-time classes should appear. Add one row for each term; half-term breaks should fall between ranges. No dates are assumed automatically.</p>
        <p><strong>These are site-wide dates.</strong> School and council calendars can differ, so check dates before saving.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="bh3_save_term_dates">
        <?php wp_nonce_field( 'bh3_save_term_dates' ); ?>
        <table class="widefat striped" style="max-width:800px"><thead><tr><th>Term starts</th><th>Term ends</th></tr></thead><tbody>
        <?php foreach ( $rows as $i => $range ) : ?><tr>
        <td><label class="screen-reader-text" for="bh3-start-<?php echo esc_attr( $i ); ?>">Term starts</label><input id="bh3-start-<?php echo esc_attr( $i ); ?>" type="date" name="ranges[<?php echo esc_attr( $i ); ?>][start]" value="<?php echo esc_attr( $range['start'] ?? '' ); ?>"></td>
        <td><label class="screen-reader-text" for="bh3-end-<?php echo esc_attr( $i ); ?>">Term ends</label><input id="bh3-end-<?php echo esc_attr( $i ); ?>" type="date" name="ranges[<?php echo esc_attr( $i ); ?>][end]" value="<?php echo esc_attr( $range['end'] ?? '' ); ?>"></td>
        </tr><?php endforeach; ?></tbody></table>
        <p>To add more terms, save the current dates first. Empty rows are ignored. Up to 20 date ranges are supported.</p>
        <?php submit_button( 'Save term dates' ); ?></form></div>
        <?php
    }
    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.', '', array( 'response'=>403 ) ); }
        check_admin_referer( 'bh3_save_term_dates' );
        $input = isset( $_POST['ranges'] ) && is_array( $_POST['ranges'] ) ? wp_unslash( $_POST['ranges'] ) : array();
        if ( count( $input ) > 20 ) { wp_die( 'Too many term ranges.', '', array( 'response'=>400 ) ); }
        $ranges = array();
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) { wp_die( 'Invalid term range.', '', array( 'response'=>400 ) ); }
            $start = isset( $row['start'] ) && is_string( $row['start'] ) ? $row['start'] : '';
            $end = isset( $row['end'] ) && is_string( $row['end'] ) ? $row['end'] : '';
            if ( $start === '' && $end === '' ) { continue; }
            foreach ( array( $start, $end ) as $value ) {
                $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
                if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) { wp_die( 'Both term dates must be valid.', '', array( 'response'=>400 ) ); }
            }
            if ( $end < $start ) { wp_die( 'Term end cannot precede start.', '', array( 'response'=>400 ) ); }
            $ranges[] = array( 'start'=>$start, 'end'=>$end );
        }
        usort( $ranges, static function( $a, $b ) { return strcmp( $a['start'], $b['start'] ); } );
        for ( $i = 1; $i < count( $ranges ); $i++ ) {
            if ( $ranges[$i]['start'] <= $ranges[$i-1]['end'] ) { wp_die( 'Term ranges must not overlap.', '', array( 'response'=>400 ) ); }
        }
        update_option( 'bh3_term_date_ranges', $ranges, false );
        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'options-general.php?page=bh3-term-dates' ) ) ); exit;
    }
}
