<?php
/**
 * 后台控制台
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo = db();
$playCount = (int)$pdo->query('SELECT COUNT(*) FROM plays')->fetchColumn();
$typeCount = (int)$pdo->query('SELECT COUNT(*) FROM opera_types')->fetchColumn();
$imgCount  = (int)$pdo->query('SELECT COUNT(*) FROM play_images')->fetchColumn();
$viewSum   = (int)$pdo->query('SELECT COALESCE(SUM(views),0) FROM plays')->fetchColumn();
$latest    = $pdo->query('SELECT p.*, t.name AS type_name FROM plays p
                          JOIN opera_types t ON t.id = p.type_id
                          ORDER BY p.created_at DESC, p.id DESC LIMIT 8')->fetchAll();

$pageTitle = '控制台';
require __DIR__ . '/includes/header.php';
?>
<div class="stat-cards">
  <div class="stat-card"><div class="stat-num"><?= $playCount ?></div><div class="stat-label">收录剧目</div></div>
  <div class="stat-card"><div class="stat-num"><?= $typeCount ?></div><div class="stat-label">戏曲剧种</div></div>
  <div class="stat-card"><div class="stat-num"><?= $imgCount ?></div><div class="stat-label">剧照图片</div></div>
  <div class="stat-card"><div class="stat-num"><?= $viewSum ?></div><div class="stat-label">累计浏览</div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>最新收录</h2>
    <a class="btn btn-primary btn-sm" href="play_form.php">+ 添加剧目</a>
  </div>
  <table class="data-table">
    <thead><tr><th>ID</th><th>剧目</th><th>剧种</th><th>传承人</th><th>年代</th><th>收录时间</th></tr></thead>
    <tbody>
    <?php foreach ($latest as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><a href="play_form.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></td>
        <td><?= e($p['type_name']) ?></td>
        <td><?= e($p['inheritor']) ?></td>
        <td><?= e($p['era']) ?></td>
        <td><?= e($p['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$latest): ?><tr><td colspan="6" class="center muted">暂无数据，可通过 <a href="import.php">CSV 导入</a> 批量添加</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
