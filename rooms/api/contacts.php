<?php
/* ============================================================================
 * api/contacts.php — Contacts, P2P chats, User search, Block
 * ============================================================================ */
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

/* ========== SEARCH USERS ========== */
if ($action === 'search') {
    $user = requireAuth();
    $q = strtolower(trim($_GET['q'] ?? ''));
    if (strlen($q) < 3) jErr('Enter exact username (min 3 chars)');

    $idx = readJson(DATA_DIR . '/users/_index.json');
    $results = [];

    // Exact match only
    if (isset($idx[$q])) {
        $uid = $idx[$q];
        if ($uid !== $user['id']) {
            $u = findById($uid);
            if ($u && empty($u['isBanned'])) {
                // Check if user is searchable
                $searchable = $u['privacy']['searchable'] ?? true;
                if ($searchable) {
                    $isContact = in_array($uid, $user['contacts'] ?? []);
                    $isBlocked = in_array($uid, $user['blocked'] ?? []);
                    $results[] = array_merge(publicProfile($u), ['isContact' => $isContact, 'isBlocked' => $isBlocked]);
                }
            }
        }
    }
    jRes(['users' => $results]);
}

/* ========== ADD CONTACT ========== */
if ($action === 'add') {
    $user = requireAuth();
    $targetId = $_POST['userId'] ?? '';
    if (!$targetId) jErr('userId required');

    $target = findById($targetId);
    if (!$target) jErr('User not found');
    if ($targetId === $user['id']) jErr('Cannot add yourself');

    if (!in_array($targetId, $user['contacts'] ?? [])) {
        $user['contacts'][] = $targetId;
        writeJson(DATA_DIR . '/users/' . $user['id'] . '.json', $user);
    }
    jRes(['ok' => true]);
}

/* ========== REMOVE CONTACT ========== */
if ($action === 'remove') {
    $user = requireAuth();
    $targetId = $_POST['userId'] ?? '';
    $user['contacts'] = array_values(array_filter($user['contacts'] ?? [], fn($c) => $c !== $targetId));
    writeJson(DATA_DIR . '/users/' . $user['id'] . '.json', $user);
    jRes(['ok' => true]);
}

/* ========== BLOCK USER ========== */
if ($action === 'block') {
    $user = requireAuth();
    $targetId = $_POST['userId'] ?? '';
    if (!$targetId) jErr('userId required');

    if (!in_array($targetId, $user['blocked'] ?? [])) {
        $user['blocked'][] = $targetId;
    }
    $user['contacts'] = array_values(array_filter($user['contacts'] ?? [], fn($c) => $c !== $targetId));
    writeJson(DATA_DIR . '/users/' . $user['id'] . '.json', $user);
    jRes(['ok' => true]);
}

/* ========== UNBLOCK USER ========== */
if ($action === 'unblock') {
    $user = requireAuth();
    $targetId = $_POST['userId'] ?? '';
    $user['blocked'] = array_values(array_filter($user['blocked'] ?? [], fn($b) => $b !== $targetId));
    writeJson(DATA_DIR . '/users/' . $user['id'] . '.json', $user);
    jRes(['ok' => true]);
}

/* ========== LIST CONTACTS ========== */
if ($action === 'list') {
    $user = requireAuth();
    $contacts = [];
    foreach ($user['contacts'] ?? [] as $cid) {
        $c = findById($cid);
        if ($c && empty($c['isBanned'])) {
            $contacts[] = publicProfile($c);
        }
    }
    usort($contacts, fn($a, $b) => strcasecmp($a['displayName'], $b['displayName']));
    jRes(['contacts' => $contacts]);
}

/* ========== START P2P CHAT ========== */
if ($action === 'startP2P') {
    $user = requireAuth();
    $targetId = $_POST['userId'] ?? '';
    if (!$targetId) jErr('userId required');
    if ($targetId === $user['id']) jErr('Cannot chat with yourself');

    $target = findById($targetId);
    if (!$target) jErr('User not found');

    // Check if blocked
    if (in_array($user['id'], $target['blocked'] ?? [])) jErr('Cannot message this user');
    if (in_array($targetId, $user['blocked'] ?? [])) jErr('You have blocked this user');

    // Check for existing P2P chat
    $p2pIndex = readJson(DATA_DIR . '/messages/_p2p_index.json');
    $key1 = $user['id'] . ':' . $targetId;
    $key2 = $targetId . ':' . $user['id'];

    if (isset($p2pIndex[$key1])) jRes(['ok' => true, 'chatId' => $p2pIndex[$key1], 'existing' => true]);
    if (isset($p2pIndex[$key2])) jRes(['ok' => true, 'chatId' => $p2pIndex[$key2], 'existing' => true]);

    // Create new P2P chat
    $chatId = 'p2p-' . uuidv4();
    $chat = [
        'id' => $chatId,
        'type' => 'p2p',
        'participants' => [$user['id'], $targetId],
        'created_at' => time()
    ];

    writeJson(DATA_DIR . '/messages/' . $chatId . '_meta.json', $chat);
    $p2pIndex[$key1] = $chatId;
    $p2pIndex[$key2] = $chatId;
    writeJson(DATA_DIR . '/messages/_p2p_index.json', $p2pIndex);

    jRes(['ok' => true, 'chatId' => $chatId, 'existing' => false]);
}

/* ========== LIST P2P CHATS ========== */
if ($action === 'listP2P') {
    $user = requireAuth();
    $p2pIndex = readJson(DATA_DIR . '/messages/_p2p_index.json');
    $chats = [];
    $seen = [];

    foreach ($p2pIndex as $key => $chatId) {
        if (in_array($chatId, $seen)) continue;
        if (strpos($key, $user['id']) === false) continue;

        $meta = readJson(DATA_DIR . '/messages/' . $chatId . '_meta.json');
        if (empty($meta) || ($meta['type'] ?? '') !== 'p2p') continue;

        $otherId = null;
        foreach ($meta['participants'] ?? [] as $pid) {
            if ($pid !== $user['id']) { $otherId = $pid; break; }
        }
        if (!$otherId) continue;

        $other = findById($otherId);
        if (!$other) continue;

        // Check blocked
        if (in_array($otherId, $user['blocked'] ?? [])) continue;

        $chats[] = [
            'chatId' => $chatId,
            'type' => 'p2p',
            'user' => publicProfile($other),
            'lastMessage' => $meta['lastMessage'] ?? null,
            'lastActivity' => $meta['lastActivity'] ?? $meta['created_at'] ?? 0
        ];

        $seen[] = $chatId;
    }

    usort($chats, fn($a, $b) => ($b['lastActivity'] ?? 0) - ($a['lastActivity'] ?? 0));
    jRes(['chats' => $chats]);
}

/* ========== GET USER PROFILE ========== */
if ($action === 'profile') {
    $user = requireAuth();
    $userId = $_GET['userId'] ?? '';
    if (!$userId) jErr('userId required');

    $target = findById($userId);
    if (!$target) jErr('User not found');

    $profile = publicProfile($target);
    $profile['isContact'] = in_array($userId, $user['contacts'] ?? []);
    $profile['isBlocked'] = in_array($userId, $user['blocked'] ?? []);
    jRes(['profile' => $profile]);
}

jErr('Unknown action');
