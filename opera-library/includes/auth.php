<?php
/**
 * 后台登录验证
 */
require_once __DIR__ . '/bootstrap.php';

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('login.php');
    }
}

function admin_name(): string
{
    return $_SESSION['admin_name'] ?? '';
}
