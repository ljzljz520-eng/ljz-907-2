<?php
/**
 * 删除剧目（连带剧照文件）
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('plays.php');
}
csrf_verify();

$id  = (int)($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT cover FROM plays WHERE id = ?');
$stmt->execute([$id]);
$play = $stmt->fetch();

if ($play) {
    $stmt = $pdo->prepare('SELECT image_path FROM play_images WHERE play_id = ?');
    $stmt->execute([$id]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM plays WHERE id = ?')->execute([$id]); // 剧照记录级联删除

    delete_upload($play['cover']);
    foreach ($paths as $p) delete_upload($p);
    flash('success', '剧目已删除');
} else {
    flash('error', '剧目不存在');
}
redirect('plays.php');
