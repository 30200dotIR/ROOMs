<?php
/* api/messages.php — Send, Fetch, Edit, Delete, React, Deliver, Read */
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';
$chatId = preg_replace('/[^a-z0-9\-]/', '', $_REQUEST['chatId'] ?? '');

/* ========== SEND ========== */
if ($action === 'send') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    if (!checkRateLimit('msg_'.$user['id'], getSettings()['rateLimitMessages'], 1))
        jErr('Slow down!', 429);
    $text = trim($_POST['text'] ?? '');
    $replyTo = $_POST['replyTo'] ?? null;
    $msg = [
        'id' => uuidv4(), 'chatId' => $chatId, 'type' => 'text',
        'from' => $user['id'], 'fromUser' => $user['username'],
        'fromName' => $user['displayName'], 'fromAvatar' => $user['profile']['avatar'] ?? '',
        'text' => $text, 'file' => null, 'replyTo' => $replyTo,
        'mentions' => [], 'timestamp' => time(),
        'edited' => false, 'deleted' => false,
        'reactions' => new \stdClass(),
        'status' => 'sent', 'deliveredTo' => [], 'readBy' => []
    ];
    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $settings = getSettings();
        if ($_FILES['file']['size'] > $settings['maxFileSize']) jErr('File too large');
        $origName = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $mime = $_FILES['file']['type'];
        $imageExts=['jpg','jpeg','png','gif','webp'];$videoExts=['mp4','mov','webm'];
        $docExts=['pdf','doc','docx','xls','xlsx','txt','csv','ppt','pptx','zip','rar'];
        $audioExts=['mp3','m4a','wav','ogg','opus'];
        $allowed=[];
        if($settings['allowImages'])$allowed=array_merge($allowed,$imageExts);
        if($settings['allowVideos'])$allowed=array_merge($allowed,$videoExts);
        if($settings['allowDocuments'])$allowed=array_merge($allowed,$docExts);
        if($settings['allowVoice'])$allowed=array_merge($allowed,$audioExts);
        if(!in_array($ext,$allowed))jErr('File type not allowed');
        $safe=uniqid('f_',true).'.'.$ext;$subdir=date('Y/m');
        $dir=UPLOADS_DIR.'/files/'.$subdir;if(!is_dir($dir))mkdir($dir,0755,true);
        move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$safe);
        if(in_array($ext,$imageExts))$msg['type']='image';
        elseif(in_array($ext,$videoExts))$msg['type']='video';
        elseif(in_array($ext,$audioExts))$msg['type']='audio';
        else $msg['type']='file';
        $msg['file']=['name'=>$safe,'originalName'=>$origName,'size'=>$_FILES['file']['size'],
            'type'=>$mime,'url'=>'uploads/files/'.$subdir.'/'.$safe,'ext'=>$ext];
    }
    if(preg_match_all('/@([a-z0-9_]+)/',$text,$matches))$msg['mentions']=array_unique($matches[1]);
    $chatFile=DATA_DIR.'/messages/'.$chatId.'.json';
    $msgs=readJson($chatFile);$msgs[]=$msg;
    if(count($msgs)>5000)$msgs=array_slice($msgs,-5000);
    writeJson($chatFile,$msgs);
    $user['stats']['messagesSent']=($user['stats']['messagesSent']??0)+1;
    $user['last_seen']=time();$user['profile']['status']='online';
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json',$user);
    $chatMeta=DATA_DIR.'/messages/'.$chatId.'_meta.json';$meta=readJson($chatMeta);
    $meta['lastMessage']=['id'=>$msg['id'],'from'=>$user['id'],'fromName'=>$user['displayName'],
        'text'=>mb_substr($text,0,100),'type'=>$msg['type'],'timestamp'=>$msg['timestamp']];
    $meta['totalMessages']=($meta['totalMessages']??0)+1;$meta['lastActivity']=time();
    writeJson($chatMeta,$meta);
    jRes(['ok'=>true,'message'=>$msg]);
}

/* ========== FETCH ========== */
if ($action === 'fetch') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    $since = intval($_GET['since'] ?? 0);
    $limit = min(100, intval($_GET['limit'] ?? 50));
    $chatFile = DATA_DIR.'/messages/'.$chatId.'.json';
    $allMsgs = readJson($chatFile);
    $needSave = false;
    // Mark messages as delivered
    foreach ($allMsgs as &$m) {
        if (($m['from']??'') !== $user['id'] && !($m['deleted']??false) && ($m['type']??'')!=='system') {
            if (!in_array($user['id'], $m['deliveredTo']??[])) {
                $m['deliveredTo'][] = $user['id'];
                if (($m['status']??'sent') === 'sent') $m['status'] = 'delivered';
                $needSave = true;
            }
        }
    }
    unset($m);
    if ($needSave) writeJson($chatFile, $allMsgs);

    $filtered = $since ? array_filter($allMsgs, fn($m)=>$m['timestamp']>$since) : $allMsgs;
    $filtered = array_values($filtered);
    $total = count($filtered);
    $filtered = array_slice($filtered, -$limit);

    // Reciprocal privacy
    $myPrivacy = $user['privacy'] ?? [];
    $myReadReceipts = $myPrivacy['readReceipts'] ?? true;
    $myLastSeen = ($myPrivacy['lastSeen'] ?? 'everyone') === 'everyone';

    foreach ($filtered as &$fm) {
        if (!empty($fm['deleted'])) { $fm['text']=''; $fm['file']=null; $fm['type']='deleted'; }
        // If I disabled read receipts, I can't see who read my msgs either
        if (!$myReadReceipts && ($fm['from']??'') === $user['id']) {
            $fm['readBy'] = [];
            $fm['status'] = count($fm['deliveredTo']??[]) > 0 ? 'delivered' : 'sent';
        }
    }
    unset($fm);

    // Presence
    $statusFile = DATA_DIR.'/messages/'.$chatId.'_presence.json';
    $presence = readJson($statusFile);
    $presence[$user['id']] = ['user'=>$user['username'],'name'=>$user['displayName'],
        'time'=>time(),'avatar'=>$user['profile']['avatar']??''];
    writeJson($statusFile, $presence);
    $now = time(); $members = [];
    foreach ($presence as $uid => $p) {
        if ($now - $p['time'] <= 30) {
            if (!$myLastSeen && $uid !== $user['id']) {
                $p['online'] = false; // can't see others if you hid yours
            } else {
                $ou = findById($uid);
                $oLastSeen = ($ou['privacy']['lastSeen'] ?? 'everyone') === 'everyone';
                $p['online'] = $oLastSeen || $uid === $user['id'];
            }
            $members[] = $p;
        }
    }
    $user['last_seen'] = time();
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);
    jRes(['messages'=>$filtered,'members'=>$members,'total'=>$total]);
}

