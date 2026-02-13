<?php
/* api/rooms.php — Room CRUD, Join, Leave, Members, Admin */
require_once __DIR__ . '/utils.php';
header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

/* ========== CREATE ========== */
if ($action === 'create') {
    $user = requireAuth();
    $name = trim($_POST['name'] ?? '');
    $type = in_array($_POST['type']??'', ['public','private']) ? $_POST['type'] : 'public';
    $description = sanitize(trim($_POST['description'] ?? ''));
    $handle = sanitizeUsername($_POST['handle'] ?? '');

    if (!$name || strlen($name)<2 || strlen($name)>50) jErr('Room name must be 2-50 chars');
    if (!checkRateLimit('room_create_'.$user['id'], 5, 60)) jErr('Too many rooms. Wait.', 429);

    // Handle for public rooms
    if ($type === 'public') {
        if (!$handle || strlen($handle)<3) jErr('Public rooms need a handle (min 3 chars)');
        $ridx = readJson(DATA_DIR.'/rooms/_index.json');
        foreach ($ridx as $r) { if (($r['handle']??'')===$handle) jErr('Handle already taken'); }
    }

    $id = uuidv4();
    $inviteCode = substr(bin2hex(random_bytes(6)),0,12);
    $slug = strtolower(preg_replace('/[^a-z0-9]/','-',strtolower($name)));
    $slug = preg_replace('/-+/','-',trim($slug,'-'));

    $room = [
        'id'=>$id, 'type'=>$type, 'name'=>sanitize($name), 'slug'=>$slug,
        'handle'=>$handle, 'description'=>$description, 'avatar'=>'',
        'owner'=>$user['id'], 'admins'=>[$user['id']],
        'members'=>[['userId'=>$user['id'],'joinedAt'=>time(),'role'=>'owner']],
        'banned'=>[], 'inviteCode'=>$inviteCode,
        'settings'=>['requireApproval'=>false,'allowInvites'=>true,'allowFileSharing'=>true,
            'maxMembers'=>getSettings()['maxMembersPerRoom']??500],
        'pinnedMessages'=>[], 'status'=>'active', 'created_at'=>time(),
        'stats'=>['totalMessages'=>0,'totalMembers'=>1]
    ];

    if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
            $safe='room_'.$id.'.'.$ext; $dir=UPLOADS_DIR.'/profiles/original';
            if(!is_dir($dir))mkdir($dir,0755,true);
            move_uploaded_file($_FILES['avatar']['tmp_name'],$dir.'/'.$safe);
            $room['avatar']='uploads/profiles/original/'.$safe;
        }
    }

    writeJson(DATA_DIR.'/rooms/'.$id.'.json', $room);
    $idx = readJson(DATA_DIR.'/rooms/_index.json');
    $idx[$id]=['name'=>$room['name'],'slug'=>$slug,'handle'=>$handle,'type'=>$type,'owner'=>$user['id'],'members'=>1,'created'=>time()];
    writeJson(DATA_DIR.'/rooms/_index.json', $idx);

    $user['stats']['roomsCreated']=($user['stats']['roomsCreated']??0)+1;
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json', $user);

    $sysMsg=['id'=>uuidv4(),'chatId'=>$id,'type'=>'system','action'=>'room_created','from'=>'system',
        'data'=>['displayName'=>$user['displayName']],'timestamp'=>time(),'text'=>$user['displayName'].' created the room'];
    writeJson(DATA_DIR.'/messages/'.$id.'.json', [$sysMsg]);

    jRes(['ok'=>true,'room'=>$room]);
}

/* ========== LIST ========== */
if ($action === 'list') {
    $user = requireAuth();
    $type = $_GET['type']??'all';
    $idx = readJson(DATA_DIR.'/rooms/_index.json');
    $rooms = [];
    foreach ($idx as $rid=>$info) {
        $room = readJson(DATA_DIR.'/rooms/'.$rid.'.json');
        if (empty($room)) continue;
        $isMember=false;
        foreach($room['members'] as $m){if($m['userId']===$user['id']){$isMember=true;break;}}
        foreach($room['banned']??[] as $b){if($b['userId']===$user['id'])continue 2;}
        if ($type==='my'&&!$isMember) continue;
        if ($type==='public'&&$room['type']!=='public') continue;
        $meta = readJson(DATA_DIR.'/messages/'.$rid.'_meta.json');
        $rooms[]=['id'=>$rid,'name'=>$room['name'],'handle'=>$room['handle']??'','type'=>$room['type'],'avatar'=>$room['avatar'],
            'description'=>$room['description'],'memberCount'=>count($room['members']),'isMember'=>$isMember,
            'isOwner'=>$room['owner']===$user['id'],'lastMessage'=>$meta['lastMessage']??null,
            'lastActivity'=>$meta['lastActivity']??$room['created_at'],'inviteCode'=>$isMember?$room['inviteCode']:null];
    }
    usort($rooms, fn($a,$b)=>($b['lastActivity']??0)-($a['lastActivity']??0));
    jRes(['rooms'=>$rooms]);
}

