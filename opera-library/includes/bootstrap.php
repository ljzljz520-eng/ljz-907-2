<?php
/**
 * 全局引导：会话 + 配置 + 公共函数
 */
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$CONFIG = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set('Asia/Shanghai');
