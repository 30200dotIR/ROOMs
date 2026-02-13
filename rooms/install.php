<?php
/* ============================================================================
 * install.php — One-time setup: creates all required directories and files
 * Run this once after uploading files, then DELETE it.
 * ============================================================================ */

$base = __DIR__;
$errors = [];
$created = [];

// All required directories
$dirs = [
    'data',
    'data/users',
    'data/rooms',
    'data/messages',
    'data/sessions',
    'data/contacts',
    'data/reports',
    'data/admin',
    'data/admin/logs',
    'data/rate_limits',
    'uploads',
    'uploads/profiles',
    'uploads/profiles/original',
    'uploads/profiles/thumbnails',
    'uploads/files',
    'uploads/voice',
    'uploads/temp',
    'cache',
    'chats',
    'status',
];

foreach ($dirs as $dir) {
    $full = $base . '/' . $dir;
    if (!is_dir($full)) {
        if (mkdir($full, 0775, true)) {
            $created[] = $dir;
        } else {
            $errors[] = "Failed to create: $dir";
        }
    }
}

// Seed files (create if not exist)
$seeds = [
    'data/users/_index.json' => '{}',
    'data/rooms/_index.json' => '{}',
    'data/contacts/_index.json' => '{}',
    'data/admin/settings.json' => json_encode([
        'registration' => true,
        'roomCreation' => 'free',
        'p2pEnabled' => true,
        'maxFileSize' => 52428800,
        'maxMembersPerRoom' => 500,
        'rateLimitMessages' => 30,
        'allowImages' => true,
        'allowVideos' => true,
        'allowDocuments' => true,
        'allowVoice' => true,
        'maxLoginAttempts' => 5,
        'lockoutDuration' => 15,
        'minPasswordLength' => 8,
        'appName' => 'ROOMs',
        'maintenanceMode' => false
    ], JSON_PRETTY_PRINT),
];

foreach ($seeds as $file => $content) {
    $full = $base . '/' . $file;
    if (!file_exists($full)) {
        if (file_put_contents($full, $content) !== false) {
            $created[] = $file;
        } else {
            $errors[] = "Failed to write: $file";
        }
    }
}

// Admin password file
$pwFile = $base . '/data/admin/.password';
if (!file_exists($pwFile)) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
    if (file_put_contents($pwFile, $hash) !== false) {
        $created[] = 'data/admin/.password (default: admin123)';
    }
}

// Set permissions
foreach (['data', 'uploads', 'cache', 'chats', 'status'] as $d) {
    @chmod($base . '/' . $d, 0775);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>ROOMs — Install</title>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,sans-serif}
body{background:#0B141A;color:#E9EDEF;display:flex;justify-content:center;padding:40px 20px}
.card{max-width:600px;width:100%;background:#111B21;border-radius:16px;padding:32px;border:1px solid rgba(134,150,160,.15)}
h1{font-size:24px;margin-bottom:8px;color:#25D366}
.sub{color:#8696A0;margin-bottom:24px}
.ok{color:#25D366;padding:6px 0;font-size:14px}
.ok::before{content:'✓ '}
.err{color:#E74C3C;padding:6px 0;font-size:14px}
.err::before{content:'✗ '}
.done{margin-top:24px;padding:16px;background:rgba(37,211,102,.1);border-radius:10px;border:1px solid rgba(37,211,102,.3)}
.done h3{color:#25D366;margin-bottom:6px}
.done p{font-size:14px;color:#8696A0}
.warn{margin-top:16px;padding:14px;background:rgba(231,76,60,.1);border-radius:10px;border:1px solid rgba(231,76,60,.3);font-size:13px;color:#E74C3C}
a{color:#25D366;text-decoration:none;display:inline-block;margin-top:16px;padding:12px 24px;background:#25D366;color:#000;border-radius:10px;font-weight:600}
</style>
</head>
<body>
<div class="card">
<h1>ROOMs Installation</h1>
<p class="sub">Setting up directories and seed files…</p>

<?php if (empty($created) && empty($errors)): ?>
<div class="ok">Everything already exists. No changes needed.</div>
<?php else: ?>
<?php foreach ($created as $c): ?><div class="ok">Created: <?= htmlspecialchars($c) ?></div><?php endforeach; ?>
<?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php endif; ?>

<?php if (empty($errors)): ?>
<div class="done">
  <h3>Installation Complete!</h3>
  <p>All directories and files are ready. Default admin password is <strong>admin123</strong> — change it in Admin Panel → Settings.</p>
</div>
<a href="index.html">Open ROOMs →</a>
<?php else: ?>
<div class="warn">⚠️ Some items failed. Check directory permissions: <code>chmod -R 775 data/ uploads/ cache/ chats/ status/</code></div>
<?php endif; ?>

<div class="warn">⚠️ <strong>Delete this file (install.php) after setup!</strong></div>
</div>
</body>
</html>
