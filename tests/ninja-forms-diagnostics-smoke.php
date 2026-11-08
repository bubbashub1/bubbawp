<?php
/** Read-only Ninja Forms diagnostics smoke test; no WordPress or submission data is required. */
define( 'ABSPATH', __DIR__ );
class WP_Error { public $code; public $message; public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; } }
function current_user_can( $cap ) { return $cap === 'manage_options'; }
function get_plugins() { return array( 'ninja-forms/ninja-forms.php' => array( 'Version' => '3.15.0' ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url() { return 'https://staging.bubbahub.co.uk'; }
class BH_NF_Test_Model {
    private $id; private $settings;
    public function __construct( $id, $settings ) { $this->id = $id; $this->settings = $settings; }
    public function get_id() { return $this->id; }
    public function get_settings() { return $this->settings; }
}
class BH_NF_Test_Form {
    public function get_fields() { return array( new BH_NF_Test_Model( 11, array( 'key' => 'attendee_choice', 'label' => 'Who is attending?', 'type' => 'listcheckbox' ) ) ); }
    public function get_actions() { return array( new BH_NF_Test_Model( 21, array( 'type' => 'save' ) ), new BH_NF_Test_Model( 22, array( 'type' => 'bh_booking_hook' ) ) ); }
}
class BH_NF_Test_Forms {
    public function get_forms() { return array( new BH_NF_Test_Model( 2, array( 'title' => 'Family booking' ) ) ); }
}
class BH_NF_Test_API {
    public function form( $id = null ) { return $id ? new BH_NF_Test_Form() : new BH_NF_Test_Forms(); }
}
function Ninja_Forms() { return new BH_NF_Test_API(); }
require dirname( __DIR__ ) . '/plugin/bubba-hub-manager/includes/class-bh-ninja-forms-diagnostics.php';
$result = BH_Ninja_Forms_Diagnostics::inspect();
if ( ! is_array( $result ) || $result['plugin_version'] !== '3.15.0' || $result['form_count'] !== 1 ) { fwrite( STDERR, "FAIL: form/version discovery\n" ); exit( 1 ); }
$form = $result['forms'][0];
if ( $form['id'] !== 2 || $form['title'] !== 'Family booking' ) { fwrite( STDERR, "FAIL: form metadata\n" ); exit( 1 ); }
if ( $form['fields'][0]['key'] !== 'attendee_choice' || $form['fields'][0]['label'] !== 'Who is attending?' ) { fwrite( STDERR, "FAIL: field key discovery\n" ); exit( 1 ); }
if ( count( $form['actions'] ) !== 2 || $result['submission_data_read'] !== false || $result['booking_writes_performed'] !== false ) { fwrite( STDERR, "FAIL: read-only action discovery\n" ); exit( 1 ); }
echo "PASS: read-only Ninja Forms form, field and action discovery\n";