/* ========== GET ========== */
if ($action === 'get') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_GET['roomId']??'');
    if (!$roomId) jErr('roomId required');
    $room = readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if (empty($room)) jErr('Not found',404);
    $isMember=false;$myRole='none';
    foreach($room['members'] as $m){if($m['userId']===$user['id']){$isMember=true;$myRole=$m['role'];break;}}
    if(!$isMember&&$room['type']!=='public')jErr('Access denied',403);
    $room['myRole']=$myRole;$room['isMember']=$isMember;unset($room['banned']);
    jRes(['room'=>$room]);
}

/* ========== JOIN ========== */
if ($action === 'join') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??'');
    $inviteCode = $_POST['inviteCode']??'';
    if (!$roomId) jErr('roomId required');
    $room = readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if (empty($room)) jErr('Not found',404);
    foreach($room['banned']??[] as $b){if($b['userId']===$user['id'])jErr('You are banned');}
    foreach($room['members'] as $m){if($m['userId']===$user['id'])jRes(['ok'=>true,'roomId'=>$roomId,'alreadyMember'=>true]);}
    if ($room['type']==='private'&&$inviteCode!==$room['inviteCode'])jErr('Invalid invite code');
    if(count($room['members'])>=($room['settings']['maxMembers']??500))jErr('Room is full');

    $room['members'][]=['userId'=>$user['id'],'joinedAt'=>time(),'role'=>'member'];
    $room['stats']['totalMembers']=count($room['members']);
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json', $room);
    $idx=readJson(DATA_DIR.'/rooms/_index.json');
    if(isset($idx[$roomId])){$idx[$roomId]['members']=count($room['members']);writeJson(DATA_DIR.'/rooms/_index.json',$idx);}

    $sysMsg=['id'=>uuidv4(),'chatId'=>$roomId,'type'=>'system','action'=>'user_joined','from'=>'system',
        'data'=>['displayName'=>$user['displayName'],'userId'=>$user['id']],
        'timestamp'=>time(),'text'=>$user['displayName'].' joined the room'];
    $msgs=readJson(DATA_DIR.'/messages/'.$roomId.'.json');$msgs[]=$sysMsg;
    writeJson(DATA_DIR.'/messages/'.$roomId.'.json',$msgs);

    $user['stats']['roomsJoined']=($user['stats']['roomsJoined']??0)+1;
    writeJson(DATA_DIR.'/users/'.$user['id'].'.json',$user);
    jRes(['ok'=>true,'roomId'=>$roomId,'room'=>$room]);
}

/* ========== LEAVE ========== */
if ($action === 'leave') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??'');
    if (!$roomId) jErr('roomId required');
    $room = readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if (empty($room)) jErr('Not found',404);
    if ($room['owner']===$user['id'])jErr('Owner cannot leave. Transfer ownership first.');
    $room['members']=array_values(array_filter($room['members'],fn($m)=>$m['userId']!==$user['id']));
    $room['admins']=array_values(array_filter($room['admins'],fn($a)=>$a!==$user['id']));
    $room['stats']['totalMembers']=count($room['members']);
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json',$room);
    $sysMsg=['id'=>uuidv4(),'chatId'=>$roomId,'type'=>'system','action'=>'user_left','from'=>'system',
        'data'=>['displayName'=>$user['displayName']],'timestamp'=>time(),'text'=>$user['displayName'].' left the room'];
    $msgs=readJson(DATA_DIR.'/messages/'.$roomId.'.json');$msgs[]=$sysMsg;writeJson(DATA_DIR.'/messages/'.$roomId.'.json',$msgs);
    jRes(['ok'=>true]);
}

