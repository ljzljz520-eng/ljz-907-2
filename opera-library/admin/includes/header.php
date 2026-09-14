<?php
/** 后台布局页头（需先 require auth.php 并调用 require_admin()） */
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? '后台管理') ?> - 地方戏曲影像库</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><span class="logo-mark">戏</span> 影像库后台</div>
    <nav class="sidebar-nav">
      <a href="index.php"  class="<?= $current === 'index.php' ? 'active' : '' ?>">📊 控制台</a>
      <a href="plays.php"  class="<?= in_array($current, ['plays.php','play_form.php'], true) ? 'active' : '' ?>">🎬 剧目管理</a>
      <a href="import.php" class="<?= $current === 'import.php' ? 'active' : '' ?>">📥 CSV 导入</a>
      <a href="types.php"  class="<?= $current === 'types.php' ? 'active' : '' ?>">🏷 剧种管理</a>
    </nav>
    <div class="sidebar-foot">
      <a href="../index.php" target="_blank">🌐 查看前台</a>
      <a href="logout.php">🚪 退出登录</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <h1><?= e($pageTitle ?? '') ?></h1>
      <span class="admin-user">管理员：<?= e(admin_name()) ?></span>
    </header>
    <div class="admin-content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
