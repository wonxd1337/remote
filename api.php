<?php
$db_file = 'db.json';
$last_sync_file = 'last_sync.txt';

// Inisialisasi database jika belum ada
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode([
        "installed" => [],
        "status" => [],
        "commands" => [],  // [pkg => ['cmd'=>'IDLE','mode'=>'public','target'=>'','content'=>'','executed'=>false]]
        "file_result" => null,
        "auto_rejoin" => false
    ]));
}

// AUTO CLEANUP: Hapus data jika lebih dari 1 menit tidak sync
if (file_exists($last_sync_file)) {
    $last_sync = (int)file_get_contents($last_sync_file);
    $time_diff = time() - $last_sync;
    if ($time_diff > 60) {
        $db = json_decode(file_get_contents($db_file), true);
        $db['installed'] = [];
        $db['status'] = [];
        $db['commands'] = [];
        file_put_contents($db_file, json_encode($db));
    }
}

$db = json_decode(file_get_contents($db_file), true);
$action = $_GET['action'] ?? '';
header('Content-Type: application/json');

// ==================== SINKRONISASI STATUS DARI TERMUX ====================
if ($action === 'sync') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $db['installed'] = $input['installed'] ?? [];
        $db['status'] = $input['accounts'] ?? [];
        file_put_contents($db_file, json_encode($db));
        file_put_contents($last_sync_file, time());
        echo json_encode(['status' => 'ok', 'message' => 'Status updated', 'timestamp' => time()]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    }
    exit;
}

// ==================== AMBIL PERINTAH YANG BELUM DIEKSEKUSI ====================
if ($action === 'get_commands') {
    $pending = [];
    foreach ($db['commands'] as $pkg => $cmd) {
        if ((!isset($cmd['executed']) || !$cmd['executed']) && $cmd['cmd'] !== 'IDLE') {
            $pending[$pkg] = $cmd;
        }
    }
    echo json_encode($pending);
    exit;
}

