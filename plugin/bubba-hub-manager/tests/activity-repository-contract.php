<?php
/**
 * Pure contract tests with WordPress/wpdb stubs; no network or database access.
 * Run: php plugin/bubba-hub-manager/tests/activity-repository-contract.php
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
function __( $text, $domain = '' ) { return $text; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
class WP_Error {
    public $code;
    public function __construct( $code, $message ) { $this->code = $code; }
}
class FakeDb {
    public $last_error = '';
    public $queries = array();
    public $table_exists = true;
    public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
    public function prepare( $sql, ...$args ) {
        $args = count( $args ) === 1 && is_array( $args[0] ) ? $args[0] : $args;
        return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $sql ), $args );
    }
    public function get_var( $sql ) { return $this->table_exists ? 'bh_activities' : null; }
    public function get_results( $sql, $format ) {
        $this->queries[] = $sql;
        return array( array( 'id' => '10', 'title' => 'Example Class', 'slug' => 'example-class' ) );
    }
}
require dirname(__DIR__) . '/includes/class-bh-activity-repository.php';
$db = new FakeDb();
$repo = new BH_Activity_Repository_3( $db );
$rows = $repo->search( array( 'page' => 0, 'per_page' => 999, 'search' => "baby%" ) );
if ( count( $rows ) !== 1 || $rows[0]['id'] !== 10 ) { throw new RuntimeException( 'Result contract failed' ); }
$sql = $db->queries[0];
if ( strpos( $sql, 'LIMIT 24 OFFSET 0' ) === false ) { throw new RuntimeException( 'Pagination clamp failed' ); }
if ( strpos( $sql, 'baby\\%' ) === false ) { throw new RuntimeException( 'Search wildcard escaping failed' ); }
if ( ! preg_match( '/^SELECT /', $sql ) ) { throw new RuntimeException( 'Not read-only' ); }
$db->table_exists = false;
if ( ! ( $repo->search() instanceof WP_Error ) ) { throw new RuntimeException( 'Missing table fallback failed' ); }
echo "Activity repository contract tests passed.\n";
