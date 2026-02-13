<?php
/* api/auth.php — Register, Login, Logout, Profile, Username change */
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

/* ========== REGISTER ========== */
if ($action === 'register') {
    $settings = getSettings();
    $displayName = trim($_POST['displayName'] ?? '');
    $username = sanitizeUsername($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$displayName || strlen($displayName)<2 || strlen($displayName)>50) jErr('Name: 2-50 chars');
    if (!$username || strlen($username)<3 || strlen($username)>20) jErr('Username: 3-20 chars');
    if (preg_match('/^[0-9]/', $username)) jErr('Username cannot start with number');
    $reserved = ['admin','system','bot','api','help','support','info','root','moderator','mod','staff','null','undefined'];
    if (in_array($username, $reserved)) jErr('Reserved username');
    if (strlen($password) < ($settings['minPasswordLength']??8)) jErr('Password too short');
    if (!checkRateLimit('register_'.($_SERVER['REMOTE_ADDR']??''), 5, 15)) jErr('Too many attempts', 429);
    if (findByUsername($username)) jErr('Username taken');

    $id = uuidv4();
    $user = [
        'id'=>$id, 'username'=>$username, 'displayName'=>sanitize($displayName),
        'password'=>hashPw($password), 'numericId'=>random_int(100000000,999999999),
        'profile'=>['avatar'=>'','bio'=>'Hey there! I\'m using ROOMs 👋','status'=>'online'],
        'privacy'=>['lastSeen'=>'everyone','profilePhoto'=>'everyone','readReceipts'=>true,'searchable'=>true],
        'settings'=>['theme'=>'dark','notifications'=>true,'soundEnabled'=>true,'fontSize'=>'medium','enterToSend'=>true],
        'contacts'=>[],'blocked'=>[],'pinnedChats'=>[],'archivedChats'=>[],
        'stats'=>['messagesSent'=>0,'messagesReceived'=>0,'roomsJoined'=>0,'roomsCreated'=>0],
        'created_at'=>time(),'last_seen'=>time(),'isAdmin'=>false,'isBanned'=>false
    ];
    writeJson(DATA_DIR.'/users/'.$id.'.json', $user);
    $idx = readJson(DATA_DIR.'/users/_index.json');
    $idx[$username] = $id;
    writeJson(DATA_DIR.'/users/_index.json', $idx);
    startSession();
    $_SESSION['user_id'] = $id; $_SESSION['username'] = $username;
    $safe=$user; unset($safe['password']);
    jRes(['ok'=>true,'user'=>$safe]);
}

/* ========== LOGIN ========== */
if ($action === 'login') {
    $username = sanitizeUsername($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) jErr('Username and password required');
    if (!checkRateLimit('login_'.($_SERVER['REMOTE_ADDR']??''), 10, 5)) jErr('Too many attempts', 429);
    $user = findByUsername($username);
    if (!$user || !verifyPw($password, $user['password'])) jErr('Invalid credentials');
    if (!empty($user['isBanned'])) jErr('Account suspended', 403);
    $user['last_seen']=time();$user['profile']['status']='online';
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json',$user);
    startSession(); session_regenerate_id(true);
    $_SESSION['user_id']=$user['id'];$_SESSION['username']=$user['username'];
    $safe=$user; unset($safe['password']);
    jRes(['ok'=>true,'user'=>$safe]);
}

/* ========== LOGOUT ========== */
if ($action === 'logout') {
    startSession();
    if (!empty($_SESSION['user_id'])) {
        $user = findById($_SESSION['user_id']);
        if ($user) { $user['profile']['status']='offline';$user['last_seen']=time();writeJson(DATA_DIR.'/users/'.$user['id'].'.json',$user); }
    }
    session_destroy();
    jRes(['ok'=>true]);
}

/* ========== CHECK ========== */
if ($action === 'check') {
    $user = getUser();
    if (!$user) jRes(['authenticated'=>false]);
    $safe=$user; unset($safe['password']);
    jRes(['authenticated'=>true,'user'=>$safe]);
}

