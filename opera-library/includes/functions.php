<?php
/**
 * 公共函数库
 */

/** HTML 转义输出 */
function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** 跳转 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** 一次性提示消息 */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** CSRF 令牌 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $known = csrf_token(); // 确保会话令牌存在；首次直接 POST 时必然校验失败
    if ($token === '' || !hash_equals($known, $token)) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试');
    }
}

/** 当前页码 */
function current_page(): int
{
    $p = (int)($_GET['page'] ?? 1);
    return max(1, $p);
}

/**
 * 生成分页 HTML
 * @param int    $total   总记录数
 * @param int    $page    当前页
 * @param int    $perPage 每页条数
 */
function pagination(int $total, int $page, int $perPage): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $page = min($page, $pages);
    $build = function (int $p): string {
        $q = array_merge($_GET, ['page' => $p]);
        return '?' . http_build_query($q);
    };
    $html = '<nav class="pagination" aria-label="分页">';
    // 上一页
    if ($page > 1) {
        $html .= '<a class="page-btn" href="' . e($build($page - 1)) . '">上一页</a>';
    } else {
        $html .= '<span class="page-btn disabled">上一页</span>';
    }
    // 页码（以当前页为中心显示 7 个）
    $start = max(1, $page - 3);
    $end   = min($pages, $page + 3);
    if ($start > 1) {
        $html .= '<a class="page-btn" href="' . e($build(1)) . '">1</a>';
        if ($start > 2) $html .= '<span class="page-dots">…</span>';
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<span class="page-btn current">' . $i . '</span>';
        } else {
            $html .= '<a class="page-btn" href="' . e($build($i)) . '">' . $i . '</a>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) $html .= '<span class="page-dots">…</span>';
        $html .= '<a class="page-btn" href="' . e($build($pages)) . '">' . $pages . '</a>';
    }
    // 下一页
    if ($page < $pages) {
        $html .= '<a class="page-btn" href="' . e($build($page + 1)) . '">下一页</a>';
    } else {
        $html .= '<span class="page-btn disabled">下一页</span>';
    }
    $html .= '<span class="page-total">共 ' . $total . ' 条 / ' . $pages . ' 页</span>';
    $html .= '</nav>';
    return $html;
}

/** 上传图片，返回相对路径；失败抛异常 */
function upload_image(array $file): string
{
    $cfg = require dirname(__DIR__) . '/config/config.php';
    $up  = $cfg['upload'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('文件上传失败，错误码：' . $file['error']);
    }
    if ($file['size'] > $up['max_size']) {
        throw new RuntimeException('图片超过大小限制（最大 5MB）');
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $up['allow_ext'], true)) {
        throw new RuntimeException('仅支持 jpg/png/gif/webp 格式');
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $up['allow_mime'], true)) {
        throw new RuntimeException('文件类型不合法');
    }
    $name = date('Ymd/') . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $up['dir'] . $name;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('保存文件失败');
    }
    return $name;
}

/** 删除上传的图片文件 */
function delete_upload(?string $path): void
{
    if (!$path) return;
    $cfg = require dirname(__DIR__) . '/config/config.php';
    $full = $cfg['upload']['dir'] . $path;
    if (is_file($full) && strpos(realpath($full), realpath($cfg['upload']['dir'])) === 0) {
        unlink($full);
    }
}

/** 视频链接类型判断：mp4 / bilibili / youtube / 其他 */
function video_embed(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('/\.mp4(\?|$)/i', $url)) {
        return '<video controls preload="metadata" style="width:100%;border-radius:8px;background:#000" src="' . e($url) . '"></video>';
    }
    if (preg_match('#bilibili\.com/video/(BV\w+)#', $url, $m)) {
        return '<iframe src="//player.bilibili.com/player.html?bvid=' . e($m[1]) . '&autoplay=0" scrolling="no" frameborder="no" allowfullscreen style="width:100%;aspect-ratio:16/9;border-radius:8px"></iframe>';
    }
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $url, $m)) {
        return '<iframe src="https://www.youtube.com/embed/' . e($m[1]) . '" frameborder="0" allowfullscreen style="width:100%;aspect-ratio:16/9;border-radius:8px"></iframe>';
    }
    return '<a class="btn btn-primary" href="' . e($url) . '" target="_blank" rel="noopener">▶ 前往观看视频</a>';
}

/** 封面图 URL（无图时返回默认占位图） */
function cover_url(?string $cover): string
{
    $cfg = require dirname(__DIR__) . '/config/config.php';
    if ($cover && is_file($cfg['upload']['dir'] . $cover)) {
        return $cfg['upload']['url'] . $cover;
    }
    return 'assets/img/no-cover.svg';
}
