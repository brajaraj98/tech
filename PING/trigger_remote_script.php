<?php
// This is the list of stations that are allowed to be used.
// It acts as a "whitelist" for security.
$allowed_stations = [ 
    'Ganjam', 'ASKA', 'BHANJANAGAR', 'CHATRAPUR', 'BUGUDA', 
    'DIGAPAHANDI', 'GRAMANYAYALAYA', 'HINJILI', 'KABISURYANAGAR', 
    'KHALLIKOTE', 'KODALA', 'PATRAPUR', 'PURUSHOTTAMPUR', 'SDJM', 
    'SERAGADA', 'SORODA' 
];

// Get the station name and IP from the web page request
$station = $_POST['station'] ?? '';
$ip = $_POST['ip'] ?? '';

// --- SECURITY VALIDATION ---

// 1. Validate the Station Name
$station_found = false;
foreach ($allowed_stations as $allowed) {
    if (strcasecmp($station, $allowed) == 0) {
        $station_found = true;
        break;
    }
}
if (!$station_found) {
    die("Error: Invalid station name provided.");
}

// 2. Validate the IP Address
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    die("Error: Invalid IP address format.");
}

// --- SECURE EXECUTION ---

// Sanitize inputs to prevent command injection attacks.
$safe_ip = escapeshellarg($ip);

// Define the path to the script on the remote server
$remote_script_path = '/root/backup.sh'; 

// Construct the full SSH command. The -o options prevent errors and timeouts.
$command = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 root@$safe_ip " . escapeshellarg($remote_script_path);

// Execute the command and capture its output
// The `2>&1` part ensures we also capture any error messages from the script.
$output = shell_exec($command . ' 2>&1');

// Check for connection errors or empty output
if ($output === null || trim($output) === '') {
    echo "<pre>Failed to connect to the station or the script produced no output.\n\nPlease check if the server is online and accessible.</pre>";
} else {
    // Send the output back to the webpage
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}
?>