/* ========== CHECK USERNAME ========== */
if ($action === 'checkUsername') {
    $username = sanitizeUsername($_GET['username'] ?? '');
    if (!$username || strlen($username)<3) jRes(['available'=>false,'reason'=>'Too short']);
    $reserved = ['admin','system','bot','api','help','support','info','root','moderator','mod','staff'];
    if (in_array($username, $reserved)) jRes(['available'=>false,'reason'=>'Reserved']);
    jRes(['available'=>!findByUsername($username)]);
}

/* ========== UPDATE PROFILE ========== */
if ($action === 'updateProfile') {
    $user = requireAuth();
    $displayName = trim($_POST['displayName'] ?? $user['displayName']);
    $bio = trim($_POST['bio'] ?? $user['profile']['bio']);
    if (strlen($displayName)>=2 && strlen($displayName)<=50) $user['displayName'] = sanitize($displayName);
    if (strlen($bio)<=200) $user['profile']['bio'] = sanitize($bio);
    if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $safe = 'avatar_'.$user['id'].'.'.$ext;
            $dir = UPLOADS_DIR.'/profiles/original';if(!is_dir($dir))mkdir($dir,0755,true);
            move_uploaded_file($_FILES['avatar']['tmp_name'], $dir.'/'.$safe);
            $user['profile']['avatar'] = 'uploads/profiles/original/'.$safe;
        }
    }
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);
    $safe=$user; unset($safe['password']);
    jRes(['ok'=>true,'user'=>$safe]);
}

/* ========== CHANGE USERNAME ========== */
if ($action === 'changeUsername') {
    $user = requireAuth();
    $newUsername = sanitizeUsername($_POST['username'] ?? '');
    if (!$newUsername || strlen($newUsername)<3 || strlen($newUsername)>20) jErr('Username: 3-20 chars');
    if (preg_match('/^[0-9]/', $newUsername)) jErr('Cannot start with number');
    $reserved = ['admin','system','bot','api','help','support','info','root','moderator','mod','staff'];
    if (in_array($newUsername, $reserved)) jErr('Reserved');
    if ($newUsername === $user['username']) jRes(['ok'=>true,'user'=>$user]);
    if (findByUsername($newUsername)) jErr('Username taken');
    if (!checkRateLimit('username_change_'.$user['id'], 1, 1440)) jErr('Can only change username once per day');

    $oldUsername = $user['username'];
    $user['username'] = $newUsername;
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);
    // Update index
    $idx = readJson(DATA_DIR.'/users/_index.json');
    unset($idx[$oldUsername]);
    $idx[$newUsername] = $user['id'];
    writeJson(DATA_DIR.'/users/_index.json', $idx);
    $_SESSION['username'] = $newUsername;
    $safe=$user; unset($safe['password']);
    jRes(['ok'=>true,'user'=>$safe]);
}

/* ========== UPDATE SETTINGS ========== */
if ($action === 'updateSettings') {
    $user = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if(isset($input['theme']))$user['settings']['theme']=in_array($input['theme'],['light','dark'])?$input['theme']:'dark';
    if(isset($input['notifications']))$user['settings']['notifications']=(bool)$input['notifications'];
    if(isset($input['soundEnabled']))$user['settings']['soundEnabled']=(bool)$input['soundEnabled'];
    if(isset($input['fontSize']))$user['settings']['fontSize']=in_array($input['fontSize'],['small','medium','large'])?$input['fontSize']:'medium';
    if(isset($input['enterToSend']))$user['settings']['enterToSend']=(bool)$input['enterToSend'];
    if(isset($input['privacy'])){
        foreach(['lastSeen','profilePhoto','readReceipts','searchable'] as $k){
            if(isset($input['privacy'][$k]))$user['privacy'][$k]=$input['privacy'][$k];
        }
    }
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);
    $safe=$user; unset($safe['password']);
    jRes(['ok'=>true,'user'=>$safe]);
}

/* ========== CHANGE PASSWORD ========== */
if ($action === 'changePassword') {
    $user = requireAuth();
    $current = $_POST['currentPassword'] ?? '';
    $newPass = $_POST['newPassword'] ?? '';
    if (!verifyPw($current, $user['password'])) jErr('Wrong current password');
    if (strlen($newPass)<8) jErr('Min 8 characters');
    $user['password'] = hashPw($newPass);
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);
    jRes(['ok'=>true]);
}

jErr('Unknown action');
