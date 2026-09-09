<?php
/**
 * 项目配置模板（.env 凭据模式）
 * 复制此文件为 config.php，复制 .env.example 为 .env 并填入真实值
 * config.php 与 .env 均不入库（见 .gitignore）
 * 编辑 .env 一律用 env-edit.sh（值不进对话上下文），禁止直接 read/cat .env
 */
$ENV = @parse_ini_file(__DIR__ . '/.env') ?: [];

return [

    // ====== 数据库 ======
    // SQLite 文件路径，相对本文件
    'db_path'       => __DIR__ . '/data/app.db',

    // ====== 登录 ======
    // 账号 + 密码 hash（多账号用数组）。
    // ⚠️ password 必须存 password_hash() 生成的 hash，登录用 password_verify() 校验。
    //    生成方法：在项目目录跑一次 php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"
    //    把输出的 hash 字符串填进 .env 的 APP_ADMIN_PASS（值放 .env，不入库）
    'users' => [
        ['username' => $ENV['APP_ADMIN_USER'] ?? 'admin', 'password' => $ENV['APP_ADMIN_PASS'] ?? ''],
    ],

    // Session 有效期（秒），0 = 永久
    'session_lifetime' => 604800, // 7 天，0=信任设备永久（Cookie 1年+自动续期，换IP/重启不掉）

    // ====== 项目元信息（侧栏顶部的项目名）======
    'app_name'    => 'My App',
    'app_tagline' => 'Default', // 项目切换器旁的标签

    // ====== 第三方 API（按需填写，值放 .env）======
    // 'gemini_key' => $ENV['APP_GEMINI_KEY'] ?? '',
    // 'openai_key' => $ENV['APP_OPENAI_KEY'] ?? '',
];
