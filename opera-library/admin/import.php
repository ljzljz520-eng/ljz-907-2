<?php
/**
 * CSV 批量导入剧目
 * CSV 列（支持中文表头，首行可省略）：
 *   剧目名称*, 剧种*, 地区, 传承人, 演员, 年代, 视频链接, 简介, 封面, 剧照
 * 规则：同「剧目名称+剧种」已存在时执行更新，否则新增；未知剧种自动创建。
 * 封面 / 剧照填写 uploads/ 目录下的相对路径（剧照多个用 | 分隔）。
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/importer.php';
require_admin();

$pdo    = db();
$result = null;

/** 读取并转码 CSV 内容为 UTF-8 行数组 */
function read_csv_rows(string $tmpFile): array
{
    $content = file_get_contents($tmpFile);
    if ($content === false || trim($content) === '') {
        throw new RuntimeException('文件为空或读取失败');
    }
    // 去 BOM
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    // 非 UTF-8 时按 GB18030 转码（Excel 中文 CSV 常见）
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'GB18030');
    }
    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $content);
    rewind($fh);
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        // 跳过完全为空的行
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;
        $rows[] = array_map(fn($v) => trim((string)$v), $row);
    }
    fclose($fh);
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择要上传的 CSV 文件');
        }
        $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            throw new RuntimeException('仅支持 .csv 文件');
        }
        $rows = read_csv_rows($_FILES['csv']['tmp_name']);
        if (!$rows) {
            throw new RuntimeException('CSV 中没有数据行');
        }

        // 识别表头：首行含「剧目」或 "title" 视为表头
        $header = $rows[0];
        $hasHeader = (mb_strpos($header[0] ?? '', '剧目') !== false)
                  || (stripos($header[0] ?? '', 'title') !== false);
        if ($hasHeader) array_shift($rows);

        // 执行导入（整个文件一个事务，出错回滚）
        $pdo->beginTransaction();
        $result = import_play_rows($pdo, $rows, $hasHeader ? 1 : 0);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $result = ['error' => $ex->getMessage()];
    }
}

$pageTitle = 'CSV 导入';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <h2>上传 CSV 文件</h2>
  <form method="post" enctype="multipart/form-data" class="import-form">
    <?= csrf_field() ?>
    <div class="upload-box">
      <input type="file" name="csv" accept=".csv,.txt" required>
      <button type="submit" class="btn btn-primary">📥 开始导入</button>
    </div>
  </form>

  <div class="import-help">
    <h3>CSV 格式说明</h3>
    <p>每行一条剧目，列顺序如下（首行表头可省略），支持 UTF-8 与 GBK 编码：</p>
    <table class="data-table">
      <thead><tr><th>列</th><th>字段</th><th>必填</th><th>示例</th></tr></thead>
      <tbody>
        <tr><td>1</td><td>剧目名称</td><td>✔</td><td>牡丹亭</td></tr>
        <tr><td>2</td><td>剧种</td><td>✔</td><td>昆曲（不存在会自动创建）</td></tr>
        <tr><td>3</td><td>地区</td><td></td><td>江苏</td></tr>
        <tr><td>4</td><td>传承人</td><td></td><td>张继青</td></tr>
        <tr><td>5</td><td>演员</td><td></td><td>张继青,王亨恺</td></tr>
        <tr><td>6</td><td>年代</td><td></td><td>1980年代</td></tr>
        <tr><td>7</td><td>视频链接</td><td></td><td>https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/360/Big_Buck_Bunny_360_10s_1MB.mp4</td></tr>
        <tr><td>8</td><td>简介</td><td></td><td>明代汤显祖代表作……</td></tr>
        <tr><td>9</td><td>封面</td><td></td><td>seed/mudanting_1.svg（uploads/ 下的相对路径）</td></tr>
        <tr><td>10</td><td>剧照</td><td></td><td>seed/mudanting_2.svg（多张用 | 分隔）</td></tr>
      </tbody>
    </table>
    <p>规则：「剧目名称 + 剧种」相同视为同一剧目，重复导入时执行<strong>更新</strong>；整个文件在一个事务中导入，出错自动回滚。</p>
    <p>图片说明：封面 / 剧照需提前放入 <code>uploads/</code> 目录，CSV 中填相对路径（如 <code>seed/mudanting_1.svg</code>）；路径不存在时忽略该图片并提示，不影响剧目导入。更新已有剧目时，仅当封面 / 剧照列非空才覆盖对应图片。</p>
    <p><a href="../sample_data/plays_sample.csv" download>⬇ 下载示例 CSV</a></p>
  </div>
</div>

<?php if ($result): ?>
<div class="panel">
  <h2>导入结果</h2>
  <?php if (isset($result['error'])): ?>
    <div class="alert alert-error">导入失败：<?= e($result['error']) ?>（已回滚，未写入任何数据）</div>
  <?php else: ?>
    <div class="alert alert-success">
      导入完成：新增 <strong><?= $result['inserted'] ?></strong> 条，更新 <strong><?= $result['updated'] ?></strong> 条，
      跳过 <strong><?= count($result['skipped']) ?></strong> 条。
      <a href="plays.php">查看剧目列表 →</a>
    </div>
    <?php if ($result['skipped']): ?>
    <ul class="skip-list">
      <?php foreach ($result['skipped'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if (!empty($result['warnings'])): ?>
    <div class="alert alert-warning">部分图片未关联：</div>
    <ul class="skip-list">
      <?php foreach ($result['warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
