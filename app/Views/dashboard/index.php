<h1>工作台总览</h1>
<div class="grid grid-3">
    <div class="card"><h3>居民总数</h3><div class="stat"><?= e($totalResidents) ?></div></div>
    <div class="card"><h3>家庭总数</h3><div class="stat"><?= e($totalFamilies) ?></div></div>
    <div class="card"><h3>今日新增预警</h3><div class="stat"><?= e($todayAlerts) ?></div></div>
    <div class="card"><h3>待处理预警</h3><div class="stat" style="color:#bb1e10"><?= e($pendingAlerts) ?></div></div>
    <div class="card"><h3>逾期随访</h3><div class="stat" style="color:#c58300"><?= e($overdueFollowups) ?></div></div>
    <div class="card"><h3>高危(红线)</h3><div class="stat" style="color:#bb1e10"><?= e($riskDist['red'] ?? 0) ?></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>风险分布</h2>
        <canvas id="riskChart" height="180"></canvas>
    </div>
    <div class="card">
        <h2>近期待办任务（7天）</h2>
        <div class="table-wrap">
            <table>
                <tr><th>到期日</th><th>居民</th><th>任务类型</th><th>责任人</th><th>备注</th></tr>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?= e(fmt_date($task['due_date'])) ?></td>
                        <td><?= e($task['resident_name']) ?></td>
                        <td><?= e($task['task_type']) ?></td>
                        <td><?= e($task['assignee_name'] ?: '未分配') ?></td>
                        <td><?= e($task['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('riskChart'), {
    type: 'doughnut',
    data: {
        labels: ['绿线', '黄线', '红线'],
        datasets: [{
            data: [<?= (int)($riskDist['green'] ?? 0) ?>, <?= (int)($riskDist['yellow'] ?? 0) ?>, <?= (int)($riskDist['red'] ?? 0) ?>],
            backgroundColor: ['#1a7f37', '#c58300', '#bb1e10']
        }]
    }
});
</script>
