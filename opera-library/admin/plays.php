<?php
/**
 * 后台剧目列表（分页 + 搜索 + 删除）
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo     = db();
$keyword = trim((string)($_GET['keyword'] ?? ''));
$typeId  = (int)($_GET['type_id'] ?? 0);
$page    = current_page();
$perPage = $CONFIG['admin_page_size'];

$where  = [];
$params = [];
if ($keyword !== '') {
    $where[]  = '(p.title LIKE ? OR p.actors LIKE ? OR p.inheritor LIKE ?)';
    $like     = "%$keyword%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($typeId > 0) {
    $where[]  = 'p.type_id = ?';
    $params[] = $typeId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM plays p $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT p.*, t.name AS type_name,
                        (SELECT COUNT(*) FROM play_images i WHERE i.play_id = p.id) AS img_count
                       FROM plays p JOIN opera_types t ON t.id = p.type_id
                       $whereSql ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$plays = $stmt->fetchAll();

$types = $pdo->query('SELECT id, name FROM opera_types ORDER BY id')->fetchAll();

$pageTitle = '剧目管理';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head">
    <form method="get" class="inline-form">
      <select name="type_id">
        <option value="0">全部剧种</option>
        <?php foreach ($types as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="剧目 / 演员 / 传承人">
      <button class="btn btn-primary btn-sm" type="submit">搜索</button>
    </form>
    <div>
      <a class="btn btn-ghost btn-sm" href="import.php">📥 CSV 导入</a>
      <a class="btn btn-primary btn-sm" href="play_form.php">+ 添加剧目</a>
    </div>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>ID</th><th>封面</th><th>剧目</th><th>剧种</th><th>地区</th><th>传承人</th><th>年代</th><th>剧照</th><th>浏览</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($plays as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><img class="thumb" src="../<?= e(cover_url($p['cover'])) ?>" alt=""></td>
        <td><?= e($p['title']) ?></td>
        <td><?= e($p['type_name']) ?></td>
        <td><?= e($p['region']) ?></td>
        <td><?= e($p['inheritor']) ?></td>
        <td><?= e($p['era']) ?></td>
        <td><?= (int)$p['img_count'] ?></td>
        <td><?= (int)$p['views'] ?></td>
        <td class="actions">
          <a class="btn btn-sm" href="../play.php?id=<?= (int)$p['id'] ?>" target="_blank">查看</a>
          <a class="btn btn-sm btn-primary" href="play_form.php?id=<?= (int)$p['id'] ?>">编辑</a>
          <form method="post" action="play_delete.php" class="inline" onsubmit="return confirm('确定删除《<?= e($p['title']) ?>》吗？剧照文件将一并删除。')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$plays): ?><tr><td colspan="10" class="center muted">暂无数据</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination($total, $page, $perPage) ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
