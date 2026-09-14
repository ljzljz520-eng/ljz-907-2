<?php
/**
 * 前台首页：按剧种 / 地区 / 传承人筛选 + 关键词搜索 + 分页
 */
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

// ---------- 读取筛选条件 ----------
$typeId    = (int)($_GET['type_id'] ?? 0);
$region    = trim((string)($_GET['region'] ?? ''));
$inheritor = trim((string)($_GET['inheritor'] ?? ''));
$keyword   = trim((string)($_GET['keyword'] ?? ''));
$page      = current_page();
$perPage   = $CONFIG['page_size'];

// ---------- 拼接查询 ----------
$where  = [];
$params = [];
if ($typeId > 0) {
    $where[]  = 'p.type_id = ?';
    $params[] = $typeId;
}
if ($region !== '') {
    $where[]  = 'p.region = ?';
    $params[] = $region;
}
if ($inheritor !== '') {
    $where[]  = 'p.inheritor = ?';
    $params[] = $inheritor;
}
if ($keyword !== '') {
    $where[]  = '(p.title LIKE ? OR p.actors LIKE ?)';
    $like     = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---------- 总数 ----------
$stmt = $pdo->prepare("SELECT COUNT(*) FROM plays p $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// ---------- 当前页数据 ----------
$offset = ($page - 1) * $perPage;
$sql = "SELECT p.*, t.name AS type_name
        FROM plays p
        JOIN opera_types t ON t.id = p.type_id
        $whereSql
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plays = $stmt->fetchAll();

// ---------- 筛选项数据 ----------
$types      = $pdo->query('SELECT id, name FROM opera_types ORDER BY id')->fetchAll();
$regions    = $pdo->query("SELECT DISTINCT region FROM plays WHERE region <> '' ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);
$inheritors = $pdo->query("SELECT DISTINCT inheritor FROM plays WHERE inheritor <> '' ORDER BY inheritor")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = '剧目浏览';
require __DIR__ . '/includes/header.php';
?>

<div class="filter-panel">
  <form method="get" action="index.php" class="filter-form">
    <div class="filter-row">
      <label>剧种
        <select name="type_id">
          <option value="0">全部剧种</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>地区
        <select name="region">
          <option value="">全部地区</option>
          <?php foreach ($regions as $r): ?>
          <option value="<?= e($r) ?>" <?= $region === $r ? 'selected' : '' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>传承人
        <select name="inheritor">
          <option value="">全部传承人</option>
          <?php foreach ($inheritors as $ih): ?>
          <option value="<?= e($ih) ?>" <?= $inheritor === $ih ? 'selected' : '' ?>><?= e($ih) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>关键词
        <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="剧目名 / 演员">
      </label>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">筛选</button>
        <a class="btn btn-ghost" href="index.php">重置</a>
      </div>
    </div>
  </form>
</div>

<?php if ($total === 0): ?>
  <div class="empty-state">
    <p>🎭 暂无符合条件的剧目</p>
    <p class="muted">换个筛选条件试试，或<a href="index.php">查看全部剧目</a></p>
  </div>
<?php else: ?>
  <div class="play-grid">
    <?php foreach ($plays as $p): ?>
    <a class="play-card" href="play.php?id=<?= (int)$p['id'] ?>">
      <div class="play-cover">
        <img src="<?= e(cover_url($p['cover'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <span class="play-type"><?= e($p['type_name']) ?></span>
      </div>
      <div class="play-info">
        <h3><?= e($p['title']) ?></h3>
        <p class="play-meta">
          <?php if ($p['inheritor']): ?><span>传承人：<?= e($p['inheritor']) ?></span><?php endif; ?>
          <?php if ($p['era']): ?><span><?= e($p['era']) ?></span><?php endif; ?>
        </p>
        <p class="play-meta muted">
          <?php if ($p['region']): ?><span>📍<?= e($p['region']) ?></span><?php endif; ?>
          <?php if ($p['actors']): ?><span>主演：<?= e($p['actors']) ?></span><?php endif; ?>
        </p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?= pagination($total, $page, $perPage) ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
