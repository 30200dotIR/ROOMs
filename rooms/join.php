<?php
/* join.php — Direct room join link handler
 * Usage: join.php?r=<roomId> or join.php?h=<handle>
 */
require_once __DIR__ . '/api/utils.php';
startSession();

$roomId = preg_replace('/[^a-z0-9\-]/', '', $_GET['r'] ?? '');
$handle = sanitizeUsername($_GET['h'] ?? '');

if (!$roomId && !$handle) { header('Location: index.html'); exit; }

// Find room
$room = null;
if ($roomId) {
    $room = readJson(DATA_DIR . '/rooms/' . $roomId . '.json');
} elseif ($handle) {
    $idx = readJson(DATA_DIR . '/rooms/_index.json');
    foreach ($idx as $rid => $info) {
        if (($info['handle'] ?? '') === $handle) {
            $roomId = $rid;
            $room = readJson(DATA_DIR . '/rooms/' . $rid . '.json');
            break;
        }
    }
}

if (empty($room)) { header('Location: index.html'); exit; }

$isLoggedIn = !empty($_SESSION['user_id']);
$needsPassword = false;

$isMember = false;
if ($isLoggedIn) {
    foreach ($room['members'] as $m) {
        if ($m['userId'] === $_SESSION['user_id']) { $isMember = true; break; }
    }
}

// If already member, redirect to chat
if ($isMember) {
    header('Location: chat.php?type=room&id=' . $roomId);
    exit;
}

$roomName = $room['name'] ?? 'Room';
$roomDesc = $room['description'] ?? '';
$roomAvatar = $room['avatar'] ?? '';
$memberCount = count($room['members']);
$roomType = $room['type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Join <?=htmlspecialchars($roomName)?> — ROOMs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif}
body{background:#0B141A;color:#E9EDEF;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{max-width:400px;width:100%;background:#111B21;border-radius:16px;padding:32px;text-align:center;border:1px solid rgba(134,150,160,.15)}
.av{width:80px;height:80px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;background-size:cover;background-position:center}
h1{font-size:22px;margin-bottom:4px}
.handle{color:#8696A0;font-size:14px;margin-bottom:8px}
.desc{color:#8696A0;font-size:14px;margin-bottom:16px}
.meta{display:flex;gap:16px;justify-content:center;margin-bottom:24px;font-size:13px;color:#8696A0}
.badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600}
.badge.public{background:rgba(37,211,102,.15);color:#25D366}
.badge.private{background:rgba(234,45,63,.15);color:#EA2D3F}

input{width:100%;padding:12px 16px;border:1px solid rgba(134,150,160,.2);border-radius:12px;background:transparent;color:#E9EDEF;font-size:15px;outline:none;margin-bottom:12px}
input:focus{border-color:#EA2D3F}
.btn{width:100%;padding:14px;border:none;border-radius:12px;font-size:16px;font-weight:600;cursor:pointer;background:#EA2D3F;color:#fff;margin-bottom:8px}
.btn:hover{background:#c62828}
.btn2{background:transparent;border:1px solid rgba(134,150,160,.3);color:#8696A0}
.btn2:hover{background:rgba(255,255,255,.05)}
.err{color:#EA2D3F;font-size:13px;margin-bottom:8px;display:none}
</style>
</head>
<body>
<div class="card">
<?php if($roomAvatar):?>
<div class="av" style="background-image:url('<?=htmlspecialchars($roomAvatar)?>')"></div>
<?php else:?>
<div class="av" style="background:hsl(<?=abs(crc32($roomName))%360?>,60%,40%)"><?=strtoupper(mb_substr($roomName,0,2))?></div>
<?php endif;?>

<h1><?=htmlspecialchars($roomName)?></h1>
<?php if($room['handle']??''):?><div class="handle">@<?=htmlspecialchars($room['handle'])?></div><?php endif;?>
<?php if($roomDesc):?><div class="desc"><?=htmlspecialchars($roomDesc)?></div><?php endif;?>

<div class="meta">
<span><span class="badge <?=$roomType?>"><?=ucfirst($roomType)?></span></span>
<span><?=$memberCount?> member<?=$memberCount>1?'s':''?></span>
</div>

<?php if(!$isLoggedIn):?>
<p style="color:#8696A0;margin-bottom:16px;font-size:14px">You need to log in first to join this room.</p>
<a href="index.html" class="btn" style="display:block;text-decoration:none;text-align:center">Log In / Register</a>
<?php else:?>
<div id="err" class="err"></div>
<?php if($needsPassword):?>
<input type="password" id="pw" placeholder="Room password required…"/>
<?php endif;?>
<button class="btn" onclick="joinRoom()">Join Room</button>
<a href="app.php" class="btn btn2" style="display:block;text-decoration:none;text-align:center;margin-top:8px">Cancel</a>
<?php endif;?>
</div>

<?php if($isLoggedIn):?>
<script>
async function joinRoom(){
    const err=document.getElementById('err');
    const pw=document.getElementById('pw');
    err.style.display='none';
    const fd=new FormData();
    fd.append('action','joinByLink');
    fd.append('roomId','<?=$roomId?>');
    if(pw)fd.append('password',pw.value);
    try{
        const r=await fetch('api/rooms.php',{method:'POST',body:fd});
        const d=await r.json();
        if(d.error){err.textContent=d.error;err.style.display='block';return;}
        location.href='chat.php?type=room&id=<?=$roomId?>';
    }catch(e){err.textContent='Connection error';err.style.display='block';}
}
</script>
<?php endif;?>
</body>
</html>
