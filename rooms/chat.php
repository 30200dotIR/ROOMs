<?php
/* chat.php — Chat UI with WhatsApp-style ticks, reactions, reply, QR */
require_once __DIR__ . '/api/utils.php';
startSession();
if (empty($_SESSION['user_id'])) { header('Location: index.html'); exit; }
$user = findById($_SESSION['user_id']);
if (!$user) { session_destroy(); header('Location: index.html'); exit; }

$chatType = $_GET['type'] ?? 'room';
$chatId = preg_replace('/[^a-z0-9\-]/', '', $_GET['id'] ?? '');
if (!$chatId) { header('Location: app.php'); exit; }

$roomData = null; $chatTitle = ''; $chatAvatar = ''; $memberList = [];
$myRole = 'member'; $isOwner = false; $inviteCode = ''; $roomHandle = ''; $roomDescription = '';

if ($chatType === 'room') {
    $roomData = readJson(DATA_DIR . '/rooms/' . $chatId . '.json');
    if (empty($roomData)) { header('Location: app.php'); exit; }
    $chatTitle = $roomData['name'];
    $chatAvatar = $roomData['avatar'] ?? '';
    $inviteCode = $roomData['inviteCode'] ?? '';
    $roomHandle = $roomData['handle'] ?? '';
    $roomDescription = $roomData['description'] ?? '';
    foreach (($roomData['members'] ?? []) as $mObj) {
        $mid = is_array($mObj) ? ($mObj['userId'] ?? '') : $mObj;
        if (!$mid) continue;
        $mu = findById($mid);
        $role = is_array($mObj) ? ($mObj['role'] ?? 'member') : 'member';
        if ($mid === $user['id']) { $myRole = $role; $isOwner = ($role === 'owner' || ($roomData['owner']??'') === $user['id']); }
        if ($mu) $memberList[] = ['id'=>$mid,'name'=>$mu['displayName'],'username'=>$mu['username'],
            'avatar'=>$mu['profile']['avatar']??'','online'=>(time()-($mu['last_seen']??0))<300,'role'=>$role];
    }
} else {
    $p2pData = readJson(DATA_DIR . '/messages/' . $chatId . '_meta.json');
    if (empty($p2pData)) { header('Location: app.php'); exit; }
    $otherId = null;
    foreach ($p2pData['participants'] ?? [] as $pid) { if ($pid !== $user['id']) { $otherId = $pid; break; } }
    $other = $otherId ? findById($otherId) : null;
    $chatTitle = $other ? $other['displayName'] : 'Chat';
    $chatAvatar = $other ? ($other['profile']['avatar'] ?? '') : '';
}

$isAdmin = $isOwner || in_array($user['id'], $roomData['admins'] ?? []);
$memberListJson = json_encode($memberList, JSON_UNESCAPED_UNICODE);
$baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
$joinLink = $baseUrl.'/join.php?r='.$chatId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><title><?=htmlspecialchars($chatTitle)?> — ROOMs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
<link rel="icon" type="image/png" href="favicon.png"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1A2028;--incoming:#1A2028;--outgoing:#005C4B;--accent:#EA2D3F;
--text:#E9EDEF;--text2:#8696A0;--green:#25D366;--blue:#53BDEB;--border:rgba(134,150,160,.15)}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Roboto,sans-serif}
html,body{height:100%;background:var(--bg);color:var(--text);overflow:hidden;-webkit-tap-highlight-color:transparent}
#app{display:flex;flex-direction:column;height:100%}

/* Header - Material 56px */
header{flex:0 0 56px;display:flex;align-items:center;padding:0 4px;background:var(--bg2);border-bottom:1px solid var(--border);z-index:50}
.hback{background:none;border:none;color:var(--text);width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:50%}
.hav{width:40px;height:40px;border-radius:50%;margin:0 8px;flex-shrink:0;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;cursor:pointer}
.hinfo{flex:1;min-width:0;cursor:pointer;padding:0 4px}
.hinfo h2{font-size:16px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}
.hinfo small{font-size:12px;color:var(--text2);line-height:1.2}
.hmenu{background:none;border:none;color:var(--text);width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:20px}

/* Messages area */
#messages{flex:1;overflow-y:auto;padding:8px 12px 90px;-webkit-overflow-scrolling:touch}
.datesep{text-align:center;margin:16px 0 10px}
.datesep span{background:rgba(17,27,33,.92);color:var(--text2);font-size:12px;padding:5px 14px;border-radius:8px}
.sysmsg{text-align:center;margin:10px 0}
.sysmsg span{background:rgba(17,27,33,.92);color:var(--text2);font-size:13px;padding:6px 14px;border-radius:8px;display:inline-block;max-width:80%}

