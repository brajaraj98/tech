<?php
date_default_timezone_set('Asia/Kolkata');
define('STATUS_FILE', __DIR__ . '/last_status.json');
define('HISTORY_FILE', __DIR__ . '/status_history.csv');
define('SCANNER_RESULTS_FILE', __DIR__ . '/scanner_results.json');
define('IP_NAMES_FILE', __DIR__ . '/ip_names.json');
define('LOG_FILE', __DIR__ . '/log.txt');

function parse_log_file($log_file_path) {
    if (!file_exists($log_file_path)) {
        return [];
    }
    $lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last_backups = [];
    foreach ($lines as $line) {
        if (preg_match('/^([\w\s_]+?)\s+SERVER DATA RECEIVED ON\s+(.*)/i', $line, $matches)) {
            $station_name = strtoupper(trim($matches[1]));
            $timestamp = trim($matches[2]);
            $last_backups[$station_name] = $timestamp;
        }
    }
    ksort($last_backups);
    return $last_backups;
}

if (file_exists(IP_NAMES_FILE)) {
    $special_ip_names = json_decode(file_get_contents(IP_NAMES_FILE), true) ?: [];
} else {
    $special_ip_names = [];
}
if (is_array($special_ip_names)) {
    ksort($special_ip_names);
}

function get_display_ip($ip, $special_names) {
    $display_ip = htmlspecialchars($ip);
    if (array_key_exists($ip, $special_names)) {
        $name_data = $special_names[$ip];
        $display_ip .= ' <span style="font-weight: bold; color: ' . $name_data['color'] . ';">(' . htmlspecialchars($name_data['name']) . ')</span>';
    }
    return $display_ip;
}

$active_view = 'dashboard';
if (isset($_GET['view']) && in_array($_GET['view'], ['scanner', 'history', 'manage', 'logreport'])) {
    $active_view = $_GET['view'];
}

$log_report_data = parse_log_file(LOG_FILE);
$stations = [ 'Ganjam'=>'172.19.110.138', 'ASKA'=>'172.19.110.8', 'BHANJANAGAR'=>'172.19.109.70', 'CHATRAPUR'=>'172.19.109.11', 'BUGUDA'=>'172.20.242.226', 'DIGAPAHANDI'=>'172.19.107.75', 'GRAMANYAYALAYA'=>'172.20.241.35', 'HINJILI'=>'172.20.241.5', 'KABISURYANAGAR'=>'172.20.242.165', 'KHALLIKOTE'=>'172.19.107.135', 'KODALA'=>'172.19.107.200', 'PATRAPUR'=>'172.19.108.5', 'PURUSHOTTAMPUR'=>'172.19.108.75', 'SDJM'=>'172.19.108.195', 'SERAGADA'=>'172.20.240.5', 'SORODA'=>'172.19.108.130' ];
$station_names_sorted = array_keys($stations);
sort($station_names_sorted);
$last_statuses = file_exists(STATUS_FILE) ? json_decode(file_get_contents(STATUS_FILE), true) : [];
$up_count = 0;
foreach($last_statuses as $status) { if ($status === 'ONLINE') $up_count++; }
$total_stations = count($stations);
$down_count = $total_stations - $up_count;
$downtime_summary = [];

