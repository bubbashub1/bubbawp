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
    'active subscriber filter' => "'_bh3_status', 'active'",
    'batched sending'          => 'BATCH_SIZE',
    'central success log'      => 'wp_mail_succeeded',
    'central failure log'      => 'wp_mail_failed',
    'transactional mail API'   => 'send_transactional',
    'bounded log retention'    => 'LOG_RETENTION_DAYS',
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
