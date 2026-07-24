<?php
/**
 * Static safety contract for the IFTTT-facing Journal Automation bridge.
 *
 * Run: php tests/automation-contract.php
 */

$root     = dirname( __DIR__ );
$failures = array();
$passes   = 0;

function automation_contract_file( $root, $path ) {
    $contents = file_get_contents( $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path ) );
    if ( false === $contents ) {
        throw new RuntimeException( 'Unable to read ' . $path );
    }
    return $contents;
}

function automation_contract_assert( $condition, $message ) {
    global $failures, $passes;
    if ( $condition ) {
        $passes++;
        return;
    }
    $failures[] = $message;
}

function automation_contract_contains( $haystack, $needle, $message ) {
    automation_contract_assert( false !== strpos( $haystack, $needle ), $message );
}

function automation_contract_not_contains( $haystack, $needle, $message ) {
    automation_contract_assert( false === strpos( $haystack, $needle ), $message );
}

$main       = automation_contract_file( $root, 'lunara-journal-foundation.php' );
$automation = automation_contract_file( $root, 'includes/class-lunara-journal-automation.php' );
$readme     = automation_contract_file( $root, 'README.md' );

automation_contract_contains( $main, "includes/class-lunara-journal-automation.php", 'Foundation must load the dedicated automation module.' );
automation_contract_contains( $main, 'Lunara_Journal_Automation::bootstrap();', 'Foundation must bootstrap the automation module.' );
automation_contract_contains( $main, "'ifttt_operator'", 'Foundation must define a dedicated IFTTT access profile.' );

if ( preg_match( "/'ifttt_operator'\s*=>\s*array\((.*?)\R\s*\),\R\s*'dalton_admin'/s", $main, $ifttt_profile ) ) {
    automation_contract_contains( $ifttt_profile[1], "'capture'", 'IFTTT profile must capture private editorial signals.' );
    automation_contract_contains( $ifttt_profile[1], "'run_dispatch'", 'IFTTT profile must queue Dispatch through the tested asynchronous path.' );
    automation_contract_contains( $ifttt_profile[1], "'notify'", 'IFTTT profile must have a narrow notification trigger scope.' );
    automation_contract_not_contains( $ifttt_profile[1], "'automation_read'", 'IFTTT profile must not read the private inbox or status snapshot.' );
    automation_contract_not_contains( $ifttt_profile[1], "'audit'", 'IFTTT profile must not read the broader Journal audit API.' );
    automation_contract_not_contains( $ifttt_profile[1], "'publish'", 'IFTTT profile must never receive publish scope.' );
    automation_contract_not_contains( $ifttt_profile[1], "'*'", 'IFTTT profile must never receive wildcard scope.' );
} else {
    automation_contract_assert( false, 'Unable to isolate the default IFTTT access profile.' );
}

automation_contract_contains( $main, "'/journal/automation/run-dispatch'", 'Scope routing must recognize the automation Dispatch endpoint.' );
automation_contract_contains( $main, "return 'run_dispatch';", 'Automation Dispatch must require the run_dispatch scope.' );
automation_contract_contains( $main, "return 'capture';", 'Automation capture must require the capture scope.' );
automation_contract_contains( $main, "return 'notify';", 'Morning Desk must require the notify scope instead of a read-only scope.' );
automation_contract_contains( $main, "return 'automation_read';", 'Automation status operations must require automation_read.' );

foreach ( array(
    "'/journal/automation/status'",
    "'/journal/automation/inbox'",
    "'/journal/automation/capture'",
    "'/journal/automation/run-dispatch'",
    "'/journal/automation/morning-desk'",
) as $route ) {
    automation_contract_contains( $automation, $route, 'Missing automation route ' . $route . '.' );
}

automation_contract_contains( $automation, "array( 'Lunara_Journal_Foundation', 'rest_permissions_check' )", 'Every automation route must reuse Foundation profile authentication.' );
automation_contract_contains( $automation, "array( 'idea', 'source', 'screening' )", 'Capture types must remain allowlisted.' );
automation_contract_contains( $automation, 'request_event_id', 'Capture must normalize a stable idempotency key.' );
automation_contract_contains( $automation, 'find_inbox_item_by_event_id', 'Capture must check for an existing event before insertion.' );
automation_contract_contains( $automation, 'acquire_capture_lock', 'Capture must acquire an atomic event lock before insertion.' );
automation_contract_contains( $automation, 'release_capture_lock', 'Capture must release only its own event lock.' );
automation_contract_contains( $automation, 'add_option( $option_name', 'Capture locking must use atomic WordPress option insertion.' );
automation_contract_contains( $automation, '} finally {', 'Capture locking must release through finally on every return path.' );
automation_contract_contains( $automation, "'post_status'  => 'draft'", 'Captured editorial signals must remain private drafts.' );
automation_contract_not_contains( $automation, "'post_status'  => 'publish'", 'Automation capture must never publish.' );
automation_contract_contains( $automation, 'HISTORY_LIMIT', 'Automation history must be explicitly bounded.' );
automation_contract_contains( $automation, "defined( 'LUNARA_IFTTT_WEBHOOK_KEY' )", 'Outbound credentials must prefer a deployment constant.' );
automation_contract_contains( $automation, "getenv( 'LUNARA_IFTTT_WEBHOOK_KEY' )", 'Outbound credentials must support deployment environment configuration.' );
automation_contract_not_contains( $automation, "get_option( 'lunara_ifttt_webhook_key'", 'IFTTT Webhooks keys must not be stored in a WordPress option.' );
automation_contract_contains( $automation, 'https://maker.ifttt.com/trigger/', 'Outbound delivery must use the fixed IFTTT Webhooks host.' );
automation_contract_contains( $automation, "'lunara_needs_attention'", 'Needs Attention must have a dedicated outbound event.' );
automation_contract_contains( $automation, "'lunara_morning_desk'", 'Morning Desk must have a dedicated outbound event.' );
automation_contract_contains( $automation, "update_option_lunara_dispatch_last_run_report", 'Foundation must observe the existing Dispatch result report.' );
automation_contract_contains( $automation, "Lunara_Journal_Fast_Desk::rest_run_dispatch", 'Run Lunara must reuse the tested asynchronous Dispatch route.' );
automation_contract_contains( $automation, 'public static function admin_snapshot', 'Control Desk must receive a non-secret automation snapshot.' );
automation_contract_not_contains( $automation, 'wp_enqueue_script', 'Automation must not add public or editor JavaScript.' );
automation_contract_not_contains( $automation, 'wp_enqueue_style', 'Automation must not add public styles.' );

automation_contract_contains( $readme, 'IFTTT Pro+', 'README must document the supported IFTTT operating model.' );
automation_contract_contains( $readme, 'never publish', 'README must state the IFTTT publishing boundary.' );

if ( ! empty( $failures ) ) {
    fwrite( STDERR, "Automation contract failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo 'Automation contract passed (' . $passes . " assertions).\n";
