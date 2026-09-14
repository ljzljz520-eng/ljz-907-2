<?php
/**
 * 后台登录
 */
require_once dirname(__DIR__) . '/includes/auth.php';

if (is_admin()) {
    redirect('index.php');
}

$error = '';
// 简单防爆破：连续失败 5 次锁定 10 分钟
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (time() < $lockedUntil) {
        $error = '失败次数过多，请 ' . ceil(($lockedUntil - time()) / 60) . ' 分钟后再试';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
            redirect('index.php');
        }
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 600;
            $_SESSION['login_fails'] = 0;
            $error = '失败次数过多，已锁定 10 分钟';
        } else {
            $error = '用户名或密码错误（剩余尝试次数：' . (5 - $_SESSION['login_fails']) . '）';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录 - 地方戏曲影像库</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-box">
  <div class="login-logo">戏</div>
  <h1>地方戏曲影像库 · 后台</h1>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>用户名
      <input type="text" name="username" required autofocus autocomplete="username">
    </label>
    <label>密码
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit" class="btn btn-primary btn-block">登 录</button>
  </form>
  <p class="muted center"><a href="../index.php">← 返回前台首页</a></p>
</div>
</body>
</html>
