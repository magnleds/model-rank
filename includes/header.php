<?php
if (!defined('APP_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$CONFIG_PAGE_TITLE = $CONFIG_PAGE_TITLE ?? ($CONFIG['app_name'] ?? 'App');
$ACTIVE = $ACTIVE ?? '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($CONFIG_PAGE_TITLE) ?> · <?= htmlspecialchars($CONFIG['app_name']) ?></title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body class="layout">

<aside class="sidebar">
  <div class="sidebar-head">
    <button class="icon-btn" aria-label="折叠菜单">☰</button>
    <div class="brand">
      <div class="brand-mark">◆</div>
      <span class="brand-name"><?= htmlspecialchars($CONFIG['app_name']) ?></span>
    </div>
    <span class="brand-tag"><?= htmlspecialchars($CONFIG['app_tagline']) ?> ▾</span>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">主导航</div>
    <nav class="nav">
      <a class="nav-item<?= $ACTIVE==='dashboard'?' active':'' ?>" href="?p=dashboard">
        <span class="nav-icon">▦</span><span>仪表盘</span>
      </a>
      <a class="nav-item<?= $ACTIVE==='items'?' active':'' ?>" href="?p=items">
        <span class="nav-icon">▤</span><span>数据列表</span>
      </a>
      <a class="nav-item<?= $ACTIVE==='settings'?' active':'' ?>" href="?p=settings">
        <span class="nav-icon">⚙</span><span>设置</span>
      </a>
    </nav>
  </div>

  <div class="sidebar-foot">
    <div class="user-card">
      <div class="avatar"><?= strtoupper(substr(current_username(),0,1) ?: 'U') ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars(current_username()) ?></div>
        <div class="user-email">已登录</div>
      </div>
      <a class="icon-btn" href="?action=logout" aria-label="退出">⏻</a>
    </div>
  </div>
</aside>

<header class="topbar">
  <div class="topbar-left">
    <h1 class="page-title"><?= htmlspecialchars($page_title ?? '页面') ?></h1>
    <?php if (!empty($page_subtitle)): ?>
      <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
    <?php endif; ?>
  </div>
  <div class="topbar-right">
    <button class="icon-btn" aria-label="设置">⚙</button>
    <button class="icon-btn" aria-label="主题">☀</button>
  </div>
</header>

<main class="main">
