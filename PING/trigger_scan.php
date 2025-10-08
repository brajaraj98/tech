<?php
// This script starts the main network scanner in the background
// AND logs any output or errors to a file named trigger.log for debugging.

// Define the full path to your files
$backend_script_path = __DIR__ . '/network_scanner_backend.php';
$log_file_path = __DIR__ . '/trigger.log';

// Log that this script was called
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file_path, "--- Trigger script executed at: " . $timestamp . " ---\n", FILE_APPEND);

// Define the command to run the script and save ALL output (including errors) to the log file
// The final '&' makes it run in the background.
$command = "/usr/bin/php " . escapeshellarg($backend_script_path) . " >> " . escapeshellarg($log_file_path) . " 2>&1 &";

// Execute the command
exec($command);

// Log that the command was sent
file_put_contents($log_file_path, "Executed background command: " . $command . "\n", FILE_APPEND);

// Send a success response back to the browser
header('Content-Type: application/json');
echo json_encode(['message' => 'Scan initiated. Checking log for status.']);
?>
