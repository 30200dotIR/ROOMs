<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Admin – Settings</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--bg4:#2A3942;--green:#25D366;--teal:#128C7E;--red:#E74C3C;--text:#E9EDEF;--text2:#8696A0;--text3:#667781;--border:rgba(134,150,160,.15);--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text)}
.top{background:var(--bg2);border-bottom:1px solid var(--border);padding:16px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10}
.top a{color:var(--text2);text-decoration:none;font-size:22px}
.top h1{font-size:18px;font-weight:600;flex:1}
.wrap{max-width:600px;margin:0 auto;padding:20px}
.section{background:var(--bg2);border:1px solid var(--border);border-radius:12px;margin-bottom:16px;overflow:hidden}
.section-title{padding:16px 20px;font-size:14px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.row{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(134,150,160,.06)}
.row:last-child{border-bottom:none}
.row-label{font-size:14px;flex:1}
.row-label small{display:block;color:var(--text3);font-size:12px;margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle span{position:absolute;inset:0;background:var(--bg4);border-radius:12px;cursor:pointer;transition:background .2s}
.toggle span::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:transform .2s}
.toggle input:checked+span{background:var(--green)}
.toggle input:checked+span::before{transform:translateX(20px)}
.num{width:80px;padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:14px;text-align:center;outline:none}
.num:focus{border-color:var(--green)}
.save-wrap{padding:20px 0}
.save{width:100%;padding:14px;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer;background:var(--green);color:#000;transition:background .2s}
.save:hover{background:var(--teal)}
.save:disabled{opacity:.5;cursor:not-allowed}
.pw-section{margin-top:8px}
.pw-row{padding:14px 20px}
.pw-input{width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;margin-top:6px}
.pw-input:focus{border-color:var(--green)}
.pw-btn{margin-top:12px;padding:10px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;background:var(--bg4);color:var(--text)}
.pw-btn:hover{background:var(--teal);color:#fff}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg2);color:var(--text);padding:10px 20px;border-radius:10px;border:1px solid var(--border);font-size:14px;opacity:0;transition:opacity .3s;z-index:999;pointer-events:none}
.toast.show{opacity:1}
</style>
</head>
<body>
<nav style="background:#111B21;border-bottom:1px solid rgba(134,150,160,.15);padding:0 16px;display:flex;align-items:center;height:56px;position:sticky;top:0;z-index:100"><div style="font-size:18px;font-weight:700;color:#25D366;flex:1;display:flex;align-items:center;gap:8px"><img src="../icon.png" style="width:28px;height:28px;border-radius:6px"/> ROOMs Admin</div><a href="index.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Dashboard</a><a href="users.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Users</a><a href="rooms.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Rooms</a><a href="settings.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Settings</a><a href="logs.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logs</a><a href="login.php?logout=1" style="color:#E74C3C;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logout</a></nav>
<div class="top">
  <a href="index.php">←</a>
  <h1>System Settings</h1>
</div>
<div class="wrap">
  <div class="section">
    <div class="section-title">General</div>
    <div class="row"><div class="row-label">Allow Registration<small>Let new users sign up</small></div><label class="toggle"><input type="checkbox" id="registration" checked/><span></span></label></div>
    <div class="row"><div class="row-label">Allow Room Creation<small>Let users create new rooms</small></div><label class="toggle"><input type="checkbox" id="roomCreation" checked/><span></span></label></div>
    <div class="row"><div class="row-label">Allow P2P Messages<small>Enable direct messaging</small></div><label class="toggle"><input type="checkbox" id="p2pEnabled" checked/><span></span></label></div>
  </div>

  <div class="section">
    <div class="section-title">Limits</div>
    <div class="row"><div class="row-label">Max File Size (MB)<small>Maximum upload file size</small></div><input type="number" class="num" id="maxFileSize" value="10" min="1" max="100"/></div>
    <div class="row"><div class="row-label">Max Room Members<small>Maximum members per room</small></div><input type="number" class="num" id="maxRoomMembers" value="500" min="2" max="10000"/></div>
    <div class="row"><div class="row-label">Max Message Length<small>Characters per message</small></div><input type="number" class="num" id="maxMsgLength" value="5000" min="100" max="50000"/></div>
  </div>

  <div class="section">
    <div class="section-title">Rate Limits (per minute)</div>
    <div class="row"><div class="row-label">Messages/min</div><input type="number" class="num" id="rateMessages" value="30" min="1" max="200"/></div>
    <div class="row"><div class="row-label">API Requests/min</div><input type="number" class="num" id="rateApi" value="60" min="10" max="500"/></div>
  </div>

  <div class="save-wrap"><button class="save" id="saveBtn" onclick="save()">Save Settings</button></div>

  <div class="section pw-section">
    <div class="section-title">Change Admin Password</div>
    <div class="pw-row">
      <label style="font-size:13px;color:var(--text2)">Current Password</label>
      <input type="password" class="pw-input" id="curPw" placeholder="Current password"/>
      <label style="font-size:13px;color:var(--text2);margin-top:10px;display:block">New Password</label>
      <input type="password" class="pw-input" id="newPw" placeholder="New password (min 6 chars)"/>
      <button class="pw-btn" onclick="changePw()">Change Password</button>
    </div>
  </div>
</div>
<div class="toast" id="toast"></div>

<script>
const API='../api/admin.php';
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2000)}

async function load(){
  try{const r=await fetch(API+'?action=getSettings');const d=await r.json();const s=d.settings||{};
  document.getElementById('registration').checked=s.registration!==false;
  document.getElementById('roomCreation').checked=s.roomCreation!==false;
  document.getElementById('p2pEnabled').checked=s.p2pEnabled!==false;
  document.getElementById('maxFileSize').value=s.maxFileSize||10;
  document.getElementById('maxRoomMembers').value=s.maxRoomMembers||500;
  document.getElementById('maxMsgLength').value=s.maxMsgLength||5000;
  document.getElementById('rateMessages').value=(s.rateLimits||{}).messages||30;
  document.getElementById('rateApi').value=(s.rateLimits||{}).api||60;
  }catch(e){console.error(e)}
}

async function save(){
  const fd=new FormData();fd.append('action','updateSettings');
  fd.append('settings',JSON.stringify({
    registration:document.getElementById('registration').checked,
    roomCreation:document.getElementById('roomCreation').checked,
    p2pEnabled:document.getElementById('p2pEnabled').checked,
    maxFileSize:parseInt(document.getElementById('maxFileSize').value)||10,
    maxRoomMembers:parseInt(document.getElementById('maxRoomMembers').value)||500,
    maxMsgLength:parseInt(document.getElementById('maxMsgLength').value)||5000,
    rateLimits:{
      messages:parseInt(document.getElementById('rateMessages').value)||30,
      api:parseInt(document.getElementById('rateApi').value)||60
    }
  }));
  const r=await fetch(API,{method:'POST',body:fd});const d=await r.json();
  if(d.error){alert(d.error);return}toast('Settings saved!')
}

async function changePw(){
  const cur=document.getElementById('curPw').value;
  const nw=document.getElementById('newPw').value;
  if(!cur||!nw){alert('Fill in both fields');return}
  if(nw.length<6){alert('New password must be at least 6 characters');return}
  const fd=new FormData();fd.append('action','changePassword');fd.append('current',cur);fd.append('newPassword',nw);
  const r=await fetch(API,{method:'POST',body:fd});const d=await r.json();
  if(d.error){alert(d.error);return}
  document.getElementById('curPw').value='';document.getElementById('newPw').value='';
  toast('Password changed!')
}
load()
</script>
</body>
</html>
