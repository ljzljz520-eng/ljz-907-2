<?php
/**
 * 剧目详情页：基本信息 + 视频 + 剧照 + 相关剧目
 */
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('index.php');
}

// 剧目详情
$stmt = $pdo->prepare('SELECT p.*, t.name AS type_name FROM plays p
                       JOIN opera_types t ON t.id = p.type_id WHERE p.id = ?');
$stmt->execute([$id]);
$play = $stmt->fetch();
if (!$play) {
    http_response_code(404);
    $pageTitle = '剧目不存在';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>😢 剧目不存在或已被删除</p><p><a class="btn btn-primary" href="index.php">返回首页</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// 浏览量 +1
$pdo->prepare('UPDATE plays SET views = views + 1 WHERE id = ?')->execute([$id]);

// 剧照
$stmt = $pdo->prepare('SELECT image_path FROM play_images WHERE play_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 相关剧目：同剧种优先，其次同地区，排除自身
$stmt = $pdo->prepare('SELECT p.*, t.name AS type_name FROM plays p
                       JOIN opera_types t ON t.id = p.type_id
                       WHERE p.id <> ? AND (p.type_id = ? OR p.region = ?)
                       ORDER BY (p.type_id = ?) DESC, p.views DESC, p.id DESC
                       LIMIT 6');
$stmt->execute([$id, $play['type_id'], $play['region'], $play['type_id']]);
$related = $stmt->fetchAll();

$pageTitle = $play['title'];
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
  <a href="index.php">首页</a> &rsaquo;
  <a href="index.php?type_id=<?= (int)$play['type_id'] ?>"><?= e($play['type_name']) ?></a> &rsaquo;
  <span><?= e($play['title']) ?></span>
</nav>

<div class="play-detail">
  <div class="detail-main">
    <div class="detail-cover">
      <img src="<?= e(cover_url($play['cover'])) ?>" alt="<?= e($play['title']) ?>">
    </div>
    <div class="detail-info">
      <h1><?= e($play['title']) ?></h1>
      <table class="info-table">
        <tr><th>剧种</th><td><a href="index.php?type_id=<?= (int)$play['type_id'] ?>"><?= e($play['type_name']) ?></a></td></tr>
        <?php if ($play['region']): ?>
        <tr><th>地区</th><td><a href="index.php?region=<?= urlencode($play['region']) ?>"><?= e($play['region']) ?></a></td></tr>
        <?php endif; ?>
        <?php if ($play['inheritor']): ?>
        <tr><th>传承人</th><td><a href="index.php?inheritor=<?= urlencode($play['inheritor']) ?>"><?= e($play['inheritor']) ?></a></td></tr>
        <?php endif; ?>
        <?php if ($play['actors']): ?>
        <tr><th>主要演员</th><td><?= e($play['actors']) ?></td></tr>
        <?php endif; ?>
        <?php if ($play['era']): ?>
        <tr><th>年代</th><td><?= e($play['era']) ?></td></tr>
        <?php endif; ?>
        <tr><th>浏览次数</th><td><?= (int)$play['views'] + 1 ?></td></tr>
      </table>
    </div>
  </div>

  <?php if ($play['video_url']): ?>
  <section class="detail-section">
    <h2>演出影像</h2>
    <div class="video-box"><?= video_embed($play['video_url']) ?></div>
  </section>
  <?php endif; ?>

  <?php if ($play['description']): ?>
  <section class="detail-section">
    <h2>剧目简介</h2>
    <div class="description"><?= nl2br(e($play['description'])) ?></div>
  </section>
  <?php endif; ?>

  <?php if ($images): ?>
  <section class="detail-section">
    <h2>剧照（<?= count($images) ?>）</h2>
    <div class="gallery">
      <?php foreach ($images as $img): ?>
      <a href="<?= e($CONFIG['upload']['url'] . $img) ?>" target="_blank">
        <img src="<?= e($CONFIG['upload']['url'] . $img) ?>" alt="剧照" loading="lazy">
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php if ($related): ?>
<section class="related-section">
  <h2>相关剧目</h2>
  <div class="play-grid">
    <?php foreach ($related as $p): ?>
    <a class="play-card" href="play.php?id=<?= (int)$p['id'] ?>">
      <div class="play-cover">
        <img src="<?= e(cover_url($p['cover'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <span class="play-type"><?= e($p['type_name']) ?></span>
      </div>
      <div class="play-info">
        <h3><?= e($p['title']) ?></h3>
        <p class="play-meta muted">
          <?php if ($p['inheritor']): ?><span>传承人：<?= e($p['inheritor']) ?></span><?php endif; ?>
          <?php if ($p['era']): ?><span><?= e($p['era']) ?></span><?php endif; ?>
        </p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
