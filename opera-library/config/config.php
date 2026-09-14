<?php
/**
 * 全局配置
 * 部署时根据实际情况修改数据库账号密码
 */
return [
    // MySQL 数据库
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'opera_library',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    // 站点信息
    'site' => [
        'name' => '地方戏曲影像库',
        'desc' => '非物质文化遗产 · 地方戏曲数字影像资料平台',
    ],
    // 上传
    'upload' => [
        'dir'       => dirname(__DIR__) . '/uploads/',
        'url'       => 'uploads/',
        'max_size'  => 5 * 1024 * 1024,               // 单个文件最大 5MB
        'allow_ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allow_mime'=> ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],
    // 分页
    'page_size'       => 9,   // 前台每页条数
    'admin_page_size' => 15,  // 后台每页条数
];
