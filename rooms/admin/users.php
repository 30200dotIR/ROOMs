<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Admin – Users</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--bg4:#2A3942;--green:#25D366;--teal:#128C7E;--red:#E74C3C;--text:#E9EDEF;--text2:#8696A0;--text3:#667781;--border:rgba(134,150,160,.15);--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text)}
.top{background:var(--bg2);border-bottom:1px solid var(--border);padding:16px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10}
.top a{color:var(--text2);text-decoration:none;font-size:22px}
.top h1{font-size:18px;font-weight:600;flex:1}
.top .cnt{font-size:13px;color:var(--text3);background:var(--bg3);padding:4px 10px;border-radius:12px}
.search{padding:12px 20px;background:var(--bg2);position:sticky;top:56px;z-index:9}
.search input{width:100%;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none}
.search input:focus{border-color:var(--green)}
.list{padding:8px 0}
.user{display:flex;align-items:center;padding:14px 20px;gap:14px;border-bottom:1px solid rgba(134,150,160,.06);transition:background .15s}
.user:hover{background:var(--bg3)}
.av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:16px;flex-shrink:0}
.info{flex:1;min-width:0}
.name{font-size:15px;font-weight:500;display:flex;align-items:center;gap:6px}
.name .badge{font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600}
.badge.admin{background:var(--green);color:#000}
.badge.banned{background:var(--red);color:#fff}
.sub{font-size:12px;color:var(--text3);margin-top:2px}
.acts{display:flex;gap:6px;flex-shrink:0}
.acts button{padding:6px 12px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:opacity .2s}
.acts button:hover{opacity:.8}
.btn-ban{background:var(--red);color:#fff}
.btn-unban{background:var(--green);color:#000}
.btn-del{background:var(--bg4);color:var(--text2)}
.empty{text-align:center;padding:60px 20px;color:var(--text3)}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg2);color:var(--text);padding:10px 20px;border-radius:10px;border:1px solid var(--border);font-size:14px;opacity:0;transition:opacity .3s;z-index:999;pointer-events:none}
.toast.show{opacity:1}
</style>
</head>
<body>
<nav style="background:#111B21;border-bottom:1px solid rgba(134,150,160,.15);padding:0 16px;display:flex;align-items:center;height:56px;position:sticky;top:0;z-index:100"><div style="font-size:18px;font-weight:700;color:#25D366;flex:1;display:flex;align-items:center;gap:8px"><img src="../icon.png" style="width:28px;height:28px;border-radius:6px"/> ROOMs Admin</div><a href="index.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Dashboard</a><a href="users.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Users</a><a href="rooms.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Rooms</a><a href="settings.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Settings</a><a href="logs.php" style="color:#8696A0;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logs</a><a href="login.php?logout=1" style="color:#E74C3C;text-decoration:none;font-size:13px;padding:8px 14px;border-radius:8px">Logout</a></nav>
<div class="top">
  <a href="index.php">←</a>
  <h1>User Management</h1>
  <span class="cnt" id="count">0 users</span>
</div>
<div class="search"><input type="text" id="q" placeholder="Search users…" oninput="render()"/></div>
<div class="list" id="list"></div>
<div class="toast" id="toast"></div>

<script>
const API='../api/admin.php';let users=[];
function sc(s){let h=0;for(let i=0;i<s.length;i++)h=s.charCodeAt(i)+((h<<5)-h);return'hsl('+Math.abs(h)%360+',55%,55%)'}
function ini(s){return(s||'??').substring(0,2).toUpperCase()}
function ago(ts){if(!ts)return'never';const d=Date.now()/1000-ts;if(d<60)return'just now';if(d<3600)return Math.floor(d/60)+'m ago';if(d<86400)return Math.floor(d/3600)+'h ago';return Math.floor(d/86400)+'d ago'}
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2000)}

async function load(){
  try{const r=await fetch(API+'?action=users');const d=await r.json();
  users=d.users||[];document.getElementById('count').textContent=users.length+' users';render()}
  catch(e){console.error(e)}
}

function render(){
  const q=document.getElementById('q').value.toLowerCase();
  let f=users;if(q)f=f.filter(u=>(u.username||'').toLowerCase().includes(q)||(u.displayName||'').toLowerCase().includes(q));
  if(!f.length){document.getElementById('list').innerHTML='<div class="empty">No users found</div>';return}
  document.getElementById('list').innerHTML=f.map(u=>{
    const badges=(u.isAdmin?'<span class="badge admin">ADMIN</span>':'')+(u.isBanned?'<span class="badge banned">BANNED</span>':'');
    const banBtn=u.isBanned
      ?'<button class="btn-unban" onclick="unban(\''+u.id+'\')">Unban</button>'
      :'<button class="btn-ban" onclick="ban(\''+u.id+'\')">Ban</button>';
    return'<div class="user"><div class="av" style="background:'+sc(u.username)+'">'+ini(u.displayName||u.username)+'</div><div class="info"><div class="name">'+(u.displayName||u.username)+' '+badges+'</div><div class="sub">@'+u.username+' · '+u.messagesSent+' msgs · Last seen: '+ago(u.lastSeen)+'</div></div><div class="acts">'+banBtn+'<button class="btn-del" onclick="del(\''+u.id+'\',\''+u.username+'\')">Delete</button></div></div>'
  }).join('')
}

async function ban(id){
  const reason=prompt('Ban reason (optional):');
  const fd=new FormData();fd.append('action','banUser');fd.append('userId',id);fd.append('reason',reason||'');
  const r=await fetch(API,{method:'POST',body:fd});const d=await r.json();
  if(d.error){alert(d.error);return}toast('User banned');load()
}
async function unban(id){
  const fd=new FormData();fd.append('action','unbanUser');fd.append('userId',id);
  const r=await fetch(API,{method:'POST',body:fd});const d=await r.json();
  if(d.error){alert(d.error);return}toast('User unbanned');load()
}
async function del(id,name){
  if(!confirm('Delete user "'+name+'"? This cannot be undone.'))return;
  const fd=new FormData();fd.append('action','deleteUser');fd.append('userId',id);
  const r=await fetch(API,{method:'POST',body:fd});const d=await r.json();
  if(d.error){alert(d.error);return}toast('User deleted');load()
}
load()
</script>
</body>
</html>
