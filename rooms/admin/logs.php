<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Admin – Logs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--bg4:#2A3942;--green:#25D366;--text:#E9EDEF;--text2:#8696A0;--text3:#667781;--border:rgba(134,150,160,.15);--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text)}
.top{background:var(--bg2);border-bottom:1px solid var(--border);padding:16px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10}
.top a{color:var(--text2);text-decoration:none;font-size:22px}
.top h1{font-size:18px;font-weight:600;flex:1}
.controls{padding:12px 20px;background:var(--bg2);display:flex;gap:10px;align-items:center;border-bottom:1px solid var(--border)}
.controls input[type=date]{padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none}
.controls button{padding:8px 16px;background:var(--green);color:#000;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.logs{padding:12px 20px;font-family:'Courier New',monospace;font-size:12px;line-height:1.8}
.log{padding:6px 10px;border-radius:4px;margin-bottom:2px;color:var(--text2);word-break:break-all}
.log:hover{background:var(--bg3)}
.log .time{color:var(--green)}
.log .action{color:#F39C12;font-weight:600}
.log .user{color:#3498DB}
.empty{text-align:center;padding:60px 20px;color:var(--text3);font-family:var(--font)}
</style>
</head>
<body>
<nav style="background:#111B21;border-bottom:1px solid rgba(134,150,160,.15);padding:0 16px;display:flex;align-items:center;height:56px;position:sticky;top:0;z-index:100"><div style="font-size:18px;font-weight:700;color:#25D366;flex:1;display:flex;align-items:center;gap:8px"><img src="../icon.png" style="width:28px;height:28px;border-radius:6px"/> ROOMs Admin</div><a href="index.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Dashboard</a><a href="users.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Users</a><a href="rooms.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Rooms</a><a href="settings.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Settings</a><a href="logs.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logs</a><a href="login.php?logout=1" style="color:#E74C3C;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logout</a></nav>
<div class="top">
  <a href="index.php">←</a>
  <h1>Activity Logs</h1>
</div>
<div class="controls">
  <input type="date" id="logDate"/>
  <button onclick="load()">Load Logs</button>
</div>
<div class="logs" id="logs"><div class="empty">Select a date and click Load</div></div>

<script>
const API='../api/admin.php';
document.getElementById('logDate').value=new Date().toISOString().split('T')[0];

async function load(){
  const date=document.getElementById('logDate').value;
  if(!date)return;
  try{
    const r=await fetch(API+'?action=logs&date='+date);const d=await r.json();
    const logs=d.logs||[];
    if(!logs.length){document.getElementById('logs').innerHTML='<div class="empty">No logs for this date</div>';return}
    document.getElementById('logs').innerHTML=logs.map(l=>{
      const t=l.time?new Date(l.time*1000).toLocaleTimeString():'';
      return'<div class="log"><span class="time">'+t+'</span> <span class="action">['+( l.action||'unknown')+']</span> <span class="user">'+(l.user||'system')+'</span> '+(l.details||'')+'</div>'
    }).reverse().join('')
  }catch(e){console.error(e);document.getElementById('logs').innerHTML='<div class="empty">Error loading logs</div>'}
}
load()
</script>
</body>
</html>
