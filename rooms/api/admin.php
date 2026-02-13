<?php
/* ============================================================================
 * api/admin.php — Admin operations
 * ============================================================================ */
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

function requireAdminAuth(): void {
    startSession();
    if (empty($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Admin auth required']);
        exit;
    }
}

/* ========== ADMIN LOGIN ========== */
if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    $hashFile = DATA_DIR . '/admin/.password';

    if (!file_exists($hashFile)) {
        // First time setup: create default password
        $defaultHash = hashPw('admin123');
        if (!is_dir(dirname($hashFile))) mkdir(dirname($hashFile), 0755, true);
        file_put_contents($hashFile, $defaultHash);
    }

    if (!checkRateLimit('admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 15)) jErr('Too many attempts', 429);

    $storedHash = trim(file_get_contents($hashFile));
    if (!verifyPw($password, $storedHash)) {
        logAction('SECURITY', 'admin_login_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        jErr('Invalid password');
    }

    startSession();
    $_SESSION['admin'] = true;
    $_SESSION['admin_login_time'] = time();
    logAction('ADMIN', 'login', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    jRes(['ok' => true]);
}

/* ========== ADMIN CHECK ========== */
if ($action === 'check') {
    startSession();
    jRes(['authenticated' => !empty($_SESSION['admin'])]);
}

/* ========== ADMIN LOGOUT ========== */
if ($action === 'logout') {
    startSession();
    unset($_SESSION['admin']);
    jRes(['ok' => true]);
}

/* ========== GET STATS ========== */
if ($action === 'stats') {
    requireAdminAuth();
    $userIdx = readJson(DATA_DIR . '/users/_index.json');
    $roomIdx = readJson(DATA_DIR . '/rooms/_index.json');

    $totalUsers = count($userIdx);
    $totalRooms = count($roomIdx);
    $totalMessages = 0;
    $totalFiles = 0;

    // Count messages from room meta
    foreach ($roomIdx as $rid => $info) {
        $meta = readJson(DATA_DIR . '/messages/' . $rid . '_meta.json');
        $totalMessages += $meta['totalMessages'] ?? 0;
    }

    // Count online users (within last 5 min)
    $onlineCount = 0;
    $now = time();
    foreach ($userIdx as $uname => $uid) {
        $u = readJson(DATA_DIR . '/users/' . $uid . '.json');
        if ($u && ($now - ($u['last_seen'] ?? 0)) < 300) $onlineCount++;
    }

    // Storage usage
    $storageBytes = 0;
    $uploadDir = UPLOADS_DIR;
    if (is_dir($uploadDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
        foreach ($it as $file) { if ($file->isFile()) $storageBytes += $file->getSize(); }
    }

    jRes([
        'users' => $totalUsers,
        'rooms' => $totalRooms,
        'messages' => $totalMessages,
        'files' => $totalFiles,
        'online' => $onlineCount,
        'storage' => $storageBytes,
        'storageFormatted' => round($storageBytes / 1048576, 1) . ' MB'
    ]);
}

/* ========== LIST USERS ========== */
if ($action === 'users') {
    requireAdminAuth();
    $idx = readJson(DATA_DIR . '/users/_index.json');
    $users = [];
    foreach ($idx as $uname => $uid) {
        $u = readJson(DATA_DIR . '/users/' . $uid . '.json');
        if ($u) {
            $users[] = [
                'id' => $uid, 'username' => $uname, 'displayName' => $u['displayName'],
                'avatar' => $u['profile']['avatar'] ?? '', 'status' => $u['profile']['status'] ?? 'offline',
                'lastSeen' => $u['last_seen'] ?? 0, 'created' => $u['created_at'] ?? 0,
                'isBanned' => $u['isBanned'] ?? false, 'isAdmin' => $u['isAdmin'] ?? false,
                'messagesSent' => $u['stats']['messagesSent'] ?? 0
            ];
        }
    }
    usort($users, fn($a, $b) => ($b['lastSeen'] ?? 0) - ($a['lastSeen'] ?? 0));
    jRes(['users' => $users]);
}

/* ========== BAN/UNBAN USER ========== */
if ($action === 'banUser') {
    requireAdminAuth();
    $userId = $_POST['userId'] ?? '';
    $ban = !empty($_POST['ban']);
    $reason = $_POST['reason'] ?? '';

    $u = findById($userId);
    if (!$u) jErr('User not found');

    $u['isBanned'] = $ban;
    $u['banReason'] = $ban ? sanitize($reason) : '';
    writeJson(DATA_DIR . '/users/' . $userId . '.json', $u);

    logAction('ADMIN', $ban ? 'ban_user' : 'unban_user', ['userId' => $userId, 'username' => $u['username'], 'reason' => $reason]);
    jRes(['ok' => true]);
}

/* ========== DELETE USER ========== */
if ($action === 'deleteUser') {
    requireAdminAuth();
    $userId = $_POST['userId'] ?? '';
    $u = findById($userId);
    if (!$u) jErr('User not found');

    // Remove from index
    $idx = readJson(DATA_DIR . '/users/_index.json');
    unset($idx[$u['username']]);
    writeJson(DATA_DIR . '/users/_index.json', $idx);

    // Remove user file
    @unlink(DATA_DIR . '/users/' . $userId . '.json');

    logAction('ADMIN', 'delete_user', ['userId' => $userId, 'username' => $u['username']]);
    jRes(['ok' => true]);
}

/* ========== LIST ROOMS (ADMIN) ========== */
if ($action === 'rooms') {
    requireAdminAuth();
    $idx = readJson(DATA_DIR . '/rooms/_index.json');
    $rooms = [];
    foreach ($idx as $rid => $info) {
        $room = readJson(DATA_DIR . '/rooms/' . $rid . '.json');
        if ($room) {
            $owner = findById($room['owner']);
            $rooms[] = [
                'id' => $rid, 'name' => $room['name'], 'type' => $room['type'],
                'avatar' => $room['avatar'], 'members' => count($room['members']),
                'owner' => $owner ? $owner['username'] : 'unknown',
                'created' => $room['created_at'], 'status' => $room['status'] ?? 'active'
            ];
        }
    }
    jRes(['rooms' => $rooms]);
}

/* ========== DELETE ROOM ========== */
if ($action === 'deleteRoom') {
    requireAdminAuth();
    $roomId = $_POST['roomId'] ?? '';
    $room = readJson(DATA_DIR . '/rooms/' . $roomId . '.json');
    if (empty($room)) jErr('Room not found');

    @unlink(DATA_DIR . '/rooms/' . $roomId . '.json');
    @unlink(DATA_DIR . '/messages/' . $roomId . '.json');
    @unlink(DATA_DIR . '/messages/' . $roomId . '_meta.json');
    @unlink(DATA_DIR . '/messages/' . $roomId . '_presence.json');
    @unlink(DATA_DIR . '/messages/' . $roomId . '_typing.json');

    $idx = readJson(DATA_DIR . '/rooms/_index.json');
    unset($idx[$roomId]);
    writeJson(DATA_DIR . '/rooms/_index.json', $idx);

    logAction('ADMIN', 'delete_room', ['roomId' => $roomId, 'name' => $room['name']]);
    jRes(['ok' => true]);
}

/* ========== GET/UPDATE SETTINGS ========== */
if ($action === 'getSettings') {
    requireAdminAuth();
    jRes(['settings' => getSettings()]);
}

if ($action === 'updateSettings') {
    requireAdminAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $current = getSettings();
    $allowed = array_keys($current);
    foreach ($input as $k => $v) {
        if (in_array($k, $allowed)) $current[$k] = $v;
    }
    writeJson(DATA_DIR . '/admin/settings.json', $current);
    logAction('ADMIN', 'settings_updated', ['keys' => array_keys($input)]);
    jRes(['ok' => true, 'settings' => $current]);
}

/* ========== GET LOGS ========== */
if ($action === 'logs') {
    requireAdminAuth();
    $date = $_GET['date'] ?? date('Y-m-d');
    $logFile = DATA_DIR . '/admin/logs/' . preg_replace('/[^0-9\-]/', '', $date) . '.log';
    $entries = [];
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $e = json_decode($line, true);
            if ($e) $entries[] = $e;
        }
    }
    jRes(['logs' => array_reverse($entries), 'date' => $date]);
}

/* ========== CHANGE ADMIN PASSWORD ========== */
if ($action === 'changePassword') {
    requireAdminAuth();
    $current = $_POST['currentPassword'] ?? '';
    $newPass = $_POST['newPassword'] ?? '';

    $hashFile = DATA_DIR . '/admin/.password';
    $storedHash = trim(file_get_contents($hashFile));
    if (!verifyPw($current, $storedHash)) jErr('Current password is incorrect');
    if (strlen($newPass) < 8) jErr('New password must be at least 8 characters');

    file_put_contents($hashFile, hashPw($newPass));
    logAction('ADMIN', 'password_changed', []);
    jRes(['ok' => true]);
}

jErr('Unknown action');
