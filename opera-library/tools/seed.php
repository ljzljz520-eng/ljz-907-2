<?php
/**
 * 命令行种子脚本：导入示例 CSV 并关联种子剧照
 * 用法: php tools/seed.php
 */
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$csvFile = dirname(__DIR__) . '/sample_data/plays_sample.csv';
$rows = array_map('str_getcsv', file($csvFile));
$header = array_shift($rows);

// slug 映射（标题 → 种子图文件名前缀）
$slugs = [
    '牡丹亭'=>'mudanting','长生殿'=>'changshengdian','霸王别姬'=>'bawangbieji',
    '贵妃醉酒'=>'guifeizuijiu','红楼梦'=>'hongloumeng','梁山伯与祝英台'=>'liangzhu',
    '天仙配'=>'tianxianpei','女驸马'=>'nvfuma','花木兰'=>'huamulan','穆桂英挂帅'=>'muguiying',
    '白蛇传'=>'baishezhuan','芙蓉花仙'=>'furonghuaxian','帝女花'=>'dinvhua',
    '紫钗记'=>'zichaiji','花为媒'=>'huaweimei','秦香莲'=>'qinxianglian',
    '三滴血'=>'sandixue','刘海砍樵'=>'liuhaikanqiao',
];

$typeStmt = $pdo->prepare('SELECT id FROM opera_types WHERE name = ?');
$typeIns  = $pdo->prepare('INSERT INTO opera_types (name, region) VALUES (?, ?)');
$playIns  = $pdo->prepare('INSERT INTO plays (title, type_id, region, inheritor, actors, era, video_url, description, cover)
                           VALUES (?,?,?,?,?,?,?,?,?)');
$imgIns   = $pdo->prepare('INSERT INTO play_images (play_id, image_path, sort_order) VALUES (?,?,?)');

$pdo->beginTransaction();
$count = 0;
foreach ($rows as $row) {
    $row = array_pad(array_map('trim', $row), 8, '');
    [$title, $typeName, $region, $inheritor, $actors, $era, $videoUrl, $desc] = $row;
    if ($title === '' || $typeName === '') continue;

    $typeStmt->execute([$typeName]);
    $typeId = $typeStmt->fetchColumn();
    if (!$typeId) {
        $typeIns->execute([$typeName, $region]);
        $typeId = (int)$pdo->lastInsertId();
    }
    $slug  = $slugs[$title] ?? null;
    $cover = $slug ? "seed/{$slug}_1.svg" : '';
    $playIns->execute([$title, $typeId, $region, $inheritor, $actors, $era, $videoUrl, $desc, $cover]);
    $playId = (int)$pdo->lastInsertId();
    if ($slug) {
        $imgIns->execute([$playId, "seed/{$slug}_2.svg", 0]);
    }
    $count++;
}
$pdo->commit();
echo "已导入 {$count} 条剧目种子数据\n";
