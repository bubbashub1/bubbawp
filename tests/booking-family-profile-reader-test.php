<?php
/** Standalone regression checks; run: php tests/booking-family-profile-reader-test.php */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['test_user_id'] = 7;
$GLOBALS['test_table_exists'] = true;
$GLOBALS['test_hooks'] = array();
function is_user_logged_in() { return $GLOBALS['test_user_id'] > 0; }
function get_current_user_id() { return $GLOBALS['test_user_id']; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['test_hooks'][$tag] = $callback; }
class BH_Test_WPDB {
    public $prefix = 'wp_';
    public $queries = array();
    public function esc_like( $s ) { return $s; }
    public function prepare( $query, $value ) {
        return str_replace( '%s', "'" . $value . "'", str_replace( '%d', (string) (int) $value, $query ) );
    }
    public function get_var( $sql ) { return $GLOBALS['test_table_exists'] ? 'wp_bh_family_profiles' : null; }
    public function get_results( $sql, $format ) {
        $this->queries[] = $sql;
        $expected = 'user_id = ' . (int) $GLOBALS['test_user_id'];
        if ( false === strpos( $sql, $expected ) ) { throw new Exception( 'Missing current-user owner filter' ); }
        if ( 7 !== (int) $GLOBALS['test_user_id'] ) { return array(); }
        return array(
            array( 'id' => '12', 'profile_type' => 'child', 'display_name' => '<b>Alex</b>' ),
            array( 'id' => '13', 'profile_type' => 'bump', 'display_name' => 'Baby' ),
            array( 'id' => '14', 'profile_type' => 'unknown', 'display_name' => 'Hidden' ),
        );
    }
}
$GLOBALS['wpdb'] = new BH_Test_WPDB();
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-booking-family-profile-reader.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } }
$rows = BH_Booking_Family_Profile_Reader::owned_options();
check( count( $rows ) === 2, 'Only valid profile types should be returned' );
check( $rows[0]['label'] === 'Alex', 'Labels must be sanitised' );
check( BH_Booking_Family_Profile_Reader::owns_profile( '12' ), 'Owned child should be accepted' );
check( ! BH_Booking_Family_Profile_Reader::owns_profile( '14' ), 'Unknown type should be rejected' );
check( ! BH_Booking_Family_Profile_Reader::owns_profile( '999' ), 'Foreign ID should be rejected' );
check( BH_Booking_Family_Profile_Reader::owns_child_profile( '12' ), 'Owned child should be valid as an attendee' );
check( ! BH_Booking_Family_Profile_Reader::owns_child_profile( '13' ), 'Pregnancy profiles must not be accepted as child attendees' );

$options = BH_Booking_Family_Profile_Reader::filter_ninja_options( array(), array( 'key' => BH_Booking_Family_Profile_Reader::NINJA_FIELD_KEY ) );
$values = array_column( $options, 'value' );
check( in_array( '12', $values, true ), 'Owned child should appear when matched by Field Key' );
check( ! in_array( '13', $values, true ), 'Bump profile must not appear as an attendee' );
check( in_array( 'guest', $values, true ), 'Manual guest route should remain available' );

$label_options = BH_Booking_Family_Profile_Reader::filter_ninja_options(
    array(),
    array( 'label' => BH_Booking_FAMILY_PROFILE_READER::NINJA_FIELD_LABEL )
);
check( in_array( '12', array_column( $label_options, 'value' ), true ), 'Modern builder exact-label fallback should populate child options' );

$untouched = array( array( 'label' => 'Existing option', 'value' => 'existing' ) );
check( BH_Booking_Family_Profile_Reader::filter_ninja_options( $untouched, array( 'key' => 'another_field', 'label' => 'Another field' ) ) === $untouched, 'Unrelated Ninja Forms fields must remain unchanged' );
BH_Booking_Family_Profile_Reader::register();
check( isset( $GLOBALS['test_hooks']['ninja_forms_render_options'] ), 'Ninja Forms options filter should register' );
check( ! BH_Booking_Family_Profile_Reader::owns_profile( '12 OR 1=1' ), 'Injection must be rejected' );
$GLOBALS['test_user_id'] = 8;
check( BH_Booking_Family_Profile_Reader::owned_options() === array(), 'A different WordPress account must not receive another account\'s profiles' );
$GLOBALS['test_table_exists'] = false;
check( BH_Booking_Family_Profile_Reader::owned_options() === array(), 'Missing profile table must fail closed' );
$GLOBALS['test_table_exists'] = true;
$GLOBALS['test_user_id'] = 0;
check( BH_Booking_Family_Profile_Reader::owned_options() === array(), 'Guests must see no profiles' );
check( ! BH_Booking_Family_Profile_Reader::owns_profile( '12' ), 'Guests cannot select profiles' );
echo "Family profile reader checks passed\n";
