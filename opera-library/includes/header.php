<?php
/** 前台页头 */
$siteName = $CONFIG['site']['name'] ?? '地方戏曲影像库';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteName) ?> - <?= e($siteName) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="index.php">
      <span class="logo-mark">戏</span>
      <span class="logo-text"><?= e($siteName) ?></span>
    </a>
    <nav class="site-nav">
      <a href="index.php">剧目浏览</a>
      <a href="admin/login.php">后台管理</a>
    </nav>
  </div>
  <div class="site-tagline"><?= e($CONFIG['site']['desc'] ?? '') ?></div>
</header>
<main class="container">
