<?php
/* ============================================================================
 * api/utils.php — Core utilities, helpers, security
 * ============================================================================ */

define('DATA_DIR', __DIR__ . '/../data');
define('UPLOADS_DIR', __DIR__ . '/../uploads');
date_default_timezone_set('Asia/Tehran');

function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function readJson(string $f): array {
    if (!file_exists($f)) return [];
    $raw = file_get_contents($f);
    if ($raw === '' || $raw === false) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function writeJson(string $f, $d): void {
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $h = fopen($f, 'c+');
    if ($h && flock($h, LOCK_EX)) {
        ftruncate($h, 0);
        fwrite($h, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($h, LOCK_UN);
    }
    if ($h) fclose($h);
}

function checkRateLimit(string $key, int $max = 30, int $decay = 1): bool {
    $dir = DATA_DIR . '/rate_limits';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . '/' . md5($key) . '.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($data['e'] ?? 0) > time()) {
            if (($data['a'] ?? 0) >= $max) return false;
            $data['a']++;
        } else {
            $data = ['a' => 1, 'e' => time() + ($decay * 60)];
        }
    } else {
        $data = ['a' => 1, 'e' => time() + ($decay * 60)];
    }
    file_put_contents($file, json_encode($data));
    return true;
}

function sanitize(string $s): string { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function sanitizeUsername(string $u): string { return strtolower(preg_replace('/[^a-z0-9_]/', '', strtolower(trim($u)))); }
function hashPw(string $p): string { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
function verifyPw(string $p, string $h): bool { return password_verify($p, $h); }

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => 604800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function requireAuth(): array {
    startSession();
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'Auth required']); exit; }
    $user = readJson(DATA_DIR . '/users/' . $_SESSION['user_id'] . '.json');
    if (empty($user)) { session_destroy(); http_response_code(401); echo json_encode(['error' => 'User not found']); exit; }
    if (!empty($user['isBanned'])) { session_destroy(); http_response_code(403); echo json_encode(['error' => 'Account suspended']); exit; }
    return $user;
}

function getUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    $u = readJson(DATA_DIR . '/users/' . $_SESSION['user_id'] . '.json');
    return $u ?: null;
}

function findByUsername(string $username): ?array {
    $idx = readJson(DATA_DIR . '/users/_index.json');
    $username = strtolower($username);
    return isset($idx[$username]) ? (readJson(DATA_DIR . '/users/' . $idx[$username] . '.json') ?: null) : null;
}

function findById(string $id): ?array {
    $f = DATA_DIR . '/users/' . $id . '.json';
    return file_exists($f) ? (readJson($f) ?: null) : null;
}

function getSettings(): array {
    $defaults = [
        'roomCreation' => 'free', 'requireApproval' => false, 'p2pEnabled' => true,
        'registration' => 'open', 'maxMembersPerRoom' => 500, 'maxFileSize' => 52428800,
        'allowImages' => true, 'allowVideos' => true, 'allowDocuments' => true,
        'allowVoice' => true, 'rateLimitMessages' => 30, 'maxLoginAttempts' => 5,
        'lockoutDuration' => 15, 'minPasswordLength' => 8, 'appName' => 'ROOMs',
        'maintenanceMode' => false
    ];
    return array_merge($defaults, readJson(DATA_DIR . '/admin/settings.json'));
}

function logAction(string $type, string $action, array $data = []): void {
    $dir = DATA_DIR . '/admin/logs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $f = $dir . '/' . date('Y-m-d') . '.log';
    file_put_contents($f, json_encode(['t' => date('H:i:s'), 'ts' => time(), 'type' => $type, 'action' => $action, 'data' => $data, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']) . "\n", FILE_APPEND | LOCK_EX);
}

function jRes(array $d, int $c = 200): void { http_response_code($c); header('Content-Type: application/json'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function jErr(string $m, int $c = 400): void { jRes(['error' => $m], $c); }

function publicProfile(array $u): array {
    return [
        'id' => $u['id'], 'username' => $u['username'], 'displayName' => $u['displayName'],
        'avatar' => $u['profile']['avatar'] ?? '', 'bio' => $u['profile']['bio'] ?? '',
        'status' => $u['profile']['status'] ?? 'offline', 'lastSeen' => $u['last_seen'] ?? 0
    ];
}
