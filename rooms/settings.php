<?php
require_once __DIR__ . '/api/utils.php';
startSession();
$user = getUser();
if (!$user) { header('Location: index.html'); exit; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><title>Settings — ROOMs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1A2028;--text:#E9EDEF;--text2:#8696A0;--green:#25D366;--accent:#EA2D3F;--border:rgba(134,150,160,.15)}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif}
html,body{background:var(--bg);color:var(--text);min-height:100%}
header{height:56px;display:flex;align-items:center;padding:0 12px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10}
header button{background:none;border:none;color:var(--text);padding:8px;cursor:pointer}
header h2{flex:1;font-size:17px;margin-left:4px}
.container{max-width:480px;margin:0 auto;padding:16px}
.section{background:var(--bg2);border-radius:12px;margin-bottom:16px;overflow:hidden}
.sec-title{font-size:13px;color:var(--text2);padding:14px 16px 8px;text-transform:uppercase}
.profile-card{display:flex;flex-direction:column;align-items:center;padding:24px 16px}
.av-wrap{position:relative;cursor:pointer;margin-bottom:12px}
.av-wrap:hover::after{content:'📷';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);border-radius:50%;font-size:28px}
.av{width:90px;height:90px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;background-size:cover;background-position:center}
.field{padding:10px 16px;border-bottom:1px solid var(--border)}
.field:last-child{border-bottom:none}
.field label{display:block;font-size:12px;color:var(--text2);margin-bottom:4px}
.field input,.field textarea{width:100%;background:none;border:none;color:var(--text);font-size:15px;outline:none;font-family:inherit;padding:4px 0}
.field textarea{resize:none;min-height:40px}
.field .helper{font-size:12px;color:var(--text2);margin-top:4px}
.toggle-item{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border)}
.toggle-item:last-child{border-bottom:none}
.toggle-item label{font-size:15px;flex:1}
.toggle-item small{font-size:12px;color:var(--text2);display:block;margin-top:2px}
.switch{position:relative;width:44px;height:24px;flex-shrink:0}
.switch input{display:none}
.switch span{position:absolute;inset:0;background:#3B4A54;border-radius:12px;cursor:pointer;transition:.3s}
.switch span::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.3s}
.switch input:checked+span{background:var(--green)}
.switch input:checked+span::before{left:23px}
.btn{width:100%;padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:4px}
.btn-save{background:var(--green);color:#fff}
.btn-save:hover{background:#1FAE53}
.btn-danger{background:rgba(234,45,63,.1);color:var(--accent)}
.btn-danger:hover{background:rgba(234,45,63,.2)}
.msg{padding:10px 16px;font-size:13px;border-radius:8px;margin-bottom:8px;display:none}
.msg.ok{display:block;background:rgba(37,211,102,.1);color:var(--green)}
.msg.err{display:block;background:rgba(234,45,63,.1);color:var(--accent)}
</style>
</head>
<body>
<header>
<button onclick="location.href='app.php'"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
<h2>Settings</h2>
<button onclick="saveAll()" style="color:var(--green);font-size:14px;font-weight:600">Save</button>
</header>
<div class="container">
<div id="feedback" class="msg"></div>

<!-- Profile -->
<div class="section">
<div class="profile-card">
<div class="av-wrap" onclick="document.getElementById('avInput').click()">
<div class="av" id="myAv" style="<?=$user['profile']['avatar']?"background-image:url('{$user['profile']['avatar']}')":"background:hsl(".(abs(crc32($user['username']))%360).",50%,40%)"?>"><?=$user['profile']['avatar']?'':strtoupper(substr($user['displayName'],0,2))?></div>
</div>
<input type="file" id="avInput" accept="image/*" style="display:none" onchange="previewAv(this)"/>
</div>
<div class="field"><label>Display Name</label><input id="fName" value="<?=htmlspecialchars($user['displayName'])?>" maxlength="50"/></div>
<div class="field"><label>Username</label><input id="fUsername" value="<?=htmlspecialchars($user['username'])?>" maxlength="20"/><div class="helper">Can change once per day. Only lowercase letters, numbers, underscore.</div></div>
<div class="field"><label>Bio</label><textarea id="fBio" maxlength="200"><?=htmlspecialchars($user['profile']['bio']??'')?></textarea></div>
</div>

<!-- Privacy -->
<div class="sec-title">Privacy</div>
<div class="section">
<div class="toggle-item">
<label>Last Seen & Online<small>If off, you also can't see others' status</small></label>
<div class="switch"><input type="checkbox" id="pLastSeen" <?=($user['privacy']['lastSeen']??'everyone')==='everyone'?'checked':''?>/><span onclick="this.previousElementSibling.click()"></span></div>
</div>
<div class="toggle-item">
<label>Read Receipts<small>If off, you also can't see if others read your messages</small></label>
<div class="switch"><input type="checkbox" id="pReadReceipts" <?=($user['privacy']['readReceipts']??true)?'checked':''?>/><span onclick="this.previousElementSibling.click()"></span></div>
</div>
<div class="toggle-item">
<label>Searchable by Username<small>If off, others can't find you by searching your username</small></label>
<div class="switch"><input type="checkbox" id="pSearchable" <?=($user['privacy']['searchable']??true)?'checked':''?>/><span onclick="this.previousElementSibling.click()"></span></div>
</div>
</div>

<!-- Password -->
<div class="sec-title">Change Password</div>
<div class="section">
<div class="field"><label>Current Password</label><input id="fCurPw" type="password"/></div>
<div class="field"><label>New Password</label><input id="fNewPw" type="password" placeholder="Min 8 characters"/></div>
<div style="padding:12px 16px"><button class="btn btn-save" onclick="changePw()" style="font-size:14px">Change Password</button></div>
</div>

<!-- Logout -->
<div class="section">
<div style="padding:14px 16px">
<button class="btn btn-danger" onclick="doLogout()">Log Out</button>
</div>
</div>
</div>

<script>
const API='api/';
function show(msg,ok){const el=document.getElementById('feedback');el.textContent=msg;el.className='msg '+(ok?'ok':'err');setTimeout(()=>el.className='msg',3000)}

function previewAv(input){
    const f=input.files[0];if(!f)return;
    const r=new FileReader();r.onload=e=>{const av=document.getElementById('myAv');av.style.backgroundImage=`url('${e.target.result}')`;av.textContent='';};r.readAsDataURL(f);
}

async function saveAll(){
    const fd=new FormData();
    fd.append('action','updateProfile');
    fd.append('displayName',document.getElementById('fName').value.trim());
    fd.append('bio',document.getElementById('fBio').value.trim());
    const avFile=document.getElementById('avInput').files[0];
    if(avFile)fd.append('avatar',avFile);
    const r=await fetch(API+'auth.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.error){show(d.error,false);return}

    // Username change
    const newUsername=document.getElementById('fUsername').value.trim().toLowerCase().replace(/[^a-z0-9_]/g,'');
    if(newUsername&&newUsername!=='<?=$user['username']?>'){
        const fd2=new FormData();fd2.append('action','changeUsername');fd2.append('username',newUsername);
        const r2=await fetch(API+'auth.php',{method:'POST',body:fd2});
        const d2=await r2.json();
        if(d2.error){show(d2.error,false);return}
    }

    // Privacy
    const privacy={lastSeen:document.getElementById('pLastSeen').checked?'everyone':'nobody',
        readReceipts:document.getElementById('pReadReceipts').checked,
        searchable:document.getElementById('pSearchable').checked};
    await fetch(API+'auth.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'updateSettings',privacy})});

    show('Settings saved!',true);
}

async function changePw(){
    const cur=document.getElementById('fCurPw').value;
    const nw=document.getElementById('fNewPw').value;
    if(!cur||!nw){show('Fill both fields',false);return}
    if(nw.length<8){show('Min 8 characters',false);return}
    const fd=new FormData();fd.append('action','changePassword');fd.append('currentPassword',cur);fd.append('newPassword',nw);
    const r=await fetch(API+'auth.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.error){show(d.error,false);return}
    show('Password changed!',true);
    document.getElementById('fCurPw').value='';document.getElementById('fNewPw').value='';
}

async function doLogout(){
    if(!confirm('Log out?'))return;
    await fetch(API+'auth.php?action=logout');
    location.href='index.html';
}
</script>
</body>
</html>
