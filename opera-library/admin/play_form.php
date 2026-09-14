<?php
/**
 * 添加 / 编辑剧目（含封面与剧照上传）
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo   = db();
$id    = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$play  = [
    'title' => '', 'type_id' => 0, 'region' => '', 'inheritor' => '',
    'actors' => '', 'era' => '', 'video_url' => '', 'cover' => '', 'description' => '',
];
$images = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM plays WHERE id = ?');
    $stmt->execute([$id]);
    $play = $stmt->fetch();
    if (!$play) {
        flash('error', '剧目不存在');
        redirect('plays.php');
    }
    $stmt = $pdo->prepare('SELECT * FROM play_images WHERE play_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $play['title']      = trim((string)($_POST['title'] ?? ''));
    $play['type_id']    = (int)($_POST['type_id'] ?? 0);
    $play['region']     = trim((string)($_POST['region'] ?? ''));
    $play['inheritor']  = trim((string)($_POST['inheritor'] ?? ''));
    $play['actors']     = trim((string)($_POST['actors'] ?? ''));
    $play['era']        = trim((string)($_POST['era'] ?? ''));
    $play['video_url']  = trim((string)($_POST['video_url'] ?? ''));
    $play['description']= trim((string)($_POST['description'] ?? ''));

    // ---- 校验 ----
    if ($play['title'] === '')  $errors[] = '请填写剧目名称';
    if ($play['type_id'] <= 0)  $errors[] = '请选择剧种';
    if ($play['video_url'] !== '' && !filter_var($play['video_url'], FILTER_VALIDATE_URL)) {
        $errors[] = '视频链接格式不正确';
    }

    // ---- 封面上传 ----
    $coverPath = $play['cover'];
    if (!empty($_FILES['cover']['name'])) {
        try {
            $coverPath = upload_image($_FILES['cover']);
        } catch (RuntimeException $ex) {
            $errors[] = '封面上传失败：' . $ex->getMessage();
        }
    }

    // ---- 剧照上传（多文件） ----
    $newImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $k => $name) {
            if ($name === '') continue;
            $file = [
                'name'     => $name,
                'type'     => $_FILES['images']['type'][$k],
                'tmp_name' => $_FILES['images']['tmp_name'][$k],
                'error'    => $_FILES['images']['error'][$k],
                'size'     => $_FILES['images']['size'][$k],
            ];
            try {
                $newImages[] = upload_image($file);
            } catch (RuntimeException $ex) {
                $errors[] = "剧照「{$name}」上传失败：" . $ex->getMessage();
            }
        }
    }

    // ---- 删除勾选的历史剧照 ----
    $delImages = array_map('intval', (array)($_POST['del_images'] ?? []));

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE plays SET title=?, type_id=?, region=?, inheritor=?,
                                       actors=?, era=?, video_url=?, cover=?, description=? WHERE id=?');
                $stmt->execute([$play['title'], $play['type_id'], $play['region'], $play['inheritor'],
                                $play['actors'], $play['era'], $play['video_url'], $coverPath,
                                $play['description'], $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO plays (title, type_id, region, inheritor, actors, era, video_url, cover, description)
                                       VALUES (?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$play['title'], $play['type_id'], $play['region'], $play['inheritor'],
                                $play['actors'], $play['era'], $play['video_url'], $coverPath, $play['description']]);
                $id = (int)$pdo->lastInsertId();
            }
            // 新剧照入库
            if ($newImages) {
                $stmt = $pdo->prepare('INSERT INTO play_images (play_id, image_path, sort_order) VALUES (?,?,0)');
                foreach ($newImages as $img) {
                    $stmt->execute([$id, $img]);
                }
            }
            // 删除剧照
            if ($delImages) {
                $in = implode(',', array_fill(0, count($delImages), '?'));
                $stmt = $pdo->prepare("SELECT image_path FROM play_images WHERE play_id = ? AND id IN ($in)");
                $stmt->execute(array_merge([$id], $delImages));
                $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $stmt = $pdo->prepare("DELETE FROM play_images WHERE play_id = ? AND id IN ($in)");
                $stmt->execute(array_merge([$id], $delImages));
                foreach ($paths as $p) delete_upload($p);
            }
            // 封面被替换时删除旧文件
            if ($isEdit && $coverPath !== $play['cover'] && $play['cover']) {
                delete_upload($play['cover']);
            }
            $pdo->commit();
            flash('success', $isEdit ? '剧目已更新' : '剧目已添加');
            redirect('plays.php');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = '保存失败：' . $ex->getMessage();
        }
    }
    $play['cover'] = $coverPath;
}

$types = $pdo->query('SELECT id, name FROM opera_types ORDER BY id')->fetchAll();
$pageTitle = $isEdit ? '编辑剧目' : '添加剧目';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="edit-form">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label>剧目名称 <i>*</i>
        <input type="text" name="title" value="<?= e($play['title']) ?>" required>
      </label>
      <label>剧种 <i>*</i>
        <select name="type_id" required>
          <option value="0">请选择</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$play['type_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>地区
        <input type="text" name="region" value="<?= e($play['region']) ?>" placeholder="如：江苏">
      </label>
      <label>代表性传承人
        <input type="text" name="inheritor" value="<?= e($play['inheritor']) ?>" placeholder="如：张继青">
      </label>
      <label>主要演员
        <input type="text" name="actors" value="<?= e($play['actors']) ?>" placeholder="多人用逗号分隔">
      </label>
      <label>年代
        <input type="text" name="era" value="<?= e($play['era']) ?>" placeholder="如：1980年代">
      </label>
      <label class="span-2">视频链接
        <input type="url" name="video_url" value="<?= e($play['video_url']) ?>" placeholder="支持 mp4 直链 / B站 / YouTube 链接">
      </label>
      <label class="span-2">剧目简介
        <textarea name="description" rows="5"><?= e($play['description']) ?></textarea>
      </label>
      <label>封面剧照
        <input type="file" name="cover" accept="image/*">
        <?php if ($play['cover']): ?>
        <div class="current-cover"><img src="../<?= e(cover_url($play['cover'])) ?>" alt="当前封面"> 当前封面</div>
        <?php endif; ?>
      </label>
      <label>上传剧照（可多选）
        <input type="file" name="images[]" accept="image/*" multiple>
      </label>
    </div>

    <?php if ($images): ?>
    <div class="image-manage">
      <h3>已有剧照（勾选删除）</h3>
      <div class="image-list">
        <?php foreach ($images as $img): ?>
        <label class="image-item">
          <img src="../<?= e($CONFIG['upload']['url'] . $img['image_path']) ?>" alt="">
          <span><input type="checkbox" name="del_images[]" value="<?= (int)$img['id'] ?>"> 删除</span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 保存</button>
      <a class="btn btn-ghost" href="plays.php">取消</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