/* ========== JOIN BY CODE ========== */
if ($action === 'joinByCode') {
    $user = requireAuth();
    $code = preg_replace('/[^a-z0-9]/','',strtolower($_POST['code']??''));
    if (!$code) jErr('Code required');
    $idx=readJson(DATA_DIR.'/rooms/_index.json');
    foreach($idx as $rid=>$info){
        $room=readJson(DATA_DIR.'/rooms/'.$rid.'.json');
        if(!empty($room)&&($room['inviteCode']??'')===$code){
            foreach($room['banned']??[] as $b){if($b['userId']===$user['id'])jErr('You are banned');}
            foreach($room['members'] as $m){if($m['userId']===$user['id'])jRes(['ok'=>true,'roomId'=>$rid,'alreadyMember'=>true]);}
            $room['members'][]=['userId'=>$user['id'],'joinedAt'=>time(),'role'=>'member'];
            $room['stats']['totalMembers']=count($room['members']);
            writeJson(DATA_DIR.'/rooms/'.$rid.'.json',$room);
            $idx[$rid]['members']=count($room['members']);writeJson(DATA_DIR.'/rooms/_index.json',$idx);
            $sysMsg=['id'=>uuidv4(),'chatId'=>$rid,'type'=>'system','action'=>'user_joined','from'=>'system',
                'data'=>['displayName'=>$user['displayName']],'timestamp'=>time(),'text'=>$user['displayName'].' joined the room'];
            $msgs=readJson(DATA_DIR.'/messages/'.$rid.'.json');$msgs[]=$sysMsg;writeJson(DATA_DIR.'/messages/'.$rid.'.json',$msgs);
            jRes(['ok'=>true,'roomId'=>$rid]);
        }
    }
    jErr('Invalid invite code');
}

/* ========== JOIN BY HANDLE ========== */
if ($action === 'joinByHandle') {
    $user = requireAuth();
    $handle = sanitizeUsername($_POST['handle']??$_GET['handle']??'');
    if (!$handle) jErr('Handle required');
    $idx=readJson(DATA_DIR.'/rooms/_index.json');
    foreach($idx as $rid=>$info){
        if(($info['handle']??'')===$handle){
            $room=readJson(DATA_DIR.'/rooms/'.$rid.'.json');
            if(empty($room))continue;
            if($room['type']!=='public')jErr('Room is not public');
            foreach($room['members'] as $m){if($m['userId']===$user['id'])jRes(['ok'=>true,'roomId'=>$rid,'alreadyMember'=>true,'room'=>$room]);}
            jRes(['ok'=>true,'roomId'=>$rid,'room'=>$room,'isMember'=>false]);
        }
    }
    jErr('Room not found');
}

/* ========== JOIN BY LINK ========== */
if ($action === 'joinByLink') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??$_GET['roomId']??'');
    if(!$roomId)jErr('roomId required');
    $room=readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if(empty($room))jErr('Room not found',404);
    foreach($room['banned']??[] as $b){if($b['userId']===$user['id'])jErr('You are banned');}
    foreach($room['members'] as $m){if($m['userId']===$user['id'])jRes(['ok'=>true,'roomId'=>$roomId,'alreadyMember'=>true]);}
    $room['members'][]=['userId'=>$user['id'],'joinedAt'=>time(),'role'=>'member'];
    $room['stats']['totalMembers']=count($room['members']);
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json',$room);
    $idx=readJson(DATA_DIR.'/rooms/_index.json');if(isset($idx[$roomId])){$idx[$roomId]['members']=count($room['members']);writeJson(DATA_DIR.'/rooms/_index.json',$idx);}
    $sysMsg=['id'=>uuidv4(),'chatId'=>$roomId,'type'=>'system','action'=>'user_joined','from'=>'system',
        'data'=>['displayName'=>$user['displayName']],'timestamp'=>time(),'text'=>$user['displayName'].' joined the room'];
    $msgs=readJson(DATA_DIR.'/messages/'.$roomId.'.json');$msgs[]=$sysMsg;writeJson(DATA_DIR.'/messages/'.$roomId.'.json',$msgs);
    jRes(['ok'=>true,'roomId'=>$roomId]);
}

/* ========== SEARCH BY HANDLE ========== */
if ($action === 'search') {
    $user = requireAuth();
    $q = strtolower(trim($_GET['q']??''));
    if(!$q)jErr('Query required');
    $idx=readJson(DATA_DIR.'/rooms/_index.json');
    $results=[];
    foreach($idx as $rid=>$info){
        if($info['type']!=='public')continue;
        $h=$info['handle']??'';$n=strtolower($info['name']);
        if($h===$q||$n===$q||strpos($h,$q)===0||strpos($n,$q)!==false){
            $room=readJson(DATA_DIR.'/rooms/'.$rid.'.json');if(empty($room))continue;
            $isMember=false;foreach($room['members'] as $m){if($m['userId']===$user['id']){$isMember=true;break;}}
            $results[]=['id'=>$rid,'name'=>$room['name'],'handle'=>$h,'type'=>$room['type'],'avatar'=>$room['avatar'],'description'=>$room['description'],'memberCount'=>count($room['members']),'isMember'=>$isMember];
        }
    }
    jRes(['rooms'=>$results]);
}

