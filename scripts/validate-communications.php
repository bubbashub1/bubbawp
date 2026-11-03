<?php
/**
 * Lightweight source-contract checks for the communications module.
 */
$path = __DIR__ . '/../plugin/bubba-hub-manager/includes/class-bh-communications.php';
$source = file_get_contents( $path );
if ( false === $source ) {
    fwrite( STDERR, "Communications module missing.\n" );
    exit( 1 );
}
$required = array(
    'staging host guard'       => 'staging.bubbahub.co.uk',
    'explicit enable option'   => 'bh3_communications_enabled',
    'double opt-in'            => '_bh3_confirm_hash',
    'unsubscribe route'        => 'bh3_unsubscribe',
    'active subscriber filter' => "'meta_value' => 'active'",
    'batched sending'          => 'BATCH_SIZE',
    'central success log'      => 'wp_mail_succeeded',
    'central failure log'      => 'wp_mail_failed',
    'transactional mail API'   => 'send_transactional',
    'bounded log retention'    => 'LOG_RETENTION_DAYS',
    'template save action'     => 'admin_post_bh3_save_template',
    'template test action'     => 'admin_post_bh3_test_template',
    'claim approval adapter'   => 'notify_claim_approved',
    'claim invitation adapter' => 'notify_claim_invitation',
);
$failed = false;
foreach ( $required as $label => $needle ) {
    if ( false === strpos( $source, $needle ) ) {
        fwrite( STDERR, "Missing communications contract: {$label}\n" );
        $failed = true;
    }
}
if ( $failed ) {
    exit( 1 );
}
echo "Communications safety contracts present.\n";

$template_path = __DIR__ . '/../plugin/bubba-hub-manager/includes/class-bh-email-templates.php';
$template_source = file_get_contents( $template_path );
foreach ( array( 'newsletter', 'welcome', 'claim_invitation', 'claim_approved', 'booking_confirmation', 'booking_reminder' ) as $template ) {
    if ( false === $template_source || false === strpos( $template_source, "'" . $template . "'" ) ) {
        fwrite( STDERR, "Missing branded template: {$template}\\n" );
        exit( 1 );
    }
}
echo "Branded template contracts present.\\n";
