<h1>双向转诊闭环管理</h1>

<div class="card">
    <h2>转诊流程记录</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>居民</th><th>申请时间</th><th>原因</th><th>状态</th><th>结果回流</th><th>操作</th></tr>
            <?php foreach ($items as $rf): ?>
                <tr>
                    <td><?= e($rf['id']) ?></td>
                    <td><?= e($rf['resident_name']) ?></td>
                    <td><?= e($rf['apply_time']) ?></td>
                    <td><?= e($rf['reason']) ?></td>
                    <td>
                        <?php
                        $statusMap = ['applied' => '待审批', 'approved' => '已审批', 'completed' => '已完成', 'rejected' => '已驳回'];
                        echo e($statusMap[$rf['status']] ?? $rf['status']);
                        ?>
                    </td>
                    <td><?= e($rf['result_note']) ?></td>
                    <td>
                        <form method="post" class="stack" style="min-width:220px;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e($rf['id']) ?>">
                            <select name="status">
                                <option value="applied" <?= $rf['status'] === 'applied' ? 'selected' : '' ?>>待审批</option>
                                <option value="approved" <?= $rf['status'] === 'approved' ? 'selected' : '' ?>>已审批</option>
                                <option value="completed" <?= $rf['status'] === 'completed' ? 'selected' : '' ?>>已完成</option>
                                <option value="rejected" <?= $rf['status'] === 'rejected' ? 'selected' : '' ?>>已驳回</option>
                            </select>
                            <textarea name="result_note" placeholder="审批/回流结果说明"><?= e($rf['result_note']) ?></textarea>
                            <button type="submit">更新</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
