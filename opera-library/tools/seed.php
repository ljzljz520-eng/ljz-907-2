<?php
/**
 * 命令行种子脚本：导入示例 CSV（含封面与剧照关联）
 * 用法: php tools/seed.php
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/importer.php';

$pdo     = db();
$csvFile = dirname(__DIR__) . '/sample_data/plays_sample.csv';
if (!is_file($csvFile)) {
    fwrite(STDERR, "找不到示例 CSV：{$csvFile}\n");
    exit(1);
}
$rows = array_map('str_getcsv', file($csvFile));

// 跳过表头、过滤空行
$header = $rows[0] ?? [];
if (mb_strpos($header[0] ?? '', '剧目') !== false || stripos($header[0] ?? '', 'title') !== false) {
    array_shift($rows);
}
$rows = array_values(array_filter($rows, fn($r) =>
    count(array_filter($r, fn($v) => trim((string)$v) !== '')) > 0
));

$pdo->beginTransaction();
try {
    $result = import_play_rows($pdo, $rows, 1);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    fwrite(STDERR, "导入失败：{$ex->getMessage()}（已回滚）\n");
    exit(1);
}

echo "种子数据导入完成：新增 {$result['inserted']} 条，更新 {$result['updated']} 条，跳过 " . count($result['skipped']) . " 条\n";
foreach (array_merge($result['skipped'], $result['warnings']) as $msg) {
    echo "  - {$msg}\n";
}
