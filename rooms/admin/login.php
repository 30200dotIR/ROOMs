<?php
session_start();
if(!empty($_GET['logout'])){unset($_SESSION['admin']);session_destroy();session_start();}
if(!empty($_SESSION['admin'])){header('Location: index.php');exit;}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pw=$_POST['password']??'';
    $hashFile=__DIR__.'/../data/admin/.password';
    if(!file_exists($hashFile)){
        $hash=password_hash('admin123',PASSWORD_BCRYPT,['cost'=>12]);
        file_put_contents($hashFile,$hash);
    }
    $stored=trim(file_get_contents($hashFile));
    if(password_verify($pw,$stored)){
        $_SESSION['admin']=true;
        $_SESSION['admin_login_time']=time();
        header('Location: index.php');exit;
    }else{$err='Invalid password';}
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Admin Login — ROOMs</title>
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
<style>
:root{--bg:#0B141A;--bg2:#111B21;--bg3:#1F2C34;--green:#25D366;--teal:#128C7E;--text:#E9EDEF;--text2:#8696A0;--border:rgba(134,150,160,.15);--err:#E74C3C;--font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center}
.card{width:100%;max-width:380px;background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.4)}
.logo{width:64px;height:64px;border-radius:16px;margin:0 auto 16px}
h1{font-size:22px;margin-bottom:6px}
.sub{color:var(--text2);font-size:14px;margin-bottom:28px}
.field{text-align:left;margin-bottom:20px}
.field label{display:block;font-size:13px;color:var(--text2);margin-bottom:6px}
.field input{width:100%;padding:12px 16px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:15px;outline:none;transition:border .2s}
.field input:focus{border-color:var(--green)}
.err{background:rgba(231,76,60,.15);color:var(--err);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
button{width:100%;padding:14px;background:var(--green);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s}
button:hover{background:var(--teal)}
.back{display:inline-block;margin-top:20px;color:var(--text2);font-size:13px;text-decoration:none}
.back:hover{color:var(--text)}
</style>
</head>
<body>
<div class="card">
  <img src="../icon.png" class="logo" alt="ROOMs"/>
  <h1>Admin Panel</h1>
  <p class="sub">Enter admin password to continue</p>
  <?php if($err):?><div class="err"><?=$err?></div><?php endif;?>
  <form method="post">
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" autofocus required placeholder="Enter admin password"/>
    </div>
    <button type="submit">Sign In</button>
  </form>
  <a href="../index.html" class="back">← Back to ROOMs</a>
</div>
</body>
</html>
