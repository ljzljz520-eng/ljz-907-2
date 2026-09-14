<?php
/**
 * CSV 剧目导入共用逻辑（后台导入页 admin/import.php 与命令行种子脚本 tools/seed.php 共用）
 *
 * 数据行共 10 列：
 *   剧目名称*, 剧种*, 地区, 传承人, 演员, 年代, 视频链接, 简介, 封面, 剧照
 * 「封面」「剧照」填写 uploads/ 目录下的相对路径（剧照多个用 | 分隔），图片需提前
 * 放入 uploads/；路径不存在或非法时忽略该项并记入警告，不影响该行其余字段导入。
 */

/**
 * 校验 uploads 相对路径：合法且文件存在时返回规范化路径，否则返回 null
 */
function csv_image_path(string $path): ?string
{
    $path = trim(str_replace('\\', '/', $path));
    // 拒绝空值、绝对路径、目录穿越、URL 与 Windows 盘符
    if ($path === '' || str_starts_with($path, '/')
        || str_contains($path, '..') || str_contains($path, '://')
        || preg_match('/^[a-zA-Z]:/', $path)) {
        return null;
    }
    $cfg  = require dirname(__DIR__) . '/config/config.php';
    $full = $cfg['upload']['dir'] . $path;
    if (!is_file($full)) {
        return null;
    }
    // 防符号链接逃逸出 uploads 目录
    $real = realpath($full);
    $base = realpath($cfg['upload']['dir']);
    if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $path;
}

/**
 * 导入 CSV 数据行（不含表头）。调用方负责事务包裹。
 *
 * @param PDO   $pdo         数据库连接
 * @param array $rows        数据行（每行为数组）
 * @param int   $startLineNo 首条数据行之前的行数（用于生成文件行号提示）
 * @return array{inserted:int, updated:int, skipped:string[], warnings:string[]}
 */
function import_play_rows(PDO $pdo, array $rows, int $startLineNo = 0): array
{
    $typeStmt   = $pdo->prepare('SELECT id FROM opera_types WHERE name = ?');
    $typeIns    = $pdo->prepare('INSERT INTO opera_types (name, region) VALUES (?, ?)');
    $existStmt  = $pdo->prepare('SELECT id FROM plays WHERE title = ? AND type_id = ?');
    $insertStmt = $pdo->prepare('INSERT INTO plays (title, type_id, region, inheritor, actors, era, video_url, description, cover)
                                 VALUES (?,?,?,?,?,?,?,?,?)');
    $updateStmt = $pdo->prepare('UPDATE plays SET region=?, inheritor=?, actors=?, era=?, video_url=?, description=?
                                 WHERE id=?');
    $coverStmt  = $pdo->prepare('UPDATE plays SET cover = ? WHERE id = ?');
    $imgDelStmt = $pdo->prepare('DELETE FROM play_images WHERE play_id = ?');
    $imgInsStmt = $pdo->prepare('INSERT INTO play_images (play_id, image_path, sort_order) VALUES (?,?,?)');

    $inserted = 0; $updated = 0;
    $skipped = []; $warnings = [];
    $lineNo = $startLineNo;

    foreach ($rows as $row) {
        $lineNo++;
        $row = array_pad(array_map(fn($v) => trim((string)$v), $row), 10, '');
        [$title, $typeName, $region, $inheritor, $actors, $era, $videoUrl, $desc, $cover, $stills] = $row;

        if ($title === '' || $typeName === '') {
            $skipped[] = "第 {$lineNo} 行：剧目名称或剧种为空，已跳过";
            continue;
        }
        if ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            $skipped[] = "第 {$lineNo} 行：《{$title}》视频链接格式不正确，已跳过";
            continue;
        }

        // ---- 封面（第 9 列）：uploads/ 相对路径 ----
        $coverPath = null;
        if ($cover !== '') {
            $coverPath = csv_image_path($cover);
            if ($coverPath === null) {
                $warnings[] = "第 {$lineNo} 行：《{$title}》封面「{$cover}」不存在或路径非法，已忽略";
            }
        }

        // ---- 剧照（第 10 列）：多个路径用 | 分隔 ----
        $stillPaths = [];
        if ($stills !== '') {
            foreach (preg_split('/[|；;]/u', $stills) as $p) {
                $p = trim($p);
                if ($p === '') continue;
                $norm = csv_image_path($p);
                if ($norm === null) {
                    $warnings[] = "第 {$lineNo} 行：《{$title}》剧照「{$p}」不存在或路径非法，已忽略";
                } elseif (!in_array($norm, $stillPaths, true)) {
                    $stillPaths[] = $norm;
                }
            }
        }

        // 剧种不存在则自动创建
        $typeStmt->execute([$typeName]);
        $typeId = $typeStmt->fetchColumn();
        if (!$typeId) {
            $typeIns->execute([$typeName, $region]);
            $typeId = (int)$pdo->lastInsertId();
        }

        // 同名同剧种 → 更新，否则新增
        $existStmt->execute([$title, $typeId]);
        $playId = (int)$existStmt->fetchColumn();
        if ($playId > 0) {
            $updateStmt->execute([$region, $inheritor, $actors, $era, $videoUrl, $desc, $playId]);
            // 封面：仅当 CSV 提供有效路径时才更新，避免清空后台已上传的封面
            if ($coverPath !== null) {
                $coverStmt->execute([$coverPath, $playId]);
            }
            $updated++;
        } else {
            $insertStmt->execute([$title, $typeId, $region, $inheritor, $actors, $era, $videoUrl, $desc, $coverPath ?? '']);
            $playId = (int)$pdo->lastInsertId();
            $inserted++;
        }

        // 剧照：CSV 列非空时整体替换（重复导入幂等）；仅改数据库引用，不删除图片文件
        if ($stills !== '') {
            $imgDelStmt->execute([$playId]);
            foreach ($stillPaths as $i => $p) {
                $imgInsStmt->execute([$playId, $p, $i]);
            }
        }
    }

    return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'warnings' => $warnings];
}
