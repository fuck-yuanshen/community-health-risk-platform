<h1>动态风险预警与任务提醒</h1>

<div class="card">
    <form method="get" class="inline">
        <input type="hidden" name="r" value="alerts">
        <select name="line">
            <option value="">全部预警线</option>
            <option value="yellow" <?= $line === 'yellow' ? 'selected' : '' ?>>黄线</option>
            <option value="red" <?= $line === 'red' ? 'selected' : '' ?>>红线</option>
        </select>
        <button type="submit" class="secondary">筛选</button>
        <?php if ($line): ?><a class="btn-link" href="/index.php?r=alerts">清空</a><?php endif; ?>
    </form>
    <form method="post" class="inline" style="margin-top:10px;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="one_click_alert_scan">
        <button type="submit">一键预警扫描</button>
        <span class="hint">自动扫描最新黄/红线且未生成待处理预警的居民</span>
    </form>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>预警列表</h2>
        <div class="table-wrap">
            <table>
                <tr><th>ID</th><th>居民</th><th>预警线</th><th>分值/等级</th><th>描述</th><th>状态</th><th>操作</th></tr>
                <?php foreach ($alerts as $a): ?>
                    <tr>
                        <td><?= e($a['id']) ?></td>
                        <td><?= e($a['resident_name']) ?></td>
                        <td><span class="badge <?= e($a['alert_line']) ?>"><?= e(strtoupper($a['alert_line'])) ?></span></td>
                        <td><?= e(($a['score'] ?? '-') . ' / ' . ($a['management_level'] ?? '-')) ?></td>
                        <td><?= e($a['description']) ?></td>
                        <td><?= $a['status'] === 'pending' ? '<span class="badge red">待处理</span>' : '<span class="badge green">已处理</span>' ?></td>
                        <td>
                            <?php if ($a['status'] === 'pending'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="handle_alert">
                                    <input type="hidden" name="alert_id" value="<?= e($a['id']) ?>">
                                    <button type="submit">处理完成</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>重点任务提醒</h2>
        <div class="table-wrap">
            <table>
                <tr><th>居民</th><th>任务类型</th><th>到期日</th><th>责任人</th><th>备注</th></tr>
                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td><?= e($t['resident_name']) ?></td>
                        <td><?= e($t['task_type']) ?></td>
                        <td><?= e(fmt_date($t['due_date'])) ?></td>
                        <td><?= e($t['assignee_name'] ?: '未分配') ?></td>
                        <td><?= e($t['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
