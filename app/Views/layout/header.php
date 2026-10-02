<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? '') . ' - ' . ($appName ?? '系统')) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4f6fb;
            --card: #ffffff;
            --text: #172331;
            --primary: #0f6d5f;
            --danger: #bb1e10;
            --warn: #c58300;
            --ok: #1a7f37;
            --muted: #6b7280;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Noto Sans SC", "Microsoft YaHei", sans-serif;
            background: radial-gradient(circle at 10% 0%, #eaf7f4, var(--bg) 38%), var(--bg);
            color: var(--text);
            font-size: 16px;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 20px; }
        .login-wrap { max-width: 420px; margin: 70px auto; }
        .nav {
            background: linear-gradient(135deg, #1f4a6a, #0f6d5f);
            color: #fff;
            padding: 14px 20px;
            position: sticky;
            top: 0;
            z-index: 8;
        }
        .nav .inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand {
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-right: 12px;
        }
        .nav a {
            color: #fff;
            text-decoration: none;
            opacity: 0.95;
            padding: 4px 2px;
            border-bottom: 2px solid transparent;
        }
        .nav a:hover { border-color: rgba(255,255,255,0.75); }
        .grow { flex: 1; }
        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(10, 20, 40, 0.08);
            padding: 16px;
            margin-bottom: 14px;
        }
        .grid {
            display: grid;
            gap: 14px;
        }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .stat { font-size: 28px; font-weight: 700; margin: 8px 0 0; }
        h1 { margin: 0 0 16px; font-size: 28px; }
        h2 { margin: 0 0 10px; font-size: 20px; }
        h3 { margin: 0 0 8px; font-size: 17px; }
        form.inline, .inline { display: inline-flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        form.stack { display: grid; gap: 10px; }
        label { font-size: 14px; color: var(--muted); }
        input, select, textarea, button {
            font-family: inherit;
            font-size: 15px;
            border-radius: 8px;
        }
        input, select, textarea {
            width: 100%;
            border: 1px solid #c9d2dc;
            padding: 9px 10px;
            background: #fff;
        }
        textarea { min-height: 100px; }
        button {
            border: 0;
            background: var(--primary);
            color: #fff;
            padding: 9px 12px;
            cursor: pointer;
        }
        button.warn { background: var(--warn); }
        button.danger { background: var(--danger); }
        button.secondary { background: #4c6278; }
        .btn-link {
            display: inline-block;
            background: #4c6278;
            color: #fff;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 8px;
        }
        .table-wrap { overflow: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            border-bottom: 1px solid #e7edf3;
            padding: 10px;
            text-align: left;
            white-space: nowrap;
            vertical-align: top;
        }
        th { background: #f9fbfc; font-weight: 700; }
        .badge {
            padding: 3px 8px;
            border-radius: 16px;
            color: #fff;
            font-size: 13px;
        }
        .badge.red { background: var(--danger); }
        .badge.yellow { background: var(--warn); }
        .badge.green { background: var(--ok); }
        .badge.gray { background: #6b7280; }
        .flash {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .flash.success { background: #e7f7ec; color: #175c31; }
        .flash.error { background: #fdeaea; color: #7f1d1d; }
        .hint { color: var(--muted); font-size: 13px; }
        .screen-kpi {
            font-size: 34px;
            font-weight: 700;
        }
        .notice-box {
            position: relative;
            color: #fff;
        }
        .notice-box summary {
            list-style: none;
            cursor: pointer;
            user-select: none;
            padding: 3px 8px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 18px;
            font-size: 13px;
        }
        .notice-box[open] summary {
            background: rgba(255,255,255,0.15);
        }
        .notice-panel {
            position: absolute;
            right: 0;
            top: 36px;
            width: 340px;
            max-height: 340px;
            overflow: auto;
            background: #fff;
            color: #1f2937;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.28);
            padding: 10px;
            z-index: 40;
        }
        .notice-item {
            border-bottom: 1px solid #eef2f7;
            padding: 8px 4px;
        }
        .notice-item:last-child { border-bottom: 0; }
        .notice-title { font-weight: 700; font-size: 14px; }
        .notice-meta { color: #6b7280; font-size: 12px; margin-top: 3px; }
        .modal-mask {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 60;
        }
        .modal-box {
            background: #fff;
            width: min(560px, 92vw);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.28);
        }
        .modal-box ul { margin: 8px 0 0 18px; }
        .modal-box li { margin-bottom: 6px; }
        @media (max-width: 780px) {
            body { font-size: 15px; }
            .container { padding: 12px; }
            .nav .inner { gap: 8px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
<?php if (!empty($authUser)): ?>
<nav class="nav">
    <div class="inner">
        <span class="brand"><?= e($appName ?? "\u{793E}\u{533A}\u{5065}\u{5EB7}\u{7BA1}\u{7406}\u{5E73}\u{53F0}") ?></span>
        <a href="/index.php?r=dashboard">工作台</a>
        <a href="/index.php?r=grids">网格</a>
        <a href="/index.php?r=families">家庭</a>
        <a href="/index.php?r=residents">居民</a>
        <a href="/index.php?r=measurements">采集</a>
        <a href="/index.php?r=risks">评估</a>
        <a href="/index.php?r=alerts">预警</a>
        <a href="/index.php?r=interventions">干预</a>
        <a href="/index.php?r=followups">随访</a>
        <a href="/index.php?r=referrals">转诊</a>
        <a href="/index.php?r=reports">报表</a>
        <a href="/index.php?r=screen">大屏</a>
        <?php if (($authUser['role'] ?? '') === 'admin'): ?>
            <a href="/index.php?r=system_users">系统</a>
        <?php endif; ?>
        <span class="grow"></span>
        <details class="notice-box">
            <summary>消息(<?= (int)($notificationUnread ?? 0) ?>)</summary>
            <div class="notice-panel">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $item): ?>
                        <div class="notice-item">
                            <div class="notice-title">
                                <?= e($item['title']) ?>
                                <?php if (($item['status'] ?? '') === 'unread'): ?>
                                    <span class="badge red">新</span>
                                <?php endif; ?>
                            </div>
                            <div><?= e($item['content']) ?></div>
                            <div class="notice-meta">
                                <?= e($item['resident_name'] ?: '-') ?> | <?= e($item['created_at']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="hint">暂无消息</div>
                <?php endif; ?>
                <div style="margin-top:8px;">
                    <a class="btn-link" href="/index.php?r=notifications_read">全部标记已读</a>
                </div>
            </div>
        </details>
        <span><?= e(($authUser['real_name'] ?? '') . ' (' . ($authUser['role'] ?? '') . ')') ?></span>
        <a href="/index.php?r=logout">退出</a>
    </div>
</nav>
<?php endif; ?>
<div class="container<?= empty($authUser) ? ' login-wrap' : '' ?>">
<?php if (!empty($flash)): ?>
    <div class="flash <?= e($flash['type'] ?? 'success') ?>"><?= e($flash['message'] ?? '') ?></div>
<?php endif; ?>

<?php if (!empty($autoAlertPopup) && is_array($autoAlertPopup)): ?>
<div id="alertPopupMask" class="modal-mask">
    <div class="modal-box">
        <h2>自动预警提示</h2>
        <div>以下对象已触发黄/红线预警，请尽快处理：</div>
        <ul>
            <?php foreach ($autoAlertPopup as $item): ?>
                <li><?= e($item) ?></li>
            <?php endforeach; ?>
        </ul>
        <div style="margin-top: 12px;" class="inline">
            <a class="btn-link" href="/index.php?r=alerts">前往预警处理</a>
            <button type="button" class="secondary" onclick="document.getElementById('alertPopupMask').style.display='none'">我知道了</button>
        </div>
    </div>
</div>
<script>
window.addEventListener('load', function () {
    var m = document.getElementById('alertPopupMask');
    if (m) { m.style.display = 'flex'; }
});
</script>
<?php endif; ?>
