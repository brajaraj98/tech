<?php
// This script checks the last modified time of the scanner results file.
// It's a very fast and lightweight way for the browser to see if the scan is done.

define('SCANNER_RESULTS_FILE', __DIR__ . '/scanner_results.json');

header('Content-Type: application/json');

// Get the last modified time of the file. Returns 0 if the file doesn't exist.
$last_modified_timestamp = file_exists(SCANNER_RESULTS_FILE) ? filemtime(SCANNER_RESULTS_FILE) : 0;

echo json_encode(['last_scan_timestamp' => $last_modified_timestamp]);
?>
