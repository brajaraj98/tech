<?php
// This is the backend script for 24/7 monitoring.
// It now includes a daily cleanup routine for the history log.

// Set correct paths and timezone
chdir(__DIR__);
date_default_timezone_set('Asia/Kolkata');

define('STATUS_FILE', 'last_status.json');
define('HISTORY_FILE', 'status_history.csv');
define('CLEANUP_TRACKER_FILE', 'last_cleanup.txt');
define('RETENTION_DAYS', 7);

$stations = [
    'Ganjam'          => '172.19.110.138',
    'ASKA'            => '172.19.110.8',
    'BHANJANAGAR'     => '172.19.109.70',
    'CHATRAPUR'       => '172.19.109.11',
    'BUGUDA'          => '172.20.242.226',
    'DIGAPAHANDI'     => '172.19.107.75',
    'GRAMANYAYALAYA'  => '172.20.241.35',
    'HINJILI'         => '172.20.241.5',
    'KABISURYANAGAR'  => '172.20.242.165',
    'KHALLIKOTE'      => '172.19.107.135',
    'KODALA'          => '172.19.107.200',
    'PATRAPUR'        => '172.19.108.5',
    'PURUSHOTTAMPUR'  => '172.19.108.75',
    'SDJM'            => '172.19.108.195',
    'SERAGADA'        => '172.20.240.5', // <-- THE COMMA WAS MISSING HERE
    'SORODA'          => '172.19.108.130'
];

// --- Main Monitoring Logic ---
$last_statuses = file_exists(STATUS_FILE) ? json_decode(file_get_contents(STATUS_FILE), true) : [];
$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$ping_command = $is_windows ? "ping -n 1 -w 1000 " : "ping -c 1 -W 1 ";

foreach ($stations as $name => $ip) {
    exec($ping_command . escapeshellarg($ip), $output, $status);
    
    $current_status = ($status === 0) ? 'ONLINE' : 'OFFLINE';
    $previous_status = isset($last_statuses[$name]) ? $last_statuses[$name] : 'UNKNOWN';

    if ($current_status !== $previous_status) {
        $history_log = fopen(HISTORY_FILE, 'a');
        fputcsv($history_log, [date('Y-m-d H:i:s'), $name, $ip, $current_status]);
        fclose($history_log);
    }
    
    $last_statuses[$name] = $current_status;
}

file_put_contents(STATUS_FILE, json_encode($last_statuses, JSON_PRETTY_PRINT));


// --- NEW: Daily Log Cleanup Logic ---

// This function reads the log, keeps only recent events, and overwrites the file.
function prune_history_log() {
    if (!file_exists(HISTORY_FILE)) {
        return; // Nothing to clean
    }

    $fresh_events = [];
    $retention_date = new DateTime("-" . RETENTION_DAYS . " days");

    if (($read_handle = fopen(HISTORY_FILE, 'r')) !== FALSE) {
        while (($event = fgetcsv($read_handle)) !== FALSE) {
            if (count($event) < 1) continue; // Skip empty lines
            
            try {
                $event_time = new DateTime($event[0]);
                // Keep the event if it is NEWER than the retention date
                if ($event_time >= $retention_date) {
                    $fresh_events[] = $event;
                }
            } catch (Exception $e) {
                // Ignore lines with an invalid date format
                continue;
            }
        }
        fclose($read_handle);
    }

    // Write the fresh events back to the file
    if (($write_handle = fopen(HISTORY_FILE, 'w')) !== FALSE) {
        foreach ($fresh_events as $event) {
            fputcsv($write_handle, $event);
        }
        fclose($write_handle);
    }
}

// Trigger the cleanup function only once per day
$today = date('Y-m-d');
$last_cleanup_date = file_exists(CLEANUP_TRACKER_FILE) ? file_get_contents(CLEANUP_TRACKER_FILE) : '';

if ($last_cleanup_date !== $today) {
    prune_history_log();
    file_put_contents(CLEANUP_TRACKER_FILE, $today);
}
?>