// ==================== KONFIRMASI EKSEKUSI (HANYA RESET CMD KE IDLE) ====================
if ($action === 'ack_execution') {
    $pkg = $_GET['pkg'] ?? '';
    if ($pkg && isset($db['commands'][$pkg])) {
        // Pertahankan mode, target, content – hanya reset cmd dan executed
        $db['commands'][$pkg]['cmd'] = 'IDLE';
        $db['commands'][$pkg]['executed'] = false;
        file_put_contents($db_file, json_encode($db));
        echo json_encode(['status' => 'ok', 'message' => "Command for $pkg reset to IDLE"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Package not found']);
    }
    exit;
}

// ==================== TAMBAHKAN KE DASHBOARD ====================
if ($action === 'add_dashboard') {
    $pkg = $_POST['pkg'] ?? '';
    if ($pkg) {
        if (!isset($db['commands'][$pkg])) {
            $db['commands'][$pkg] = [
                'cmd' => 'IDLE',
                'mode' => 'public',
                'target' => '',
                'content' => '',
                'executed' => false
            ];
            file_put_contents($db_file, json_encode($db));
            echo json_encode(['status' => 'ok', 'message' => "Package $pkg added"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Package already exists']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No package specified']);
    }
    exit;
}

// ==================== HAPUS DARI DASHBOARD ====================
if ($action === 'remove_dashboard') {
    $pkg = $_GET['pkg'] ?? '';
    if ($pkg && isset($db['commands'][$pkg])) {
        unset($db['commands'][$pkg]);
        file_put_contents($db_file, json_encode($db));
        echo json_encode(['status' => 'ok', 'message' => "Package $pkg removed"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Package not found']);
    }
    exit;
}

// ==================== SET PERINTAH (START/STOP/RERUN/IDLE & FILE OPS) ====================
if ($action === 'set_cmd') {
    $pkg = $_POST['pkg'] ?? '';
    $cmd = $_POST['cmd'] ?? 'IDLE';
    $mode = $_POST['mode'] ?? 'public';
    $target = $_POST['target'] ?? '';
    $content = $_POST['content'] ?? '';

    if ($pkg) {
        if (($cmd === 'START' || $cmd === 'RERUN') && empty($target)) {
            echo json_encode(['status' => 'error', 'message' => 'Target required for START/RERUN']);
            exit;
        }
        $db['commands'][$pkg] = [
            'cmd' => $cmd,
            'mode' => $mode,
            'target' => $target,
            'content' => $content,
            'executed' => false
        ];
        file_put_contents($db_file, json_encode($db));
        echo json_encode(['status' => 'ok', 'message' => "Command $cmd set for $pkg"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No package specified']);
    }
    exit;
}

// ==================== APPLY TO ALL (GLOBAL SETTING) ====================
if ($action === 'set_all_cmd') {
    $mode = $_POST['mode'] ?? 'public';
    $target = $_POST['target'] ?? '';
    if (empty($target)) {
        echo json_encode(['status' => 'error', 'message' => 'Target required']);
        exit;
    }
    if (empty($db['commands'])) {
        echo json_encode(['status' => 'error', 'message' => 'No packages in dashboard']);
        exit;
    }
    foreach ($db['commands'] as $pkg => &$cmd) {
        $cmd['mode'] = $mode;
        $cmd['target'] = $target;
        $cmd['executed'] = false;
        // cmd tidak diubah, biarkan tetap IDLE atau sesuai sebelumnya
    }
    file_put_contents($db_file, json_encode($db));
    echo json_encode(['status' => 'ok', 'message' => 'Applied to all packages']);
    exit;
}

// ==================== SET AUTO REJOIN ====================
if ($action === 'set_auto_rejoin') {
    $enabled = $_POST['enabled'] ?? 'false';
    $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    $db['auto_rejoin'] = $enabled;
    file_put_contents($db_file, json_encode($db));
    echo json_encode(['status' => 'ok', 'message' => 'Auto rejoin ' . ($enabled ? 'ON' : 'OFF'), 'enabled' => $enabled]);
    exit;
}

// ==================== GET DASHBOARD DATA (UNTUK POLLING) ====================
if ($action === 'get_dashboard_data') {
    $last_sync = file_exists($last_sync_file) ? (int)file_get_contents($last_sync_file) : 0;
    echo json_encode([
        'status' => 'ok',
        'installed' => $db['installed'] ?? [],
        'status_data' => $db['status'] ?? [],
        'commands' => $db['commands'] ?? [],
        'auto_rejoin' => $db['auto_rejoin'] ?? false,
        'last_sync' => $last_sync,
        'is_connected' => $last_sync > 0 && (time() - $last_sync) <= 60,
        'time_diff' => $last_sync > 0 ? time() - $last_sync : null
    ]);
    exit;
}

// ==================== SCAN ULANG (BERSIHKAN DATA) ====================
if ($action === 'clear_installed') {
    $db['installed'] = [];
    $db['status'] = [];
    $db['commands'] = [];
    $db['auto_rejoin'] = false;
    file_put_contents($db_file, json_encode($db));
    if (file_exists($last_sync_file)) unlink($last_sync_file);
    echo json_encode(['status' => 'ok', 'message' => 'All data cleared']);
    exit;
}

// ==================== GET STATUS (UNTUK AJAX REFRESH) ====================
if ($action === 'get_status') {
    $pkg = $_GET['pkg'] ?? '';
    if ($pkg && isset($db['status'][$pkg])) {
        echo json_encode(['status' => 'ok', 'data' => $db['status'][$pkg]]);
    } elseif (empty($pkg)) {
        echo json_encode(['status' => 'ok', 'data' => $db['status']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Package not found']);
    }
    exit;
}

// ==================== GET LAST SYNC TIME ====================
if ($action === 'get_last_sync') {
    if (file_exists($last_sync_file)) {
        $last_sync = (int)file_get_contents($last_sync_file);
        $time_diff = time() - $last_sync;
        echo json_encode([
            'status' => 'ok',
            'last_sync' => $last_sync,
            'time_diff' => $time_diff,
            'is_connected' => $time_diff <= 60
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No sync record found',
            'is_connected' => false
        ]);
    }
    exit;
}

// ==================== FILE RESULT FROM BOT ====================
if ($action === 'file_result') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $db['file_result'] = [
            'timestamp' => time(),
            'operation' => $input['operation'] ?? '',
            'data' => $input['data'] ?? [],
            'message' => $input['message'] ?? '',
            'success' => $input['success'] ?? false
        ];
        file_put_contents($db_file, json_encode($db));
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    }
    exit;
}

// ==================== GET FILE RESULT ====================
if ($action === 'get_file_result') {
    $result = $db['file_result'] ?? null;
    $clear = $_GET['clear'] ?? false;
    if ($result) {
        if ($clear) {
            unset($db['file_result']);
            file_put_contents($db_file, json_encode($db));
        }
        echo json_encode(['status' => 'ok', 'result' => $result]);
    } else {
        echo json_encode(['status' => 'ok', 'result' => null]);
    }
    exit;
}

// ==================== GET ROBLOX AVATAR ====================
if ($action === 'get_avatar') {
    $username = $_GET['username'] ?? '';
    if (empty($username) || $username === 'Unknown') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username']);
        exit;
    }

    // 1. Dapatkan UserID dari Username
    $url = 'https://users.roblox.com/v1/usernames/users';
    $data = json_encode(['usernames' => [$username], 'excludeBannedUsers' => false]);
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $data,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $json = json_decode($response, true);
        if (!empty($json['data'][0]['id'])) {
            $userId = $json['data'][0]['id'];
            
            // 2. Dapatkan URL Avatar (Headshot) berdasarkan UserID
            $thumbUrl = "https://thumbnails.roblox.com/v1/users/avatar-headshot?userIds=$userId&size=150x150&format=Png&isCircular=false";
            $thumbRes = @file_get_contents($thumbUrl);
            
            if ($thumbRes) {
                $thumbJson = json_decode($thumbRes, true);
                if (!empty($thumbJson['data'][0]['imageUrl'])) {
                    echo json_encode(['status' => 'ok', 'url' => $thumbJson['data'][0]['imageUrl']]);
                    exit;
                }
            }
        }
    }
    echo json_encode(['status' => 'error']);
    exit;
}


// ==================== DEFAULT RESPONSE ====================
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action',
    'available_actions' => [
        'sync', 'get_commands', 'ack_execution', 'add_dashboard',
        'remove_dashboard', 'set_cmd', 'set_all_cmd', 'clear_installed',
        'get_status', 'get_last_sync', 'file_result', 'get_file_result',
        'set_auto_rejoin', 'get_dashboard_data', 'get_avatar'
    ]
]);
exit;
?>