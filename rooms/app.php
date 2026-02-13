<?php
require_once __DIR__ . '/api/utils.php';
startSession();
$user = getUser();
if (!$user) { header('Location: index.html'); exit; }
$safe = $user; unset($safe['password']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><title>ROOMs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
<meta name="theme-color" content="#111B21"/>
<link rel="icon" type="image/png" href="favicon32x32.png"/>
<style>
:root{--green:#25D366;--teal:#128C7E;--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--bg4:#2A3942;--text:#E9EDEF;--text2:#8696A0;--text3:#667781;--border:rgba(134,150,160,.15);--err:#E74C3C;--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text);overflow:hidden}
#app{height:100%;display:flex;flex-direction:column}
.header{flex-shrink:0;height:56px;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;gap:12px;z-index:100}
.header-title{font-size:20px;font-weight:700;color:var(--green);flex:1}
.hbtn{background:none;border:none;color:var(--text2);cursor:pointer;padding:8px;border-radius:50%;display:flex;align-items:center;transition:background .2s}
.hbtn:hover{background:var(--bg3)}
.tabs{flex-shrink:0;display:flex;background:var(--bg2);border-bottom:1px solid var(--border);padding:0 12px;overflow-x:auto}
.tab{padding:10px 16px;font-size:13px;font-weight:600;color:var(--text3);border:none;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap;background:none;transition:all .2s}
.tab.active{color:var(--green);border-bottom-color:var(--green)}
.sbar{flex-shrink:0;padding:8px 12px;background:var(--bg2);display:none;position:relative}
.sbar.open{display:block}
.sbar input{width:100%;padding:10px 14px 10px 36px;background:var(--bg3);border:none;border-radius:8px;color:var(--text);font-size:14px;outline:none}
.sbar svg{position:absolute;left:24px;top:50%;transform:translateY(-50%);color:var(--text3)}
.chatlist{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch}
.ci{display:flex;align-items:center;padding:12px 16px;gap:12px;cursor:pointer;border-bottom:1px solid rgba(134,150,160,.08);transition:background .15s;text-decoration:none;color:var(--text)}
.ci:hover,.ci:active{background:var(--bg3)}
.cav{width:50px;height:50px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#fff;overflow:hidden}
.cav img{width:100%;height:100%;object-fit:cover}
.cinfo{flex:1;min-width:0}
.cname{font-size:16px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.clast{font-size:13px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.cmeta{text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.ctime{font-size:12px;color:var(--text3)}
.ctype{font-size:10px;color:var(--text3);background:var(--bg4);padding:2px 6px;border-radius:4px}
.empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px;text-align:center;color:var(--text3)}
.empty h3{color:var(--text2);font-size:18px}
.empty p{font-size:14px;line-height:1.5}
.fab{position:fixed;bottom:20px;right:20px;width:56px;height:56px;border-radius:16px;background:var(--green);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);z-index:50;transition:transform .2s}
.fab:hover{transform:scale(1.05)}
.fabm{position:fixed;bottom:84px;right:20px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:50;display:none;min-width:220px}
.fabm.open{display:block;animation:fu .2s}
@keyframes fu{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.fmi{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;font-size:15px;color:var(--text);transition:background .15s;border:none;background:none;width:100%;text-align:left}
.fmi:hover{background:var(--bg3)}
.fmi svg{color:var(--text2);flex-shrink:0}
.pmenu{position:fixed;top:56px;right:8px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:150;display:none;min-width:200px}
.pmenu.open{display:block;animation:fu .2s}
.pmi{display:flex;align-items:center;gap:10px;padding:12px 16px;cursor:pointer;font-size:14px;color:var(--text);transition:background .15s;border:none;background:none;width:100%;text-align:left}
.pmi:hover{background:var(--bg3)}
.pmi.danger{color:var(--err)}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;display:none;align-items:center;justify-content:center;padding:20px}
.mo.open{display:flex}
.mdl{background:var(--bg2);border-radius:16px;width:100%;max-width:420px;max-height:80vh;overflow-y:auto;box-shadow:0 12px 48px rgba(0,0,0,.5)}
.mdl-h{padding:20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.mdl-h h3{font-size:18px;font-weight:600}
.mdl-x{background:none;border:none;color:var(--text2);font-size:22px;cursor:pointer;padding:4px}
.mdl-b{padding:20px}
.fg{margin-bottom:14px}
.fg label{display:block;font-size:13px;color:var(--text2);margin-bottom:6px}
.fg input,.fg textarea,.fg select{width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;font-family:inherit}
.fg textarea{resize:vertical;min-height:60px}
.btnx{width:100%;padding:12px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s;margin-top:8px;background:var(--green);color:#fff}
.btnx:hover{background:var(--teal)}
.ur{display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;cursor:pointer;transition:background .15s}
.ur:hover{background:var(--bg3)}
.ur .av{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;flex-shrink:0}
.ur .inf{flex:1;min-width:0}
.ur .nm{font-size:14px;font-weight:500}
.ur .un{font-size:12px;color:var(--text3)}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--bg4);border-radius:3px}
</style>
</head>
<body>
<div id="app">
<div class="header">
  <div class="header-title">ROOMs</div>
  <button class="hbtn" id="searchToggle"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></button>
  <button class="hbtn" id="menuBtn"><svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button>
</div>
<div class="tabs">
  <button class="tab active" data-filter="all">All</button>
  <button class="tab" data-filter="p2p">Direct</button>
  <button class="tab" data-filter="room">Rooms</button>
</div>
<div class="sbar" id="searchBar"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><input type="text" id="searchInput" placeholder="Search chats, rooms, users..."/><button onclick="document.getElementById('searchBar').classList.remove('open');document.getElementById('searchInput').value='';render()" style="background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer;padding:4px 8px;position:absolute;right:16px;top:50%;transform:translateY(-50%)">&#10005;</button></div>
<div class="chatlist" id="chatList"></div>
<button class="fab" id="fabBtn"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
<div class="fabm" id="fabMenu">
  <button class="fmi" id="newP2PBtn"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>New Message</button>
  <button class="fmi" id="createRoomBtn"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>Create Room</button>
  <button class="fmi" id="joinRoomBtn"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>Join Room</button>
  <button class="fmi" id="browseBtn"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Find Room</button>
</div>
<div class="pmenu" id="profileMenu">
  <button class="pmi" id="profileBtn"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile</button>
  <button class="pmi danger" id="logoutBtn"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>Logout</button>
</div>
</div>
<div class="mo" id="createRoomModal"><div class="mdl"><div class="mdl-h"><h3>Create Room</h3><button class="mdl-x" onclick="closeM('createRoomModal')">&#10005;</button></div><div class="mdl-b"><div class="fg"><label>Room Name</label><input id="crName" type="text" placeholder="e.g. Team Alpha" maxlength="50"/></div><div class="fg"><label>Description</label><textarea id="crDesc" placeholder="Optional..." maxlength="200"></textarea></div><div class="fg"><label>Type</label><select id="crType" onchange="document.getElementById('crHandleGroup').style.display=this.value==='public'?'block':'none'"><option value="public">Public</option><option value="private">Private</option></select></div><div class="fg" id="crHandleGroup"><label>Handle (for search, like @username)</label><input id="crHandle" type="text" placeholder="e.g. teamalpha" maxlength="30"/></div><div class="fg"><label>Room Avatar</label><input id="crAvatar" type="file" accept="image/*" style="padding:8px"/></div><button class="btnx" id="crSubmit">Create Room</button></div></div></div>
<div class="mo" id="newP2PModal"><div class="mdl"><div class="mdl-h"><h3>New Message</h3><button class="mdl-x" onclick="closeM('newP2PModal')">&#10005;</button></div><div class="mdl-b"><div class="fg"><label>Search Users</label><input id="p2pSearch" type="text" placeholder="Type a username..."/></div><div id="p2pResults"></div></div></div></div>
<div class="mo" id="joinRoomModal"><div class="mdl"><div class="mdl-h"><h3>Join Room</h3><button class="mdl-x" onclick="closeM('joinRoomModal')">&#10005;</button></div><div class="mdl-b"><div class="fg"><label>Invite Code</label><input id="joinCode" type="text" placeholder="Enter invite code..."/></div><button class="btnx" id="joinSubmit">Join</button></div></div></div>
<div class="mo" id="browseModal"><div class="mdl"><div class="mdl-h"><h3>Find Room</h3><button class="mdl-x" onclick="closeM('browseModal')">&#10005;</button></div><div class="mdl-b"><div class="fg"><label>Search by handle (e.g. @teamalpha)</label><input id="browseSearch" type="text" placeholder="@handle or room name…"/></div><div id="browseResults"><p style="color:var(--text3);font-size:13px;padding:8px">Type a room handle to search</p></div></div></div></div>
<script>
const ME=<?php echo json_encode($safe);?>;const API='api/';let filter='all';let chats=[];
function sc(s){let h=0;for(let i=0;i<s.length;i++)h=s.charCodeAt(i)+((h<<5)-h);return'hsl('+Math.abs(h)%360+',55%,55%)'}
function ta(ts){const d=Date.now()/1000-ts;if(d<60)return'now';if(d<3600)return Math.floor(d/60)+'m';if(d<86400)return Math.floor(d/3600)+'h';if(d<604800)return Math.floor(d/86400)+'d';return new Date(ts*1000).toLocaleDateString([],{month:'short',day:'numeric'})}
function ini(s){return(s||'??').substring(0,2).toUpperCase()}
async function load(){try{
const[rr,pr]=await Promise.all([fetch(API+'rooms.php?action=list&type=my').then(r=>r.json()),fetch(API+'contacts.php?action=listP2P').then(r=>r.json())]);
const rooms=(rr.rooms||[]).map(r=>({id:r.id,type:'room',name:r.name,avatar:r.avatar,lastMsg:r.lastMessage,la:r.lastActivity||r.created_at,mc:r.memberCount,rt:r.type}));
const p2ps=(pr.chats||[]).map(c=>({id:c.chatId,type:'p2p',name:c.user.displayName,avatar:c.user.avatar,uname:c.user.username,uid:c.user.id,lastMsg:c.lastMessage,la:c.lastActivity||0}));
chats=[...rooms,...p2ps].sort((a,b)=>(b.la||0)-(a.la||0));render()}catch(e){console.error(e)}}
function render(){
const el=document.getElementById('chatList');const q=document.getElementById('searchInput').value.toLowerCase();
let f=chats;if(filter==='p2p')f=f.filter(c=>c.type==='p2p');if(filter==='room')f=f.filter(c=>c.type==='room');
if(q)f=f.filter(c=>(c.name||'').toLowerCase().includes(q)||(c.uname||'').includes(q));
if(!f.length){el.innerHTML='<div class="empty"><svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg><h3>No conversations yet</h3><p>Tap + to start chatting</p></div>';return}
el.innerHTML=f.map(c=>{
const av=c.avatar?'<img src="'+c.avatar+'"/>':'<span style="background:'+sc(c.name)+';width:100%;height:100%;display:flex;align-items:center;justify-content:center">'+ini(c.name)+'</span>';
const lt=c.lastMsg?(c.lastMsg.type==='image'?'📷 Photo':c.lastMsg.text||''):'No messages yet';
const tm=c.lastMsg?ta(c.lastMsg.timestamp):(c.la?ta(c.la):'');
const tb=c.type==='room'?'<span class="ctype">'+c.rt+'</span>':'';
const hr=c.type==='room'?'chat.php?type=room&id='+c.id:'chat.php?type=p2p&id='+c.id;
return'<a class="ci" href="'+hr+'"><div class="cav">'+av+'</div><div class="cinfo"><div class="cname">'+c.name+'</div><div class="clast">'+lt+'</div></div><div class="cmeta"><span class="ctime">'+tm+'</span>'+tb+'</div></a>'}).join('')}
document.querySelectorAll('.tab').forEach(t=>t.onclick=function(){document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));this.classList.add('active');filter=this.dataset.filter;render()});
document.getElementById('searchToggle').onclick=()=>{const s=document.getElementById('searchBar');s.classList.toggle('open');if(s.classList.contains('open'))document.getElementById('searchInput').focus()};
document.getElementById('searchInput').oninput=render;
let fo=false,mo=false;
document.getElementById('fabBtn').onclick=()=>{fo=!fo;document.getElementById('fabMenu').classList.toggle('open',fo)};
document.getElementById('menuBtn').onclick=()=>{mo=!mo;document.getElementById('profileMenu').classList.toggle('open',mo)};
document.addEventListener('click',e=>{if(!e.target.closest('#fabBtn')&&!e.target.closest('#fabMenu')){fo=false;document.getElementById('fabMenu').classList.remove('open')}if(!e.target.closest('#menuBtn')&&!e.target.closest('#profileMenu')){mo=false;document.getElementById('profileMenu').classList.remove('open')}});
function openM(id){document.getElementById(id).classList.add('open');fo=false;document.getElementById('fabMenu').classList.remove('open')}
function closeM(id){document.getElementById(id).classList.remove('open')}
window.closeM=closeM;
document.querySelectorAll('.mo').forEach(m=>m.onclick=e=>{if(e.target===m)closeM(m.id)});
document.getElementById('createRoomBtn').onclick=()=>openM('createRoomModal');
document.getElementById('crSubmit').onclick=async()=>{const n=document.getElementById('crName').value.trim();if(!n)return;const fd=new FormData();fd.append('action','create');fd.append('name',n);fd.append('description',document.getElementById('crDesc').value.trim());fd.append('type',document.getElementById('crType').value);const h=document.getElementById('crHandle').value.trim();if(h)fd.append('handle',h);const av=document.getElementById('crAvatar').files[0];if(av)fd.append('avatar',av);const r=await fetch(API+'rooms.php',{method:'POST',body:fd});const d=await r.json();if(d.error){alert(d.error);return}closeM('createRoomModal');location.href='chat.php?type=room&id='+d.room.id};
document.getElementById('newP2PBtn').onclick=()=>openM('newP2PModal');
let pt;document.getElementById('p2pSearch').oninput=function(){clearTimeout(pt);const q=this.value.trim().toLowerCase();if(q.length<3){document.getElementById('p2pResults').innerHTML='<p style="color:var(--text3);font-size:13px;padding:12px">Enter exact username (min 3 chars)</p>';return}pt=setTimeout(async()=>{const r=await fetch(API+'contacts.php?action=search&q='+encodeURIComponent(q));const d=await r.json();document.getElementById('p2pResults').innerHTML=(d.users||[]).map(u=>'<div class="ur" onclick="startP2P(\''+u.id+'\')"><div class="av" style="background:'+sc(u.username)+'">'+ini(u.displayName)+'</div><div class="inf"><div class="nm">'+u.displayName+'</div><div class="un">@'+u.username+'</div></div></div>').join('')||'<p style="color:var(--text3);font-size:14px;padding:12px">User not found. Enter exact username.</p>'},400)};
window.startP2P=async function(uid){const fd=new FormData();fd.append('action','startP2P');fd.append('userId',uid);const r=await fetch(API+'contacts.php',{method:'POST',body:fd});const d=await r.json();if(d.error){alert(d.error);return}closeM('newP2PModal');location.href='chat.php?type=p2p&id='+d.chatId};
document.getElementById('joinRoomBtn').onclick=()=>openM('joinRoomModal');
document.getElementById('joinSubmit').onclick=async()=>{const c=document.getElementById('joinCode').value.trim();if(!c)return;const fd=new FormData();fd.append('action','joinByCode');fd.append('code',c);const r=await fetch(API+'rooms.php',{method:'POST',body:fd});const d=await r.json();if(d.error){alert(d.error);return}closeM('joinRoomModal');location.href='chat.php?type=room&id='+d.roomId};
document.getElementById('browseBtn').onclick=()=>{openM('browseModal')};
let bt;document.getElementById('browseSearch').oninput=function(){clearTimeout(bt);const q=this.value.trim().replace(/^@/,'');if(q.length<2){document.getElementById('browseResults').innerHTML='<p style="color:var(--text3);font-size:13px;padding:8px">Type a room handle to search</p>';return}bt=setTimeout(async()=>{const r=await fetch(API+'rooms.php?action=search&q='+encodeURIComponent(q));const d=await r.json();rBR(d.rooms||[])},300)};
function rBR(rooms){document.getElementById('browseResults').innerHTML=rooms.map(r=>'<div class="ur" onclick="'+(r.isMember?"location.href=\'chat.php?type=room&id="+r.id+"\'":"jR(\'"+r.id+"\')")+'"><div class="av" style="'+(r.avatar?'background:url('+r.avatar+') center/cover':'background:'+sc(r.name))+'">'+(!r.avatar?ini(r.name):'')+'</div><div class="inf"><div class="nm">'+r.name+(r.handle?' <span style="color:var(--text3);font-size:12px">@'+r.handle+'</span>':'')+'</div><div class="un">'+r.memberCount+' members</div></div><span style="font-size:12px;color:'+(r.isMember?'var(--green)':'var(--text3)')+'">'+( r.isMember?'Joined':'Join')+'</span></div>').join('')||'<p style="color:var(--text3);font-size:14px;padding:12px">No rooms found</p>'}
window.jR=async function(rid){const fd=new FormData();fd.append('action','join');fd.append('roomId',rid);const r=await fetch(API+'rooms.php',{method:'POST',body:fd});const d=await r.json();if(d.error){alert(d.error);return}closeM('browseModal');location.href='chat.php?type=room&id='+rid};
document.getElementById('logoutBtn').onclick=async()=>{await fetch(API+'auth.php?action=logout');location.href='index.html'};
document.getElementById('profileBtn').onclick=()=>location.href='settings.php';
load();setInterval(load,5000);
</script>
</body>
</html>
