<?php
// This is the backend script for the Network Scanner.
// It now only detects the OS and does not look for hostnames.

chdir(__DIR__);
date_default_timezone_set('Asia/Kolkata');

define('SCANNER_RESULTS_FILE', 'scanner_results.json');

// --- CONFIGURATION ---
$ip_range_start = '172.19.110.129';
$ip_range_end = '172.19.110.254';

// --- SCRIPT LOGIC ---
$results = [
    'last_scan_start' => date('Y-m-d H:i:s'),
    'last_scan_end' => '',
    'used_ips' => [],
    'unused_ips' => []
];

$start_int = ip2long($ip_range_start);
$end_int = ip2long($ip_range_end);
$is_windows_server = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// --- Helper function to guess OS from TTL ---
function guess_os_by_ttl($ttl) {
    if ($ttl > 100 && $ttl <= 128) {
        return 'Windows';
    } elseif ($ttl > 0 && $ttl <= 64) {
        return 'Linux / macOS';
    } else {
        return 'Network Device / Other';
    }
}

for ($i = $start_int; $i <= $end_int; $i++) {
    $ip = long2ip($i);
    $ping_output = []; // Reset output array for each IP
    
    $ping_command = $is_windows_server ? "ping -n 1 -w 500 " . escapeshellarg($ip) : "ping -c 1 -W 0.5 " . escapeshellarg($ip);
    exec($ping_command, $ping_output, $status);

    if ($status === 0) {
        // IP is UP (Used)
        $os = 'Unknown';

        // --- TTL Parsing and OS Guessing ---
        $ttl_line = implode(' ', $ping_output);
        if (preg_match('/ttl=(\d+)/i', $ttl_line, $matches)) {
            $ttl = (int)$matches[1];
            $os = guess_os_by_ttl($ttl);
        }
        
        $results['used_ips'][] = ['ip' => $ip, 'os' => $os];
    } else {
        // IP is DOWN (Unused)
        $results['unused_ips'][] = $ip;
    }
}

$results['last_scan_end'] = date('Y-m-d H:i:s');
file_put_contents(SCANNER_RESULTS_FILE, json_encode($results, JSON_PRETTY_PRINT));
?>
