<h1>操作日志与数据备份</h1>

<div class="card">
    <h2>数据备份</h2>
    <form method="post" action="/index.php?r=system_backup" class="inline">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">立即备份数据库</button>
    </form>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>备份记录</h2>
        <div class="table-wrap">
            <table>
                <tr><th>ID</th><th>文件</th><th>时间</th></tr>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td><?= e($b['id']) ?></td>
                        <td><?= e($b['file_path']) ?></td>
                        <td><?= e($b['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>审计日志</h2>
        <div class="table-wrap" style="max-height:520px;">
            <table>
                <tr><th>时间</th><th>用户</th><th>动作</th><th>对象</th><th>IP</th></tr>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['real_name'] ?: '-') ?></td>
                        <td><?= e($log['action']) ?></td>
                        <td><?= e($log['target_type'] . '#' . ($log['target_id'] ?: '-')) ?></td>
                        <td><?= e($log['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div class="card inline">
    <a class="btn-link" href="/index.php?r=system_users">返回系统管理</a>
</div>
