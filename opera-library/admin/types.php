<?php
/**
 * 剧种管理
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name   = trim((string)($_POST['name'] ?? ''));
        $region = trim((string)($_POST['region'] ?? ''));
        if ($name === '') {
            flash('error', '剧种名称不能为空');
        } else {
            try {
                $pdo->prepare('INSERT INTO opera_types (name, region) VALUES (?,?)')->execute([$name, $region]);
                flash('success', '剧种已添加');
            } catch (PDOException $e) {
                flash('error', '添加失败：剧种可能已存在');
            }
        }
    } elseif ($action === 'delete') {
        $tid = (int)($_POST['id'] ?? 0);
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM plays WHERE type_id = ?');
        $cnt->execute([$tid]);
        if ($cnt->fetchColumn() > 0) {
            flash('error', '该剧种下还有剧目，无法删除');
        } else {
            $pdo->prepare('DELETE FROM opera_types WHERE id = ?')->execute([$tid]);
            flash('success', '剧种已删除');
        }
    }
    redirect('types.php');
}

$types = $pdo->query('SELECT t.*, (SELECT COUNT(*) FROM plays p WHERE p.type_id = t.id) AS play_count
                      FROM opera_types t ORDER BY t.id')->fetchAll();
$pageTitle = '剧种管理';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head">
    <h2>剧种列表</h2>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="剧种名称" required>
      <input type="text" name="region" placeholder="主要流传地区">
      <button class="btn btn-primary btn-sm" type="submit">+ 添加</button>
    </form>
  </div>
  <table class="data-table">
    <thead><tr><th>ID</th><th>剧种</th><th>流传地区</th><th>剧目数</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($types as $t): ?>
      <tr>
        <td><?= (int)$t['id'] ?></td>
        <td><?= e($t['name']) ?></td>
        <td><?= e($t['region']) ?></td>
        <td><?= (int)$t['play_count'] ?></td>
        <td>
          <form method="post" class="inline" onsubmit="return confirm('确定删除该剧种吗？')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit" <?= $t['play_count'] > 0 ? 'disabled title="该剧种下还有剧目"' : '' ?>>删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
