<h1>分层分类健康干预管理</h1>

<div class="card">
    <form method="get" class="inline">
        <input type="hidden" name="r" value="interventions">
        <select name="status">
            <option value="">全部状态</option>
            <option value="todo" <?= $status === 'todo' ? 'selected' : '' ?>>待执行</option>
            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>执行中</option>
            <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>已完成</option>
        </select>
        <button type="submit" class="secondary">筛选</button>
        <?php if ($status): ?><a class="btn-link" href="/index.php?r=interventions">清空</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>干预任务</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>居民</th><th>风险</th><th>类别</th><th>干预方案</th><th>执行人</th><th>状态与反馈</th></tr>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td><?= e($i['id']) ?></td>
                    <td><?= e($i['resident_name']) ?></td>
                    <td>
                        <?php if (!empty($i['line_color'])): ?>
                        <span class="badge <?= e($i['line_color']) ?>"><?= e(strtoupper($i['line_color'])) ?></span>
                        <?= e($i['management_level']) ?>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                    <td><?= e($i['category']) ?></td>
                    <td><?= e($i['plan_content']) ?></td>
                    <td><?= e($i['executor_name'] ?: '未分配') ?></td>
                    <td>
                        <form method="post" class="stack" style="min-width:260px;" enctype="multipart/form-data">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_intervention">
                            <input type="hidden" name="id" value="<?= e($i['id']) ?>">
                            <select name="status">
                                <option value="todo" <?= $i['status'] === 'todo' ? 'selected' : '' ?>>待执行</option>
                                <option value="in_progress" <?= $i['status'] === 'in_progress' ? 'selected' : '' ?>>执行中</option>
                                <option value="done" <?= $i['status'] === 'done' ? 'selected' : '' ?>>已完成</option>
                            </select>
                            <textarea name="feedback" placeholder="执行反馈"><?= e($i['feedback']) ?></textarea>
                            <input type="file" name="attachment">
                            <?php if (!empty($i['attachment_path'])): ?>
                                <a class="btn-link" href="<?= e($i['attachment_path']) ?>" target="_blank">查看已上传附件</a>
                            <?php endif; ?>
                            <button type="submit">保存</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