if (file_exists(HISTORY_FILE)) {
    $all_history = [];
    $file = fopen(HISTORY_FILE, 'r');
    while (($line = fgetcsv($file)) !== FALSE) { if (count($line) >= 4) { $all_history[] = $line; } }
    fclose($file);
    $open_downtimes = [];
    foreach ($all_history as $event) {
        list($timestamp, $station, $ip, $status) = $event;
        if ($status === 'OFFLINE' && !isset($open_downtimes[$station])) { $open_downtimes[$station] = ['start' => $timestamp, 'ip' => $ip]; } 
        elseif ($status === 'ONLINE' && isset($open_downtimes[$station])) {
            $start_time = new DateTime($open_downtimes[$station]['start']);
            $end_time = new DateTime($timestamp);
            $interval = $start_time->diff($end_time);
            $downtime_summary[] = ['station' => $station, 'ip' => $open_downtimes[$station]['ip'], 'start' => $start_time->format('M d, Y g:i A'), 'end' => $end_time->format('M d, Y g:i A'), 'duration' => $interval->format('%d d, %h h, %i m, %s s'), 'start_date_iso' => $start_time->format('Y-m-d'), 'start_obj' => $start_time];
            unset($open_downtimes[$station]);
        }
    }
    foreach($open_downtimes as $station => $data) {
        $start_time = new DateTime($data['start']);
        $end_time = new DateTime('now');
        $interval = $start_time->diff($end_time);
        $downtime_summary[] = ['station' => $station, 'ip' => $data['ip'], 'start' => $start_time->format('M d, Y g:i A'), 'end' => '<span style="color:var(--red-color);">Still Offline</span>', 'duration' => $interval->format('%d d, %h h, %i m, %s s'), 'start_date_iso' => $start_time->format('Y-m-d'), 'start_obj' => $start_time];
    }
    
    usort($downtime_summary, function($a, $b) {
        $is_a_offline = strpos($a['end'], 'Still Offline') !== false;
        $is_b_offline = strpos($b['end'], 'Still Offline') !== false;
        if ($is_a_offline && !$is_b_offline) { return -1; }
        if (!$is_a_offline && $is_b_offline) { return 1; }
        return $b['start_obj'] <=> $a['start_obj'];
    });
}
$scanner_results = file_exists(SCANNER_RESULTS_FILE) ? json_decode(file_get_contents(SCANNER_RESULTS_FILE), true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>24/7 Network Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f0f2f5; --card-bg-color: #ffffff; --text-color: #333; --text-light-color: #888;
            --border-color: #e8e8e8; --green-color: #28a745; --red-color: #dc3545; --blue-color: #007bff;
            --orange-color: #fd7e14; --purple-color: #6f42c1;
            --active-border: #0d6efd; --table-header-bg: #f8f9fa; --hover-bg: #fafafa;
        }
        body.dark-mode {
            --bg-color: #121212; --card-bg-color: #1e1e1e; --text-color: #e0e0e0; --text-light-color: #a0a0a0;
            --border-color: #333333; --active-border: #4dabf7; --table-header-bg: #2c2c2c; --hover-bg: #252525;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); color: var(--text-color); margin: 0; padding: 20px; transition: background-color 0.3s, color 0.3s; }
        .dashboard-container { max-width: 1200px; margin: 0 auto; }
        .dashboard-header { background-color: var(--card-bg-color); border-radius: 8px; padding: 15px 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; transition: background-color 0.3s, box-shadow 0.3s; border: 2px solid transparent; }
        .dashboard-header h1 { margin: 0; font-size: 1.5em; }
        .header-controls { display: flex; align-items: center; gap: 20px; }
        .refresh-controls { display: flex; align-items: center; gap: 15px; font-size: 0.9em; color: var(--text-light-color); }
        .refresh-button { background-color: var(--blue-color); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 600; transition: background-color 0.2s; }
        .refresh-button:hover { background-color: #0056b3; }
        .refresh-button:disabled { background-color: #5a6268; cursor: not-allowed; }
        .tabs { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); }
        .tab-button { background: none; border: none; color: var(--text-light-color); font-size: 1.1em; font-family: 'Poppins', sans-serif; padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-button.active { border-bottom-color: var(--active-border); color: var(--active-border); font-weight: 600; }
        .view-container { display: none; } .view-container.active { display: block; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background-color: var(--card-bg-color); padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; align-items: center; border: 2px solid transparent; cursor: pointer; transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.active { border-color: var(--active-border); }
        .stat-card .icon { font-size: 2em; margin-right: 15px; padding: 10px; border-radius: 50%; color: white; }
        .stat-card.total .icon { background-color: var(--blue-color); } .stat-card.online .icon { background-color: var(--green-color); } .stat-card.offline .icon { background-color: var(--red-color); }
        .stat-card .value { font-size: 1.8em; font-weight: 700; }
        .stat-card .label { color: var(--text-light-color); font-size: 0.9em; }
        .content-container { background-color: var(--card-bg-color); padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: background-color 0.3s, box-shadow 0.3s; border: 2px solid transparent; }
        .content-container h2 { margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .station-grid { display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-start; }
        .station-box { padding: 8px 15px; border-radius: 6px; color: white; font-weight: 600; font-size: 0.9em; text-align: center; flex-grow: 1; min-width: 150px; }
        .station-box.up { background-color: var(--green-color); } .station-box.down { background-color: var(--red-color); } .station-box.hidden { display: none; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .history-table th { background-color: var(--table-header-bg); font-weight: 600; } .history-table tr:hover { background-color: var(--hover-bg); }
        .theme-cycle-button { background: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: 50%; width: 40px; height: 40px; font-size: 1.4em; cursor: pointer; display: flex; justify-content: center; align-items: center; padding: 0; transition: all 0.3s ease; }
        .theme-cycle-button:hover { border-color: var(--active-border); transform: rotate(20deg) scale(1.1); }
        @keyframes rgb-border-anim { 0% { border-color: #ff0000; } 15% { border-color: #ff7f00; } 30% { border-color: #ffff00; } 45% { border-color: #00ff00; } 60% { border-color: #0000ff; } 75% { border-color: #4b0082; } 90% { border-color: #8f00ff; } 100% { border-color: #ff0000; } }
        .rgb-icon { display: flex; justify-content: center; align-items: center; width: 28px; height: 28px; border-radius: 50%; font-size: 10px; font-weight: 600; font-family: 'Poppins', sans-serif; color: var(--text-color); border: 2px solid; animation: rgb-border-anim 4s linear infinite; }
        @keyframes rgb-glow { 0% { box-shadow: 0 0 8px 2px #ff0000; } 15% { box-shadow: 0 0 8px 2px #ff7f00; } 30% { box-shadow: 0 0 8px 2px #ffff00; } 45% { box-shadow: 0 0 8px 2px #00ff00; } 60% { box-shadow: 0 0 8px 2px #0000ff; } 75% { box-shadow: 0 0 8px 2px #4b0082; } 90% { box-shadow: 0 0 8px 2px #8f00ff; } 100% { box-shadow: 0 0 8px 2px #ff0000; } }
        body.rgb-mode .dashboard-header, body.rgb-mode .stat-card, body.rgb-mode .content-container { animation: rgb-glow 4s linear infinite; }
        .history-controls { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; margin-bottom: 20px; gap: 15px; }
        .history-controls label { font-weight: 600; }
        .history-controls input, .history-controls select { padding: 8px; border-radius: 5px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-color); font-family: 'Poppins', sans-serif; }
        .scanner-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .scanner-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
        .ip-card { background-color: var(--bg-color); border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; }
        .ip-card-header { display: flex; align-items: center; margin-bottom: 10px; }
        .ip-card-header .status-dot { width: 12px; height: 12px; border-radius: 50%; margin-right: 10px; background-color: var(--green-color); }
        .ip-card-header .ip-address { font-weight: 600; font-size: 1.1em; }
        .ip-card-body .os { font-size: 0.9em; color: var(--text-light-color); }
        .ip-card-body .os-icon { margin-right: 5px; }
        .unused-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .unused-ip { background-color: var(--bg-color); border: 1px solid var(--border-color); padding: 5px 10px; font-family: monospace; font-size: 0.8em; border-radius: 4px; color: var(--text-light-color); }
        .password-prompt { max-width: 400px; margin: 40px auto; text-align: center; padding: 30px; border: 1px solid var(--border-color); border-radius: 8px; }
        .password-prompt h3 { margin-top: 0; }
        .password-prompt .form-group { text-align: left; }
        .manage-container { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .manage-form { display: flex; flex-direction: column; gap: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group select { padding: 8px 12px; border-radius: 5px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-color); font-family: 'Poppins', sans-serif; font-size: 1em; }
        .delete-button { background-color: var(--red-color); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .toggle-button { background: none; border: 1px solid var(--border-color); color: var(--text-color); padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 20px; }
        @media (max-width: 768px) { .manage-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>🛰️ 24/7 Network Dashboard</h1>
            <div class="header-controls">
                <div class="refresh-controls">
                    <span id="refresh-timer"></span>
                    <button class="refresh-button" onclick="location.href='index.php'">🔄 Refresh Now</button>
                </div>
                <button id="theme-cycle-btn" class="theme-cycle-button" title="Change Theme"></button>
            </div>
        </header>

        <nav class="tabs">
            <button class="tab-button <?php if ($active_view === 'dashboard') echo 'active'; ?>" data-view="dashboard-view">📊 Dashboard</button>
            <button class="tab-button <?php if ($active_view === 'scanner') echo 'active'; ?>" data-view="scanner-view">📡 Network Scanner</button>
            <button class="tab-button <?php if ($active_view === 'history') echo 'active'; ?>" data-view="history-view">📜 History</button>
            <button class="tab-button <?php if ($active_view === 'manage') echo 'active'; ?>" data-view="manage-view">⚙️ Manage IP Names</button>
            <button class="tab-button <?php if ($active_view === 'logreport') echo 'active'; ?>" data-view="logreport-view">📄 Log Report</button>
        </nav>

        <main>
            <div id="dashboard-view" class="view-container <?php if ($active_view === 'dashboard') echo 'active'; ?>">
                <section class="summary-cards">
                    <div class="stat-card total active" data-filter="all"><div class="icon">📡</div><div><div class="value"><?php echo $total_stations; ?></div><div class="label">Total</div></div></div>
                    <div class="stat-card online" data-filter="online"><div class="icon">✅</div><div><div class="value"><?php echo $up_count; ?></div><div class="label">Online</div></div></div>
                    <div class="stat-card offline" data-filter="offline"><div class="icon">❌</div><div><div class="value"><?php echo $down_count; ?></div><div class="label">Offline</div></div></div>
                </section>
                <section class="content-container" id="status-list" data-total-count="<?php echo $total_stations; ?>">
                    <h2 id="list-title">All Stations (<?php echo $total_stations; ?>)</h2>
                    <div class="station-grid">
                        <?php foreach ($stations as $name => $ip): 
                            $status = $last_statuses[$name] ?? 'UNKNOWN';
                            $status_class = ($status === 'ONLINE') ? 'up' : 'down';
                            $status_data = ($status === 'ONLINE') ? 'online' : 'offline';
                        ?>
                        <div class="station-box <?php echo $status_class; ?>" data-status="<?php echo $status_data; ?>"><?php echo htmlspecialchars($name); ?></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div id="scanner-view" class="view-container <?php if ($active_view === 'scanner') echo 'active'; ?>">
                <div class="content-container">
                    <div class="scanner-header">
                        <h2>Network Scan Results <small style="color:var(--text-light-color); font-weight:normal;">(Range: 172.19.110.130-254)</small></h2>
                        <div>
                            <span id="scan-status-msg"></span>
                            <button id="run-scan-btn" class="refresh-button">Run Scan Now</button>
                        </div>
                    </div>
                    <?php if ($scanner_results): ?>
                        <p style="text-align:center; color: var(--text-light-color);">Last Scan Completed: <?php echo htmlspecialchars($scanner_results['last_scan_end']); ?> (IST)</p>
                        <h3><span style="color: var(--green-color)">●</span> Used IPs (<?php echo count($scanner_results['used_ips']); ?>)</h3><div class="scanner-grid">
                            <?php foreach($scanner_results['used_ips'] as $item): ?>
                            <div class="ip-card">
                                <div class="ip-card-header">
                                    <div class="status-dot"></div>
                                    <div class="ip-address"><?php echo get_display_ip($item['ip'], $special_ip_names); ?></div>
                                </div>
                                <div class="ip-card-body">
                                    <div class="os">
                                        <span class="os-icon"><?php $os_icon = '🖥️'; if (stripos($item['os'], 'Windows') !== false) $os_icon = '🪟'; if (stripos($item['os'], 'Linux') !== false) $os_icon = '🐧'; echo $os_icon; ?></span>
                                        <strong>Guessed OS:</strong> <?php echo htmlspecialchars($item['os']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <h3 style="margin-top: 30px;"><span style="color: var(--red-color)">●</span> Unused IPs (<?php echo count($scanner_results['unused_ips']); ?>)</h3><div class="unused-grid">
                            <?php if (empty($scanner_results['unused_ips'])) { echo '<span>None</span>'; } else { foreach($scanner_results['unused_ips'] as $ip): ?>
                            <div class="unused-ip"><?php echo get_display_ip($ip, $special_ip_names); ?></div>
                            <?php endforeach; } ?>
                        </div>
                    <?php else: ?>
                        <p>The first network scan has not completed yet. Run the scan manually or wait for the next scheduled run.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="history-view" class="view-container <?php if ($active_view === 'history') echo 'active'; ?>">
                <div class="content-container">
                    <h2>Downtime Summary</h2>
                    <div class="history-controls">
                        <label for="history-station-filter">Filter by Station:</label>
                        <select id="history-station-filter">
                            <option value="">All Stations</option>
                            <?php foreach ($station_names_sorted as $name): ?>
                                <option value="<?php echo strtolower(htmlspecialchars($name)); ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="history-date-picker">Filter by Date:</label>
                        <input type="date" id="history-date-picker">
                    </div>
                    <table class="history-table">
                        <thead><tr><th>Station</th><th>Offline From (IST)</th><th>Online Again (IST)</th><th>Total Duration</th></tr></thead>
                        <tbody>
                        <?php if (empty($downtime_summary)): ?>
                            <tr><td colspan="4">No completed downtimes have been recorded yet.</td></tr>
                        <?php else: foreach ($downtime_summary as $downtime): ?>
                            <tr data-start-date="<?php echo htmlspecialchars($downtime['start_date_iso']); ?>" data-station-name="<?php echo strtolower(htmlspecialchars($downtime['station'])); ?>">
                                <td><?php echo htmlspecialchars($downtime['station']); ?></td>
                                <td><?php echo $downtime['start']; ?></td>
                                <td><?php echo $downtime['end']; ?></td>
                                <td><?php echo $downtime['duration']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr id="no-history-for-date" style="display: none;">
                            <td colspan="4" style="text-align: center; color: var(--text-light-color);">No results match your filters.</td>
                        </tr>
                    </tbody></table>
                </div>
            </div>
            
            <div id="manage-view" class="view-container <?php if ($active_view === 'manage') echo 'active'; ?>">
                <div id="manage-password-prompt" class="password-prompt">
                    <h3>Admin Access Required</h3>
                    <p>Please enter the password to manage IP names.</p>
                    <div class="form-group">
                        <label for="manage-password">Password</label>
                        <input type="password" id="manage-password" placeholder="Enter password">
                    </div>
                    <br>
                    <button id="submit-password-btn" class="refresh-button">Login</button>
                </div>
                <div id="manage-content" style="display: none;">
                    <div class="content-container">
                        <h2>Manage Special IP Names</h2>
                        <div class="manage-container">
                            <div class="manage-form">
                                <h3>Add / Edit IP Name</h3>
                                <form action="manage_ips.php" method="POST">
                                    <input type="hidden" name="action" value="add">
                                    <div class="form-group"><label for="ip_address">IP Address</label><input type="text" id="ip_address" name="ip" placeholder="e.g., 172.19.110.100" required></div>
                                    <div class="form-group"><label for="ip_name">Display Name</label><input type="text" id="ip_name" name="name" placeholder="e.g., NEW PC" required></div>
                                    <div class="form-group">
                                        <label for="ip_color">Display Color</label>
                                        <select id="ip_color" name="color" required>
                                            <option value="var(--green-color)">Green</option><option value="var(--blue-color)">Blue</option>
                                            <option value="var(--orange-color)">Orange</option><option value="var(--purple-color)">Purple</option>
                                            <option value="var(--red-color)">Red</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="refresh-button">Save Name</button>
                                </form>
                            </div>
                            <div class="current-names">
                                <h3>Currently Assigned Names</h3>
                                <div id="current-names-collapsible" style="display:none;">
                                    <table class="history-table">
                                        <thead><tr><th>IP Address</th><th>Assigned Name</th><th>Action</th></tr></thead>
                                        <tbody>
                                            <?php if (empty($special_ip_names)): ?>
                                                <tr><td colspan="3">No special IP names have been assigned yet.</td></tr>
                                            <?php else: foreach ($special_ip_names as $ip => $data): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ip); ?></td>
                                                    <td><span style="font-weight:bold; color: <?php echo htmlspecialchars($data['color']); ?>;"><?php echo htmlspecialchars($data['name']); ?></span></td>
                                                    <td>
                                                        <form action="manage_ips.php" method="POST" style="margin:0;"><input type="hidden" name="action" value="delete"><input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip); ?>"><button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete the name for <?php echo htmlspecialchars($ip); ?>?');">Delete</button></form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button id="toggle-names-btn" class="toggle-button">Show Current Names</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="logreport-view" class="view-container <?php if ($active_view === 'logreport') echo 'active'; ?>">
                <div class="content-container">
                    <h2>Log File Report</h2>
                    <?php if (empty($log_report_data)): ?>
                        <p><strong>Could not find or parse `log.txt`.</strong></p>
                        <p>Please make sure the `log.txt` file is located in the same directory on the server as your `index.php` file.</p>
                    <?php else: ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Station Name</th>
                                    <th>Last Backup Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($log_report_data as $station => $timestamp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($station); ?></td>
                                        <td><?php echo htmlspecialchars($timestamp); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // REFRESH TIMER LOGIC
            let refreshTimerInterval; 
            const refreshTimerDisplay = document.getElementById('refresh-timer');
            const countdownElement = document.createElement('strong');
            countdownElement.id = 'countdown';
            if (refreshTimerDisplay) {
                refreshTimerDisplay.innerHTML = 'Auto-refresh in ';
                refreshTimerDisplay.appendChild(countdownElement);
                refreshTimerDisplay.append('s...');
            }
            function stopRefreshTimer() { clearInterval(refreshTimerInterval); if(refreshTimerDisplay) { refreshTimerDisplay.style.display = 'none'; } }
            function startRefreshTimer() {
                if(refreshTimerDisplay) {
                    refreshTimerDisplay.style.display = 'inline-block';
                    let seconds = 30; countdownElement.textContent = seconds;
                    clearInterval(refreshTimerInterval);
                    refreshTimerInterval = setInterval(() => {
                        seconds--; countdownElement.textContent = seconds;
                        if (seconds <= 0) { clearInterval(refreshTimerInterval); window.location.reload(); }
                    }, 1000);
                }
            }

            // THEME LOGIC
            const themeCycleBtn = document.getElementById('theme-cycle-btn');
            const themes = ['light', 'dark', 'rgb'];
            const themeIcons = { light: '☀️', dark: '🌙', rgb: '<span class="rgb-icon">RGB</span>' };
            function applyTheme(theme) {
                document.body.classList.remove('dark-mode', 'rgb-mode');
                if (theme === 'dark') { document.body.classList.add('dark-mode'); } 
                else if (theme === 'rgb') { document.body.classList.add('dark-mode'); document.body.classList.add('rgb-mode'); }
                if (themeCycleBtn) { themeCycleBtn.innerHTML = themeIcons[theme]; }
                localStorage.setItem('theme_mode', theme);
            }
            let currentTheme = localStorage.getItem('theme_mode') || 'light';
            applyTheme(currentTheme);
            if (themeCycleBtn) {
                themeCycleBtn.addEventListener('click', () => {
                    let currentTheme = localStorage.getItem('theme_mode') || 'light';
                    let currentIndex = themes.indexOf(currentTheme);
                    let nextIndex = (currentIndex + 1) % themes.length;
                    applyTheme(themes[nextIndex]);
                });
            }

            // MANAGE TAB PASSWORD LOGIC
            const MANAGE_PASSWORD = 'admin'; 
            const passwordPrompt = document.getElementById('manage-password-prompt');
            const manageContent = document.getElementById('manage-content');
            const passwordInput = document.getElementById('manage-password');
            const passwordSubmitBtn = document.getElementById('submit-password-btn');
            function showManageContent() {
                if (passwordPrompt) passwordPrompt.style.display = 'none';
                if (manageContent) manageContent.style.display = 'block';
            }
            function showPasswordPrompt() {
                if (passwordPrompt) passwordPrompt.style.display = 'block';
                if (manageContent) manageContent.style.display = 'none';
                if (passwordInput) passwordInput.value = '';
            }
            if (passwordSubmitBtn) {
                passwordSubmitBtn.addEventListener('click', function() {
                    if (passwordInput.value === MANAGE_PASSWORD) {
                        sessionStorage.setItem('manageAccessGranted', 'true');
                        showManageContent();
                    } else {
                        alert('Incorrect Password!');
                        passwordInput.value = '';
                    }
                });
                passwordInput.addEventListener('keyup', function(event) { if (event.key === 'Enter') { passwordSubmitBtn.click(); } });
            }

            // TAB NAVIGATION LOGIC
            const tabButtons = document.querySelectorAll('.tab-button');
            const viewContainers = document.querySelectorAll('.view-container');
            tabButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const viewId = this.dataset.view;
                    history.pushState(null, '', 'index.php?view=' + viewId.replace('-view', ''));
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    viewContainers.forEach(container => { container.classList.toggle('active', container.id === viewId); });
                    if (viewId !== 'manage-view') {
                        sessionStorage.removeItem('manageAccessGranted');
                    } else {
                        if (sessionStorage.getItem('manageAccessGranted') === 'true') {
                            showManageContent();
                        } else {
                            showPasswordPrompt();
                        }
                    }
                    if (viewId === 'dashboard-view') {
                        const activeFilter = document.querySelector('.stat-card.active')?.dataset.filter;
                        if (activeFilter === 'all') { startRefreshTimer(); } else { stopRefreshTimer(); }
                    } else { stopRefreshTimer(); }
                });
            });

            // DASHBOARD FILTER LOGIC
            const filterCards = document.querySelectorAll('.stat-card[data-filter]');
            const stationBoxes = document.querySelectorAll('.station-box[data-status]');
            const listTitle = document.getElementById('list-title');
            if (filterCards.length > 0) {
                const listContainer = document.getElementById('status-list');
                const counts = { all: listContainer.dataset.totalCount, online: <?php echo $up_count; ?>, offline: <?php echo $down_count; ?> };
                const titles = { all: 'All Stations', online: 'Online Stations', offline: 'Offline Stations' };
                filterCards.forEach(card => {
                    card.addEventListener('click', function() {
                        const filterValue = this.getAttribute('data-filter');
                        filterCards.forEach(c => c.classList.remove('active')); this.classList.add('active');
                        if(listTitle) listTitle.textContent = `${titles[filterValue]} (${counts[filterValue]})`;
                        stationBoxes.forEach(item => { item.classList.toggle('hidden', !(filterValue === 'all' || item.dataset.status === filterValue)); });
                        if (filterValue === 'all') { startRefreshTimer(); } else { stopRefreshTimer(); }
                    });
                });
            }

            // --- HISTORY FILTER LOGIC (MODIFIED FOR NEW DEFAULT VIEW) ---
            const datePicker = document.getElementById('history-date-picker');
            const stationFilterSelect = document.getElementById('history-station-filter'); 
            const historyRows = document.querySelectorAll('.history-table tbody tr[data-start-date]');
            const noHistoryRow = document.getElementById('no-history-for-date');
            
            function applyHistoryFilters() {
                if (!datePicker || !stationFilterSelect) return;

                const today = new Date();
                const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                
                const selectedDate = datePicker.value;
                const selectedStation = stationFilterSelect.value;
                let visibleRowCount = 0;

                // Check if we are in the default view (today's date, all stations)
                const isDefaultView = (selectedDate === todayISO && selectedStation === '');

                historyRows.forEach(row => {
                    const rowDate = row.dataset.startDate;
                    const rowStation = row.dataset.stationName;
                    const isRowOffline = row.innerHTML.includes('Still Offline');
                    
                    let showRow = false;

                    if (isDefaultView) {
                        // Special logic for default view: show "Still Offline" OR anything from today
                        if (isRowOffline || rowDate === todayISO) {
                            showRow = true;
                        }
                    } else {
                        // Standard filtering logic for any other selection
                        const dateMatch = (selectedDate === '' || rowDate === selectedDate);
                        const stationMatch = (selectedStation === '' || rowStation === selectedStation);
                        if (dateMatch && stationMatch) {
                            showRow = true;
                        }
                    }

                    if (showRow) {
                        row.style.display = '';
                        visibleRowCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                if (noHistoryRow) { noHistoryRow.style.display = (visibleRowCount === 0 && historyRows.length > 0) ? '' : 'none'; }
            }

            if (datePicker && stationFilterSelect) {
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                datePicker.value = `${year}-${month}-${day}`;
                stationFilterSelect.value = '';
                applyHistoryFilters();

                datePicker.addEventListener('change', applyHistoryFilters);
                
                stationFilterSelect.addEventListener('change', function() {
                    if (this.value !== '') { datePicker.value = ''; }
                    applyHistoryFilters();
                });
            }

            // SCANNER BUTTON LOGIC
            const runScanBtn = document.getElementById('run-scan-btn');
            const scanStatusMsg = document.getElementById('scan-status-msg');
            if (runScanBtn) {
                runScanBtn.addEventListener('click', function() {
                    this.disabled = true;
                    scanStatusMsg.textContent = '➡️ Scan started...';
                    fetch('check_scan_status.php').then(res => res.json()).then(initialData => {
                        const initialTimestamp = initialData.last_scan_timestamp;
                        fetch('trigger_scan.php');
                        let dots = 1;
                        const poller = setInterval(() => {
                            scanStatusMsg.textContent = 'Scanning in progress' + '.'.repeat(dots);
                            dots = (dots >= 3) ? 1 : dots + 1;
                            fetch('check_scan_status.php').then(res => res.json()).then(newData => {
                                if (newData.last_scan_timestamp > initialTimestamp) {
                                    clearInterval(poller);
                                    scanStatusMsg.textContent = '✅ Scan complete! Reloading...';
                                    setTimeout(() => { window.location.href = 'index.php?view=scanner'; }, 1500);
                                }
                            });
                        }, 5000);
                    });
                });
            }
            
            // MANAGE TAB COLLAPSIBLE LIST LOGIC
            const toggleNamesBtn = document.getElementById('toggle-names-btn');
            const namesCollapsible = document.getElementById('current-names-collapsible');
            if (toggleNamesBtn) {
                toggleNamesBtn.addEventListener('click', function() {
                    const isHidden = namesCollapsible.style.display === 'none';
                    namesCollapsible.style.display = isHidden ? 'block' : 'none';
                    this.textContent = isHidden ? 'Hide Current Names' : 'Show Current Names';
                });
            }
            
            // INITIAL PAGE LOAD CHECKS
            if (document.querySelector('.tab-button.active')?.dataset.view === 'manage-view') {
                 if (sessionStorage.getItem('manageAccessGranted') === 'true') {
                    showManageContent();
                } else {
                    showPasswordPrompt();
                }
            }
            if ("<?php echo $active_view; ?>" === 'dashboard') { startRefreshTimer(); } 
            else { stopRefreshTimer(); }
        });
    </script>
    <script>
      document.addEventListener("contextmenu", function(e){ e.preventDefault(); });
      document.onkeydown = function(e) {
        if (e.ctrlKey && (e.key === "u" || e.key === "U")) { e.preventDefault(); }
        if (e.key === "F12") { e.preventDefault(); }
        if (e.ctrlKey && e.shiftKey && (e.key === "I" || "i" === e.key)) { e.preventDefault(); }
      };
    </script>
</body>
</html>