/* ========== MARK READ ========== */
if ($action === 'markRead') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    if (!($user['privacy']['readReceipts'] ?? true)) jRes(['ok'=>true]);
    $chatFile = DATA_DIR.'/messages/'.$chatId.'.json';
    $msgs = readJson($chatFile); $changed = false;
    foreach ($msgs as &$m) {
        if (($m['from']??'') !== $user['id'] && !($m['deleted']??false) && ($m['type']??'')!=='system') {
            if (!in_array($user['id'], $m['readBy']??[])) {
                $m['readBy'][] = $user['id'];
                $m['status'] = 'read';
                $changed = true;
            }
        }
    }
    if ($changed) writeJson($chatFile, $msgs);
    jRes(['ok'=>true]);
}

/* ========== DELETE ========== */
if ($action === 'delete') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    $msgId = $_POST['messageId']??'';
    if (!$msgId) jErr('messageId required');
    $chatFile = DATA_DIR.'/messages/'.$chatId.'.json';
    $msgs = readJson($chatFile);
    foreach ($msgs as &$m) {
        if ($m['id'] === $msgId) {
            if ($m['from'] !== $user['id']) {
                $room = readJson(DATA_DIR.'/rooms/'.$chatId.'.json');
                if(empty($room)||!in_array($user['id'],$room['admins']??[]))jErr('No permission');
            }
            $m['deleted']=true;$m['text']='';$m['file']=null;
            writeJson($chatFile,$msgs);jRes(['ok'=>true]);
        }
    }
    jErr('Not found');
}

/* ========== EDIT ========== */
if ($action === 'edit') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    $msgId = $_POST['messageId']??'';
    $newText = trim($_POST['text']??'');
    if(!$msgId||!$newText)jErr('messageId and text required');
    $chatFile = DATA_DIR.'/messages/'.$chatId.'.json';
    $msgs = readJson($chatFile);
    foreach ($msgs as &$m) {
        if ($m['id']===$msgId) {
            if($m['from']!==$user['id'])jErr('Own messages only');
            $m['text']=$newText;$m['edited']=true;$m['editedAt']=time();
            writeJson($chatFile,$msgs);jRes(['ok'=>true]);
        }
    }
    jErr('Not found');
}

/* ========== REACT ========== */
if ($action === 'react') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    $msgId = $_POST['messageId']??'';
    $emoji = $_POST['emoji']??'';
    if(!$msgId||!$emoji)jErr('messageId and emoji required');
    $chatFile = DATA_DIR.'/messages/'.$chatId.'.json';
    $msgs = readJson($chatFile);
    foreach ($msgs as &$m) {
        if ($m['id']===$msgId) {
            $reactions = (array)($m['reactions'] ?? []);
            // Toggle: if user already reacted with this emoji, remove
            $toggled = false;
            if (isset($reactions[$emoji]) && is_array($reactions[$emoji])) {
                $idx = array_search($user['id'], $reactions[$emoji]);
                if ($idx !== false) {
                    array_splice($reactions[$emoji], $idx, 1);
                    if (empty($reactions[$emoji])) unset($reactions[$emoji]);
                    $toggled = true;
                }
            }
            if (!$toggled) {
                // Remove from other emojis
                foreach ($reactions as $e => &$users) {
                    if(!is_array($users)){unset($reactions[$e]);continue;}
                    $users = array_values(array_filter($users, fn($u)=>$u!==$user['id']));
                    if(empty($users))unset($reactions[$e]);
                }
                unset($users);
                if(!isset($reactions[$emoji]))$reactions[$emoji]=[];
                $reactions[$emoji][]=$user['id'];
            }
            $m['reactions'] = empty($reactions) ? new \stdClass() : $reactions;
            writeJson($chatFile, $msgs);
            jRes(['ok'=>true,'reactions'=>$m['reactions']]);
        }
    }
    jErr('Not found');
}

/* ========== PRESENCE ========== */
if ($action === 'presence') {
    $user = requireAuth();
    if (!$chatId) jErr('chatId required');
    $sf = DATA_DIR.'/messages/'.$chatId.'_presence.json';
    $p = readJson($sf);
    $p[$user['id']] = ['user'=>$user['username'],'name'=>$user['displayName'],
        'time'=>time(),'avatar'=>$user['profile']['avatar']??''];
    writeJson($sf,$p);
    $user['last_seen']=time();$user['profile']['status']='online';
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json',$user);
    jRes(['ok'=>true]);
}

jErr('Unknown action');
