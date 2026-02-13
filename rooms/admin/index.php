<?php
session_start();
if(empty($_SESSION['admin'])){header('Location: login.php');exit;}
require_once __DIR__.'/../api/utils.php';

// Gather stats
$userIndex=readJson(__DIR__.'/../data/users/_index.json');
$roomIndex=readJson(__DIR__.'/../data/rooms/_index.json');
$totalUsers=count($userIndex);
$totalRooms=count($roomIndex);
$totalMessages=0;$onlineUsers=0;$bannedUsers=0;
$now=time();
foreach($userIndex as $uname=>$uid){
    $u=readJson(__DIR__."/../data/users/$uid.json");
    if(!empty($u['stats']['messagesSent']))$totalMessages+=$u['stats']['messagesSent'];
    if(!empty($u['lastSeen'])&&($now-$u['lastSeen'])<300)$onlineUsers++;
    if(!empty($u['isBanned']))$bannedUsers++;
}
function dirSize($d){$s=0;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS)) as $f)$s+=$f->getSize();return $s;}
$storage=dirSize(__DIR__.'/../data')+dirSize(__DIR__.'/../uploads');
function fmtSize($b){if($b>=1073741824)return round($b/1073741824,2).' GB';if($b>=1048576)return round($b/1048576,1).' MB';return round($b/1024).' KB';}
$settings=getSettings();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Dashboard — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--bg4:#2A3942;--green:#25D366;--teal:#128C7E;--text:#E9EDEF;--text2:#8696A0;--text3:#667781;--border:rgba(134,150,160,.15);--err:#E74C3C;--warn:#F39C12;--info:#3498DB;--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text)}
.wrap{max-width:960px;margin:0 auto;padding:16px}
nav{background:var(--bg2);border-bottom:1px solid var(--border);padding:0 16px;display:flex;align-items:center;height:56px;position:sticky;top:0;z-index:100}
nav .brand{font-size:18px;font-weight:700;color:var(--green);flex:1;display:flex;align-items:center;gap:8px}
nav .brand img{width:28px;height:28px;border-radius:6px}
nav a{color:var(--text2);text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px;transition:all .2s;margin-left:4px}
nav a:hover,nav a.active{background:var(--bg3);color:var(--text)}
nav a.logout{color:var(--err)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:20px 0}
.stat{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;text-align:center}
.stat .num{font-size:28px;font-weight:700;color:var(--green)}
.stat .lbl{font-size:12px;color:var(--text2);margin-top:4px;text-transform:uppercase;letter-spacing:.5px}
h2{font-size:16px;color:var(--text2);margin:24px 0 12px;text-transform:uppercase;letter-spacing:1px;font-weight:600}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:12px}
.card h3{font-size:15px;margin-bottom:12px}
.links{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.links a{display:flex;align-items:center;gap:10px;padding:14px 16px;background:var(--bg3);border-radius:10px;text-decoration:none;color:var(--text);font-size:14px;font-weight:500;transition:all .2s;border:1px solid transparent}
.links a:hover{border-color:var(--green);background:var(--bg4)}
.links a .icon{font-size:20px;width:32px;text-align:center}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px}
.info-row:last-child{border:none}
.info-row .val{color:var(--green);font-weight:600}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.badge.on{background:rgba(37,211,102,.15);color:var(--green)}
.badge.off{background:rgba(231,76,60,.15);color:var(--err)}
.bar{height:8px;background:var(--bg3);border-radius:4px;margin-top:8px;overflow:hidden}
.bar .fill{height:100%;background:var(--green);border-radius:4px;transition:width .5s}
@media(max-width:600px){.links{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<nav>
  <div class="brand"><img src="../icon.png" alt=""/> ROOMs Admin</div>
  <a href="index.php" class="active">Dashboard</a>
  <a href="users.php">Users</a>
  <a href="rooms.php">Rooms</a>
  <a href="settings.php">Settings</a>
  <a href="login.php?logout=1" class="logout">Logout</a>
</nav>
<div class="wrap">

<div class="stats">
  <div class="stat"><div class="num"><?=$totalUsers?></div><div class="lbl">Users</div></div>
  <div class="stat"><div class="num"><?=$totalRooms?></div><div class="lbl">Rooms</div></div>
  <div class="stat"><div class="num"><?=number_format($totalMessages)?></div><div class="lbl">Messages</div></div>
  <div class="stat"><div class="num"><?=$onlineUsers?></div><div class="lbl">Online</div></div>
</div>

<h2>System Info</h2>
<div class="card">
  <div class="info-row"><span>Storage Used</span><span class="val"><?=fmtSize($storage)?></span></div>
  <div class="info-row"><span>Banned Users</span><span class="val"><?=$bannedUsers?></span></div>
  <div class="info-row"><span>Registration</span><span class="badge <?=$settings['registration']?'on':'off'?>"><?=$settings['registration']?'Open':'Closed'?></span></div>
  <div class="info-row"><span>P2P Messaging</span><span class="badge <?=$settings['p2pEnabled']?'on':'off'?>"><?=$settings['p2pEnabled']?'Enabled':'Disabled'?></span></div>
  <div class="info-row"><span>Room Creation</span><span class="badge <?=$settings['roomCreation']?'on':'off'?>"><?=$settings['roomCreation']?'Enabled':'Disabled'?></span></div>
  <div class="info-row"><span>Max File Size</span><span class="val"><?=$settings['maxFileSize']??50?>MB</span></div>
  <div class="info-row"><span>PHP Version</span><span class="val"><?=PHP_VERSION?></span></div>
  <div class="info-row"><span>Server Time</span><span class="val"><?=date('Y-m-d H:i T')?></span></div>
</div>

<h2>Quick Access</h2>
<div class="links">
  <a href="users.php"><span class="icon">👥</span>User Management</a>
  <a href="rooms.php"><span class="icon">💬</span>Room Management</a>
  <a href="settings.php"><span class="icon">⚙️</span>System Settings</a>
  <a href="logs.php"><span class="icon">📋</span>Activity Logs</a>
  <a href="settings.php#security"><span class="icon">🔒</span>Security</a>
  <a href="settings.php#password"><span class="icon">🔑</span>Change Password</a>
</div>

<h2>Recent Activity</h2>
<div class="card" id="logBox" style="max-height:300px;overflow-y:auto;font-size:13px;color:var(--text2)">
  <p>Loading logs…</p>
</div>

</div>
<script>
// Load recent logs
fetch('../api/admin.php?action=logs&date=<?=date('Y-m-d')?>')
.then(r=>r.json()).then(d=>{
  const box=document.getElementById('logBox');
  if(!d.logs||!d.logs.length){box.innerHTML='<p style="color:var(--text3)">No logs today</p>';return;}
  box.innerHTML=d.logs.slice(-30).reverse().map(l=>{
    const t=new Date(l.time*1000).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    return `<div style="padding:6px 0;border-bottom:1px solid var(--border)">[${t}] <b>${l.user||'system'}</b> — ${l.action} ${l.detail||''}</div>`;
  }).join('');
}).catch(()=>{document.getElementById('logBox').innerHTML='<p>Could not load logs</p>';});
</script>
</body>
</html>