/* ========== UPDATE ========== */
if ($action === 'update') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??'');
    if(!$roomId)jErr('roomId required');
    $room=readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if(empty($room))jErr('Not found',404);
    if($room['owner']!==$user['id']&&!in_array($user['id'],$room['admins']))jErr('Admin required',403);
    if(isset($_POST['name']))$room['name']=sanitize(trim($_POST['name']));
    if(isset($_POST['description']))$room['description']=sanitize(trim($_POST['description']));
    if(isset($_POST['handle'])&&$room['type']==='public')$room['handle']=sanitizeUsername($_POST['handle']);
    if(isset($_FILES['avatar'])&&is_uploaded_file($_FILES['avatar']['tmp_name'])){
        $ext=strtolower(pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','gif','webp'])){
            $safe='room_'.$roomId.'.'.$ext;$dir=UPLOADS_DIR.'/profiles/original';if(!is_dir($dir))mkdir($dir,0755,true);
            move_uploaded_file($_FILES['avatar']['tmp_name'],$dir.'/'.$safe);$room['avatar']='uploads/profiles/original/'.$safe;
        }
    }
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json',$room);
    $idx=readJson(DATA_DIR.'/rooms/_index.json');
    if(isset($idx[$roomId])){$idx[$roomId]['name']=$room['name'];$idx[$roomId]['handle']=$room['handle']??'';writeJson(DATA_DIR.'/rooms/_index.json',$idx);}
    jRes(['ok'=>true,'room'=>$room]);
}

/* ========== ADMIN: KICK/BAN/PROMOTE ========== */
if ($action === 'kick' || $action === 'ban') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??'');
    $targetId = $_POST['userId']??'';
    if(!$roomId||!$targetId)jErr('roomId and userId required');
    $room=readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if(empty($room))jErr('Not found',404);
    if($room['owner']!==$user['id']&&!in_array($user['id'],$room['admins']))jErr('Admin required',403);
    if($targetId===$room['owner'])jErr('Cannot kick/ban owner');
    $room['members']=array_values(array_filter($room['members'],fn($m)=>$m['userId']!==$targetId));
    $room['admins']=array_values(array_filter($room['admins'],fn($a)=>$a!==$targetId));
    if($action==='ban')$room['banned'][]=['userId'=>$targetId,'by'=>$user['id'],'time'=>time(),'reason'=>$_POST['reason']??''];
    $room['stats']['totalMembers']=count($room['members']);
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json',$room);
    $target=findById($targetId);$tName=$target?$target['displayName']:'User';
    $act=$action==='ban'?'user_banned':'user_kicked';
    $sysMsg=['id'=>uuidv4(),'chatId'=>$roomId,'type'=>'system','action'=>$act,'from'=>'system',
        'data'=>['displayName'=>$tName],'timestamp'=>time(),'text'=>$tName.' was '.($action==='ban'?'banned':'removed')];
    $msgs=readJson(DATA_DIR.'/messages/'.$roomId.'.json');$msgs[]=$sysMsg;writeJson(DATA_DIR.'/messages/'.$roomId.'.json',$msgs);
    jRes(['ok'=>true]);
}

if ($action === 'promote') {
    $user = requireAuth();
    $roomId = preg_replace('/[^a-z0-9\-]/','', $_POST['roomId']??'');
    $targetId = $_POST['userId']??'';
    $role = $_POST['role']??'admin';
    if(!$roomId||!$targetId)jErr('roomId and userId required');
    $room=readJson(DATA_DIR.'/rooms/'.$roomId.'.json');
    if(empty($room))jErr('Not found',404);
    if($room['owner']!==$user['id'])jErr('Only owner can promote',403);
    foreach($room['members'] as &$m){if($m['userId']===$targetId){$m['role']=$role;break;}}
    if($role==='admin'&&!in_array($targetId,$room['admins']))$room['admins'][]=$targetId;
    if($role==='member')$room['admins']=array_values(array_filter($room['admins'],fn($a)=>$a!==$targetId));
    writeJson(DATA_DIR.'/rooms/'.$roomId.'.json',$room);
    jRes(['ok'=>true]);
}

jErr('Unknown action');
