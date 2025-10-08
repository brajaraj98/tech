<?php
// Define the path to the IP names JSON file
define('IP_NAMES_FILE', __DIR__ . '/ip_names.json');

// --- Main logic to handle form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Read the existing data from the JSON file
    $ip_names = file_exists(IP_NAMES_FILE) ? json_decode(file_get_contents(IP_NAMES_FILE), true) : [];

    // 2. Determine the action (add or delete)
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // --- ADD/UPDATE LOGIC ---
        $ip = trim($_POST['ip'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '';

        // Basic validation: ensure IP and name are not empty
        if (!empty($ip) && !empty($name)) {
            $ip_names[$ip] = [
                'name' => $name,
                'color' => $color
            ];
        }

    } elseif ($action === 'delete') {
        // --- DELETE LOGIC ---
        $ip = trim($_POST['ip'] ?? '');

        // Check if the IP exists in our list before trying to delete it
        if (isset($ip_names[$ip])) {
            unset($ip_names[$ip]);
        }
    }

    // 3. Save the modified data back to the JSON file
    // JSON_PRETTY_PRINT makes the file readable for humans
    file_put_contents(IP_NAMES_FILE, json_encode($ip_names, JSON_PRETTY_PRINT));
}

// 4. Redirect the user back to the manage tab to see the changes
header('Location: index.php?view=manage');
exit;
?>
