<h1>规范化随访管理</h1>

<div class="grid grid-2">
    <div class="card">
        <h2>新建随访计划</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_followup">
            <select name="resident_id" required>
                <option value="">选择居民</option>
                <?php foreach ($residents as $r): ?>
                    <option value="<?= e($r['id']) ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>" required>
            <button type="submit">生成计划</button>
        </form>
    </div>

    <div class="card">
        <h2>说明</h2>
        <p>支持“计划-执行-再计划”闭环，异常自动触发转诊流程。</p>
        <p>完成随访时可勾选“异常”，系统会生成转诊申请并留痕。</p>
    </div>
</div>

<div class="card">
    <h2>随访记录</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>居民</th><th>计划日期</th><th>完成日期</th><th>状态</th><th>结果</th><th>下次日期</th><th>操作</th></tr>
            <?php foreach ($followups as $f): ?>
                <tr>
                    <td><?= e($f['id']) ?></td>
                    <td><?= e($f['resident_name']) ?></td>
                    <td><?= e(fmt_date($f['due_date'])) ?></td>
                    <td><?= e(fmt_date($f['visit_date'])) ?></td>
                    <td>
                        <?php if ($f['status'] === 'done'): ?>
                            <span class="badge green">已完成</span>
                        <?php elseif ($f['due_date'] < date('Y-m-d')): ?>
                            <span class="badge yellow">逾期</span>
                        <?php else: ?>
                            <span class="badge red">待执行</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($f['result_summary']) ?></td>
                    <td><?= e(fmt_date($f['next_due_date'])) ?></td>
                    <td>
                        <?php if ($f['status'] !== 'done'): ?>
                            <form method="post" class="stack" style="min-width:260px;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="complete_followup">
                                <input type="hidden" name="followup_id" value="<?= e($f['id']) ?>">
                                <input type="date" name="visit_date" value="<?= e(date('Y-m-d')) ?>" required>
                                <textarea name="result_summary" placeholder="随访记录"></textarea>
                                <input type="date" name="next_due_date" placeholder="下次随访日期">
                                <label><input type="checkbox" name="abnormal" value="1">本次指标异常，触发转诊</label>
                                <button type="submit">完成随访</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