/* Messages */
.msg{margin:1px 0;display:flex;align-items:flex-end;max-width:82%;position:relative;transition:transform .2s}
.msg.mine{margin-left:auto;flex-direction:row-reverse}
.msg:not(.mine){margin-right:auto}
.msg.first-in-group{margin-top:12px}
/* Material Design: 28px avatar for dense chat */
.msg .mav{width:28px;height:28px;border-radius:50%;flex-shrink:0;margin-right:6px;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;visibility:hidden}
.msg.mine .mav{display:none}
.msg.first-in-group .mav{visibility:visible}

.bubble{padding:6px 10px 4px;border-radius:10px 10px 10px 3px;background:var(--incoming);max-width:100%;min-width:80px;overflow-wrap:break-word;word-break:break-word;hyphens:auto;position:relative}
.msg.mine .bubble{border-radius:10px 10px 3px 10px;background:var(--outgoing)}
.sender{font-size:12px;font-weight:600;margin-bottom:3px;display:none}
.msg.first-in-group:not(.mine) .sender{display:block}
.sender .tag{font-size:10px;font-weight:500;padding:1px 6px;border-radius:4px;margin-left:4px}
.tag.owner{background:rgba(234,45,63,.2);color:#EA2D3F}
.tag.admin{background:rgba(37,211,102,.2);color:#25D366}
.bubble p{margin:0;font-size:14px;line-height:1.45;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word}
.bubble img,.bubble video{max-width:min(260px,70vw);max-height:280px;border-radius:6px;display:block;margin-bottom:4px}
.reply-box{padding:5px 8px;margin-bottom:4px;border-left:3px solid var(--green);border-radius:4px;background:rgba(0,0,0,.15);font-size:12px;cursor:pointer}
.reply-box .rn{color:var(--green);font-weight:600}
.reply-box .rt{color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;display:block}
.meta{display:flex;align-items:center;justify-content:flex-end;gap:3px;margin-top:2px;float:right;margin-left:12px;padding-bottom:1px}
.meta .tm{font-size:11px;color:rgba(255,255,255,.5)}
.meta .ed{font-size:10px;color:rgba(255,255,255,.4);font-style:italic}
.tk{font-size:13px;letter-spacing:-1px;margin-left:2px}
.tk.pending{color:rgba(255,255,255,.3)}
.tk.sent{color:rgba(255,255,255,.5)}
.tk.delivered{color:rgba(255,255,255,.65)}
.tk.read{color:var(--blue)}
.rxrow{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;clear:both}
.rx{padding:2px 8px;border-radius:12px;font-size:14px;cursor:pointer;background:rgba(255,255,255,.06);border:1px solid transparent;display:inline-flex;align-items:center;gap:3px;user-select:none}
.rx.mine{border-color:var(--blue);background:rgba(83,189,235,.1)}
.rx .cnt{font-size:11px;color:var(--text2)}

/* Reply bar */
#replyBar{display:none;padding:8px 14px;background:var(--bg2);border-top:1px solid var(--border);align-items:center;gap:10px}
.rpc{flex:1;border-left:3px solid var(--green);padding-left:10px;font-size:13px;overflow:hidden}
.rpn{color:var(--green);font-weight:600}.rpt{color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rpx{background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer;padding:4px}

/* Upload bar */
#uploadBar{display:none;padding:8px 14px;background:var(--bg2);border-top:1px solid var(--border);align-items:center;gap:10px}
.ub-thumb{width:40px;height:40px;border-radius:8px;background-size:cover;background-position:center;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px;background-color:var(--bg3)}
.ub-info{flex:1}.ub-name{font-size:13px}.ub-bar{height:3px;background:rgba(255,255,255,.1);border-radius:2px;margin-top:4px;overflow:hidden}
.ub-fill{height:100%;background:var(--green);width:0%;transition:width .2s}
.ub-cancel{background:none;border:none;color:var(--text2);cursor:pointer;font-size:18px}

/* Composer */
#composer{position:fixed;bottom:0;left:0;right:0;background:var(--bg2);border-top:1px solid var(--border);display:flex;align-items:flex-end;padding:6px 8px;gap:6px;z-index:40}
#attachBtn{width:44px;height:44px;border:none;background:none;color:var(--text2);font-size:22px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center}
#inputText{flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:22px;font-size:15px;background:var(--bg3);color:var(--text);outline:none;max-height:120px;resize:none;line-height:1.35;font-family:inherit}
#inputText::placeholder{color:var(--text2)}
#sendBtn{width:44px;height:44px;border:none;border-radius:50%;background:var(--green);color:#fff;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;opacity:.4;transition:opacity .2s}
#sendBtn.active{opacity:1}
#filePicker{display:none}
#scrollFab{position:fixed;bottom:72px;right:14px;width:42px;height:42px;border-radius:50%;background:var(--bg2);border:1px solid var(--border);color:var(--text);font-size:18px;display:none;align-items:center;justify-content:center;cursor:pointer;z-index:30;box-shadow:0 2px 8px rgba(0,0,0,.3)}
#toast{position:fixed;bottom:140px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.88);color:#fff;padding:8px 20px;border-radius:20px;font-size:13px;z-index:300;opacity:0;transition:opacity .3s;pointer-events:none}

/* Context menu */
.ctx{position:fixed;background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:6px 0;z-index:200;display:none;min-width:190px;box-shadow:0 6px 24px rgba(0,0,0,.5)}
.ctx .emoji-row{display:flex;padding:10px 14px 6px;gap:10px;border-bottom:1px solid var(--border)}
.ctx .emoji-row span{font-size:26px;cursor:pointer;padding:2px;border-radius:6px;transition:transform .15s}
.ctx .emoji-row span:active{transform:scale(1.3)}
.ctx button{display:block;width:100%;padding:12px 18px;border:none;background:none;color:var(--text);text-align:left;cursor:pointer;font-size:14px}
.ctx button:active{background:rgba(255,255,255,.05)}
.ctx button.danger{color:#EA2D3F}

/* Drawer */
.dov{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;display:none}
.drawer{position:fixed;right:-340px;top:0;bottom:0;width:min(320px,85vw);background:var(--bg2);z-index:101;overflow-y:auto;transition:right .3s ease}
.drawer.open{right:0}
.dh{padding:16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.dh .dx{background:none;border:none;color:var(--text2);font-size:22px;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%}
.dh h3{flex:1;font-size:17px}
.dsec{padding:14px 16px;border-bottom:1px solid var(--border)}
.dsec h4{font-size:12px;color:var(--text2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.link-box{background:var(--bg3);padding:10px 12px;border-radius:8px;font-size:12px;color:var(--green);word-break:break-all;margin-bottom:8px;font-family:'Courier New',monospace;line-height:1.4}
.copy-btn{padding:7px 14px;border:1px solid var(--border);border-radius:8px;background:none;color:var(--text);cursor:pointer;font-size:13px;transition:background .2s}
.copy-btn:active{background:rgba(255,255,255,.08)}
.mi{display:flex;align-items:center;gap:10px;padding:10px 0;position:relative}
.mi .mia{width:40px;height:40px;border-radius:50%;flex-shrink:0;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff}
.mi .dot{position:absolute;left:29px;bottom:10px;width:10px;height:10px;border-radius:50%;border:2px solid var(--bg2)}
.mi .minf{flex:1}.mi .mn{font-size:14px;font-weight:500}.mi .mu{font-size:12px;color:var(--text2)}
.mrole{font-size:10px;padding:2px 6px;border-radius:4px;margin-left:4px}
.mrole.owner{background:rgba(234,45,63,.2);color:#EA2D3F}
.mrole.admin{background:rgba(37,211,102,.2);color:#25D366}
.mact{display:flex;gap:4px;flex-shrink:0}
.mact button{padding:4px 8px;border:1px solid var(--border);border-radius:6px;background:none;color:var(--text2);cursor:pointer;font-size:11px}
.mact button.danger{color:#EA2D3F;border-color:rgba(234,45,63,.3)}
.leave-btn{width:100%;padding:14px;border:none;border-radius:10px;background:rgba(234,45,63,.1);color:#EA2D3F;cursor:pointer;font-size:15px;font-weight:600}
#qrCanvas{margin:8px auto;display:block;border-radius:8px}
</style>
</head>
<body>
<div id="app">
<header>
<button class="hback" onclick="location.href='app.php'"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
<div class="hav" id="headerAv" onclick="openDrawer()"></div>
<div class="hinfo" onclick="openDrawer()"><h2 id="chatTitle"><?=htmlspecialchars($chatTitle)?></h2><small id="chatStatus">…</small></div>
<button class="hmenu" onclick="openDrawer()">⋮</button>
</header>
<main id="messages"></main>
<button id="scrollFab" onclick="scrollBot()">↓</button>
<div id="toast"></div>
<div id="replyBar"><div class="rpc"><div class="rpn" id="rpName"></div><div class="rpt" id="rpText"></div></div><button class="rpx" onclick="cancelReply()">✕</button></div>
<div id="uploadBar"><div class="ub-thumb" id="ubThumb"></div><div class="ub-info"><div class="ub-name" id="ubName"></div><div class="ub-bar"><div class="ub-fill" id="ubFill"></div></div></div><button class="ub-cancel" onclick="cancelUpload()">✕</button></div>
<div id="composer">
<input type="file" id="filePicker" accept="image/*,video/*,.pdf,.doc,.docx,.zip"/>
<button id="attachBtn" onclick="document.getElementById('filePicker').click()">📎</button>
<textarea id="inputText" rows="1" placeholder="Message…"></textarea>
<button id="sendBtn" onclick="doSend()"><svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg></button>
</div>
</div>
<div class="dov" id="dov" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer"></div>
<div class="ctx" id="ctx"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
"use strict";
const API='api/',POLL=1500,chatId='<?=$chatId?>',chatType='<?=$chatType?>';
const ME={id:'<?=$user['id']?>',un:'<?=$user['username']?>',name:'<?=addslashes($user['displayName'])?>',av:'<?=$user['profile']['avatar']??''?>'};
const isAdmin=<?=$isAdmin?'true':'false'?>,isOwner=<?=$isOwner?'true':'false'?>;
const memberList=<?=$memberListJson?>;
const inviteCode='<?=$inviteCode?>',joinLink='<?=addslashes($joinLink)?>',roomHandle='<?=addslashes($roomHandle)?>',roomDesc='<?=addslashes($roomDescription)?>',roomAv='<?=addslashes($chatAvatar)?>';

let lastTs=0,allMsgs=[],replyToId=null,currentXHR=null;
const $=id=>document.getElementById(id),msgEl=$('messages'),inpEl=$('inputText');

/* Helpers */
function sc(s){let h=0;for(let i=0;i<s.length;i++)h=s.charCodeAt(i)+((h<<5)-h);return`hsl(${Math.abs(h)%360},50%,40%)`}
function ini(s){return(s||'?').slice(0,2).toUpperCase()}
function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function linkify(s){return s.replace(/(https?:\/\/[^\s<]+)/g,'<a href="$1" target="_blank" style="color:var(--blue)">$1</a>')}
function fmtTime(ts){return new Date(ts*1000).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}
function fmtDate(ts){const d=new Date(ts*1000),n=new Date(),y=new Date(n);y.setDate(y.getDate()-1);if(d.toDateString()===n.toDateString())return'Today';if(d.toDateString()===y.toDateString())return'Yesterday';return d.toLocaleDateString([],{month:'short',day:'numeric',year:'numeric'})}
function nearBot(){return msgEl.scrollHeight-msgEl.scrollTop-msgEl.clientHeight<200}
function scrollBot(){msgEl.scrollTop=msgEl.scrollHeight}

/* Header avatar */
const hav=$('headerAv');
if(roomAv){hav.style.backgroundImage=`url('${roomAv}')`;hav.textContent='';}
else{hav.style.background=sc('<?=addslashes($chatTitle)?>');hav.textContent=ini('<?=addslashes($chatTitle)?>');}

/* Toast */
let toastT;
function toast(msg){const t=$('toast');t.textContent=msg;t.style.opacity='1';clearTimeout(toastT);toastT=setTimeout(()=>t.style.opacity='0',1500)}

/* Ticks */
function tickH(m){
    if(m.from!==ME.id||m.type==='system')return'';
    if(m._pending)return'<span class="tk pending">⏳</span>';
    const s=m.status||'sent';
    if(s==='read')return'<span class="tk read">✓✓</span>';
    if(s==='delivered')return'<span class="tk delivered">✓✓</span>';
    return'<span class="tk sent">✓</span>';
}

/* Reactions HTML */
function rxH(m){
    const r=m.reactions;if(!r||typeof r!=='object')return'';
    const e=Object.entries(r).filter(([,u])=>Array.isArray(u)&&u.length>0);
    if(!e.length)return'';
    return'<div class="rxrow">'+e.map(([em,u])=>`<span class="rx${u.includes(ME.id)?' mine':''}" data-mid="${m.id}" data-emoji="${esc(em)}">${em}<span class="cnt">${u.length}</span></span>`).join('')+'</div>';
}

/* Render all messages */
function renderAll(){
    let html='',lastDate='',lastUser='',lastTime=0;
    allMsgs.forEach(m=>{
        const d=fmtDate(m.timestamp);
        if(d!==lastDate){html+=`<div class="datesep"><span>${d}</span></div>`;lastDate=d;lastUser='';lastTime=0;}
        if(m.type==='system'){html+=`<div class="sysmsg"><span>${esc(m.text)}</span></div>`;lastUser='';return;}
        if(m.type==='deleted'){html+=`<div class="msg${m.from===ME.id?' mine':''}" data-id="${m.id}"><div class="mav"></div><div class="bubble" style="opacity:.5"><p style="font-style:italic;color:var(--text2)">🚫 This message was deleted</p></div></div>`;lastUser='';return;}
        const isMine=m.from===ME.id;
        const isFirst=m.from!==lastUser||(m.timestamp-lastTime)>120;
        const mb=memberList.find(x=>x.id===m.from);
        const sN=m.fromName||'Unknown';
        const sA=m.fromAvatar||(mb?mb.avatar:'');
        const role=mb?mb.role:'';
        let rTag='';if(role==='owner')rTag='<span class="tag owner">Creator</span>';else if(role==='admin')rTag='<span class="tag admin">Admin</span>';
        html+=`<div class="msg${isMine?' mine':''}${isFirst?' first-in-group':''}" data-id="${m.id}" data-from="${m.from}">`;
        if(!isMine){html+=sA?`<div class="mav" style="background:url('${sA}') center/cover"></div>`:`<div class="mav" style="background:${sc(sN)}">${ini(sN)}</div>`;}
        html+=`<div class="bubble" data-mid="${m.id}">`;
        if(!isMine&&chatType==='room')html+=`<div class="sender" style="color:${sc(sN)}">${esc(sN)}${rTag}</div>`;
        if(m.replyTo){const orig=allMsgs.find(x=>x.id===m.replyTo);if(orig)html+=`<div class="reply-box" data-goto="${m.replyTo}"><div class="rn">${esc(orig.fromName||'')}</div><div class="rt">${esc((orig.text||'').slice(0,60))}</div></div>`;}
        if(m.file){if(m.type==='image')html+=`<img src="${m.file.url}" loading="lazy"/>`;else if(m.type==='video')html+=`<video src="${m.file.url}" controls preload="none"></video>`;else html+=`<p>📄 <a href="${m.file.url}" target="_blank" style="color:var(--green)">${esc(m.file.originalName)}</a></p>`;}
        if(m.text)html+=`<p>${linkify(esc(m.text))}</p>`;
        html+=`<div class="meta">${m.edited?'<span class="ed">edited </span>':''}<span class="tm">${fmtTime(m.timestamp)}</span>${tickH(m)}</div>`;
        html+=rxH(m);
        html+='</div></div>';
        lastUser=m.from;lastTime=m.timestamp;
    });
    const wb=nearBot();
    msgEl.innerHTML=html;
    if(wb)scrollBot();
}

/* Event delegation on messages */
msgEl.addEventListener('click',function(e){
    // Reply-box click -> scroll to original
    const rb=e.target.closest('.reply-box');
    if(rb){const id=rb.dataset.goto;if(id){const el=msgEl.querySelector(`[data-id="${id}"]`);if(el){el.scrollIntoView({behavior:'smooth',block:'center'});el.style.background='rgba(37,211,102,.12)';setTimeout(()=>el.style.background='',2000)}}return;}
    // Reaction click
    const rx=e.target.closest('.rx');
    if(rx){e.stopPropagation();doReact(rx.dataset.mid,rx.dataset.emoji);return;}
    // Bubble tap -> copy text
    const bub=e.target.closest('.bubble');
    if(bub&&!e.target.closest('a')&&!e.target.closest('img')&&!e.target.closest('video')){
        const mid=bub.dataset.mid;const m=allMsgs.find(x=>x.id===mid);
        if(m&&m.text){navigator.clipboard.writeText(m.text).then(()=>toast('Copied!')).catch(()=>{})}
    }
});

/* Long press / right click -> context menu */
let lpTimer=null;
msgEl.addEventListener('contextmenu',function(e){
    const msg=e.target.closest('.msg');if(!msg)return;e.preventDefault();showCtx(e,msg.dataset.id);
});
msgEl.addEventListener('touchstart',function(e){
    const msg=e.target.closest('.msg');if(!msg)return;
    const t=e.touches[0];
    lpTimer=setTimeout(()=>{lpTimer=null;showCtx({clientX:t.clientX,clientY:t.clientY},msg.dataset.id)},600);
},{passive:true});
msgEl.addEventListener('touchmove',function(){if(lpTimer){clearTimeout(lpTimer);lpTimer=null}},{passive:true});
msgEl.addEventListener('touchend',function(){if(lpTimer){clearTimeout(lpTimer);lpTimer=null}},{passive:true});

/* Swipe to reply */
let swSX=0,swEl=null,swiping=false;
msgEl.addEventListener('touchstart',function(e){
    const msg=e.target.closest('.msg');if(!msg)return;swSX=e.touches[0].clientX;swEl=msg;swiping=false;
},{passive:true});
msgEl.addEventListener('touchmove',function(e){
    if(!swEl)return;const dx=swEl.classList.contains('mine')?(swSX-e.touches[0].clientX):(e.touches[0].clientX-swSX);
    if(dx>15){swiping=true;if(lpTimer){clearTimeout(lpTimer);lpTimer=null}
        const tx=Math.min(dx,80);swEl.style.transform=swEl.classList.contains('mine')?`translateX(${-tx}px)`:`translateX(${tx}px)`;swEl.style.transition='none';}
},{passive:true});
msgEl.addEventListener('touchend',function(e){
    if(!swEl)return;
    if(swiping){const dx=swEl.classList.contains('mine')?(swSX-e.changedTouches[0].clientX):(e.changedTouches[0].clientX-swSX);
        swEl.style.transform='';swEl.style.transition='transform .2s';
        if(dx>55)setReply(swEl.dataset.id);}
    swEl=null;swiping=false;
},{passive:true});

/* ===== Context Menu ===== */
function showCtx(e,msgId){
    const m=allMsgs.find(x=>x.id===msgId);if(!m||m.type==='system'||m.type==='deleted')return;
    const menu=$('ctx');
    const emojis=['👍','❤️','😂','😮','😢','🙏'];
    menu.innerHTML='<div class="emoji-row">'+emojis.map(em=>`<span data-action="react" data-mid="${msgId}" data-emoji="${em}">${em}</span>`).join('')+'</div>'+
        `<button data-action="reply" data-mid="${msgId}">↩ Reply</button>`+
        (m.text?`<button data-action="copy" data-mid="${msgId}">📋 Copy text</button>`:'')+
        (m.from===ME.id&&m.type==='text'?`<button data-action="edit" data-mid="${msgId}">✏️ Edit</button>`:'')+
        (m.from===ME.id||isAdmin?`<button data-action="delete" data-mid="${msgId}" class="danger">🗑 Delete</button>`:'')+
        '';
    menu.style.display='block';
    const cx=e.clientX||0,cy=e.clientY||0;
    menu.style.left=Math.min(cx,window.innerWidth-200)+'px';
    menu.style.top=Math.min(cy,window.innerHeight-menu.offsetHeight-10)+'px';
    setTimeout(()=>document.addEventListener('click',hideCtx,{once:true}),50);
}
function hideCtx(){$('ctx').style.display='none'}

/* Context menu delegation */
$('ctx').addEventListener('click',function(e){
    const el=e.target.closest('[data-action]');if(!el)return;
    const act=el.dataset.action,mid=el.dataset.mid;
    hideCtx();
    if(act==='react')doReact(mid,el.dataset.emoji);
    else if(act==='reply')setReply(mid);
    else if(act==='copy'){const m=allMsgs.find(x=>x.id===mid);if(m&&m.text)navigator.clipboard.writeText(m.text).then(()=>toast('Copied!'))}
    else if(act==='edit'){const m=allMsgs.find(x=>x.id===mid);if(m){const t=prompt('Edit message:',m.text);if(t&&t!==m.text){const fd=new FormData();fd.append('action','edit');fd.append('chatId',chatId);fd.append('messageId',mid);fd.append('text',t);fetch(API+'messages.php',{method:'POST',body:fd}).then(()=>poll())}}}
    else if(act==='delete'){if(confirm('Delete this message?')){const fd=new FormData();fd.append('action','delete');fd.append('chatId',chatId);fd.append('messageId',mid);fetch(API+'messages.php',{method:'POST',body:fd}).then(()=>poll())}}
});

/* ===== API Calls ===== */
async function doReact(msgId,emoji){
    const fd=new FormData();fd.append('action','react');fd.append('chatId',chatId);fd.append('messageId',msgId);fd.append('emoji',emoji);
    try{await fetch(API+'messages.php',{method:'POST',body:fd});await poll()}catch(e){console.error(e)}
}

async function markRead(){
    try{await fetch(API+'messages.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=markRead&chatId='+chatId})}catch(e){}
}

async function poll(){
    try{
        const r=await fetch(`${API}messages.php?action=fetch&chatId=${chatId}&since=${lastTs}`);
        const d=await r.json();
        if(d.messages&&d.messages.length){
            d.messages.forEach(nm=>{
                const pi=allMsgs.findIndex(m=>m._pending&&m._tempText===nm.text&&m.from===nm.from);
                if(pi>-1)allMsgs.splice(pi,1);
                const ei=allMsgs.findIndex(m=>m.id===nm.id);
                if(ei>-1)allMsgs[ei]=nm;else allMsgs.push(nm);
            });
            lastTs=Math.max(lastTs,...d.messages.map(m=>m.timestamp));
            renderAll();
        }
        if(d.members){
            const online=d.members.filter(m=>m.online!==false);
            $('chatStatus').textContent=online.length?`${online.length} online`:(chatType==='room'?`${memberList.length} members`:'');
        }
    }catch(e){console.error(e)}
    // Always mark as read when polling (fixes "come back and see blue ticks")
    markRead();
}

/* Initial load + periodic */
poll();
setInterval(poll,POLL);
// Mark read on visibility change (tab switch)
document.addEventListener('visibilitychange',()=>{if(!document.hidden){poll()}});
window.addEventListener('focus',()=>{poll()});

/* Send */
async function doSend(){
    const text=inpEl.value.trim();if(!text)return;
    const temp={id:'t_'+Date.now(),chatId,type:'text',from:ME.id,fromUser:ME.un,fromName:ME.name,fromAvatar:ME.av,
        text,file:null,replyTo:replyToId,timestamp:Math.floor(Date.now()/1000),edited:false,deleted:false,reactions:{},
        status:'sent',deliveredTo:[],readBy:[],_pending:true,_tempText:text};
    allMsgs.push(temp);renderAll();scrollBot();
    inpEl.value='';inpEl.style.height='auto';$('sendBtn').classList.remove('active');
    const rId=replyToId;cancelReply();
    const fd=new FormData();fd.append('action','send');fd.append('chatId',chatId);fd.append('text',text);
    if(rId)fd.append('replyTo',rId);
    try{const r=await fetch(API+'messages.php',{method:'POST',body:fd});const d=await r.json();
        if(d.ok&&d.message){const pi=allMsgs.findIndex(m=>m.id===temp.id);if(pi>-1)allMsgs[pi]=d.message;renderAll()}}catch(e){console.error(e)}
}

/* File upload */
$('filePicker').onchange=function(e){
    const f=e.target.files[0];if(!f)return;
    $('uploadBar').style.display='flex';$('ubName').textContent=f.name;
    if(f.type.startsWith('image/')){const r=new FileReader();r.onload=ev=>$('ubThumb').style.backgroundImage=`url('${ev.target.result}')`;r.readAsDataURL(f);}
    else{$('ubThumb').style.backgroundImage='';$('ubThumb').textContent='📄'}
    const xhr=new XMLHttpRequest();currentXHR=xhr;
    xhr.upload.onprogress=ev=>{if(ev.lengthComputable)$('ubFill').style.width=Math.round(ev.loaded/ev.total*100)+'%'};
    xhr.onload=()=>{$('uploadBar').style.display='none';$('ubFill').style.width='0%';currentXHR=null;poll()};
    xhr.onerror=()=>{$('uploadBar').style.display='none';currentXHR=null};
    const fd=new FormData();fd.append('action','send');fd.append('chatId',chatId);fd.append('file',f);
    xhr.open('POST',API+'messages.php');xhr.send(fd);
    this.value='';
};
function cancelUpload(){if(currentXHR){currentXHR.abort();currentXHR=null}$('uploadBar').style.display='none'}

/* Input */
inpEl.oninput=function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px';$('sendBtn').classList.toggle('active',!!this.value.trim())};
inpEl.onkeydown=function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend()}};

/* Reply */
function setReply(id){const m=allMsgs.find(x=>x.id===id);if(!m)return;replyToId=id;$('rpName').textContent=m.fromName||'';$('rpText').textContent=(m.text||'').slice(0,80);$('replyBar').style.display='flex';inpEl.focus()}
function cancelReply(){replyToId=null;$('replyBar').style.display='none'}

/* Scroll FAB */
msgEl.onscroll=()=>{$('scrollFab').style.display=nearBot()?'none':'flex'};

/* Presence ping */
setInterval(()=>{fetch(`${API}messages.php?action=presence&chatId=${chatId}`)},15000);

/* ===== Drawer ===== */
function openDrawer(){
    const d=$('drawer');$('dov').style.display='block';
    let h=`<div class="dh"><button class="dx" onclick="closeDrawer()">✕</button><h3>${chatType==='room'?'Room Info':'Chat Info'}</h3></div>`;
    if(chatType==='room'){
        h+=`<div class="dsec" style="text-align:center;padding:24px 16px">`;
        if(isAdmin)h+=`<div style="display:inline-block;position:relative;cursor:pointer" onclick="document.getElementById('ravPick').click()">`;
        h+=roomAv?`<div style="width:80px;height:80px;border-radius:50%;background:url('${roomAv}') center/cover;margin:0 auto"></div>`
            :`<div style="width:80px;height:80px;border-radius:50%;background:${sc('<?=addslashes($chatTitle)?>')};display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto">${ini('<?=addslashes($chatTitle)?>')}</div>`;
        if(isAdmin)h+=`</div><input type="file" id="ravPick" accept="image/*" style="display:none" onchange="uploadRoomAv(this)"/>`;
        h+=`<h3 style="margin-top:12px"><?=htmlspecialchars($chatTitle)?></h3>`;
        if(roomHandle)h+=`<div style="color:var(--text2);font-size:13px;margin-top:2px">@${roomHandle}</div>`;
        h+=`<div style="color:var(--text2);font-size:13px;margin-top:4px">${memberList.length} member${memberList.length>1?'s':''}</div>`;
        if(roomDesc)h+=`<p style="color:var(--text2);font-size:13px;margin-top:8px">${esc(roomDesc)}</p>`;
        h+=`</div>`;
        h+=`<div class="dsec"><h4>Room ID</h4><div class="link-box" style="font-size:11px">${chatId}</div><button class="copy-btn" onclick="navigator.clipboard.writeText('${chatId}');toast('ID copied!')">Copy ID</button></div>`;
        h+=`<div class="dsec"><h4>Invite Link</h4><div class="link-box">${joinLink}</div><div style="display:flex;gap:8px;flex-wrap:wrap">`;
        h+=`<button class="copy-btn" onclick="navigator.clipboard.writeText('${joinLink}');toast('Link copied!')">Copy Link</button>`;
        if(inviteCode)h+=`<button class="copy-btn" onclick="navigator.clipboard.writeText('${inviteCode}');toast('Code copied!')">Copy Code</button>`;
        h+=`</div></div>`;
        h+=`<div class="dsec"><h4>QR Code</h4><div id="qrBox" style="text-align:center"></div></div>`;
        h+=`<div class="dsec"><h4>Members (${memberList.length})</h4>`;
        const sorted=[...memberList].sort((a,b)=>a.role==='owner'?-1:b.role==='owner'?1:a.role==='admin'?-1:b.role==='admin'?1:0);
        sorted.forEach(m=>{
            const rb=m.role==='owner'?'<span class="mrole owner">Creator</span>':m.role==='admin'?'<span class="mrole admin">Admin</span>':'';
            h+=`<div class="mi"><div class="mia" style="${m.avatar?`background:url('${m.avatar}') center/cover`:`background:${sc(m.name)}`}">${m.avatar?'':ini(m.name)}</div><div class="dot" style="background:${m.online?'var(--green)':'#555'}"></div><div class="minf"><div class="mn">${esc(m.name)}${rb}</div><div class="mu">@${esc(m.username)}</div></div>`;
            if(isAdmin&&m.id!==ME.id&&m.role!=='owner'){
                h+=`<div class="mact">`;
                if(isOwner&&m.role!=='admin')h+=`<button onclick="promoteUser('${m.id}','admin')">Admin</button>`;
                if(isOwner&&m.role==='admin')h+=`<button onclick="promoteUser('${m.id}','member')">Demote</button>`;
                h+=`<button class="danger" onclick="kickUser('${m.id}')">Kick</button>`;
                h+=`<button class="danger" onclick="banUser('${m.id}')">Ban</button></div>`;
            }
            h+=`</div>`;
        });
        h+=`</div>`;
        if(!isOwner)h+=`<div class="dsec"><button class="leave-btn" onclick="leaveRoom()">Leave Room</button></div>`;
    }
    d.innerHTML=h;
    requestAnimationFrame(()=>{d.classList.add('open');
        try{const qb=document.getElementById('qrBox');if(qb&&typeof QRCode!=='undefined'){new QRCode(qb,{text:joinLink,width:180,height:180,colorDark:'#E9EDEF',colorLight:'#111B21'})}}catch(e){}
    });
}
function closeDrawer(){$('drawer').classList.remove('open');setTimeout(()=>$('dov').style.display='none',300)}

async function uploadRoomAv(i){const f=i.files[0];if(!f)return;const fd=new FormData();fd.append('action','update');fd.append('roomId',chatId);fd.append('avatar',f);await fetch(API+'rooms.php',{method:'POST',body:fd});location.reload()}
async function kickUser(uid){if(!confirm('Kick this user?'))return;const fd=new FormData();fd.append('action','kick');fd.append('roomId',chatId);fd.append('userId',uid);await fetch(API+'rooms.php',{method:'POST',body:fd});location.reload()}
async function banUser(uid){if(!confirm('Ban this user?'))return;const fd=new FormData();fd.append('action','ban');fd.append('roomId',chatId);fd.append('userId',uid);await fetch(API+'rooms.php',{method:'POST',body:fd});location.reload()}
async function promoteUser(uid,role){const fd=new FormData();fd.append('action','promote');fd.append('roomId',chatId);fd.append('userId',uid);fd.append('role',role);await fetch(API+'rooms.php',{method:'POST',body:fd});location.reload()}
async function leaveRoom(){if(!confirm('Leave this room?'))return;const fd=new FormData();fd.append('action','leave');fd.append('roomId',chatId);await fetch(API+'rooms.php',{method:'POST',body:fd});location.href='app.php'}

/* Expose globals */
window.openDrawer=openDrawer;window.closeDrawer=closeDrawer;window.cancelReply=cancelReply;window.cancelUpload=cancelUpload;
window.doSend=doSend;window.scrollBot=scrollBot;window.toast=toast;window.uploadRoomAv=uploadRoomAv;
window.kickUser=kickUser;window.banUser=banUser;window.promoteUser=promoteUser;window.leaveRoom=leaveRoom;
})();
</script>
</body>
</html>
