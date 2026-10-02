<h1>社区风险全景大屏</h1>

<div class="grid grid-3">
    <div class="card"><h3>今日新增预警</h3><div class="screen-kpi"><?= e($todayAlerts) ?></div></div>
    <div class="card"><h3>高危人群（红线）</h3><div class="screen-kpi" style="color:#bb1e10"><?= e($highRisk) ?></div></div>
    <div class="card"><h3>待处理预警</h3><div class="screen-kpi" style="color:#bb1e10"><?= e($pendingAlerts) ?></div></div>
    <div class="card"><h3>随访完成率</h3><div class="screen-kpi"><?= e($followupRate) ?>%</div></div>
    <div class="card"><h3>干预覆盖率</h3><div class="screen-kpi"><?= e($coverageRate) ?>%</div></div>
    <div class="card"><h3>逾期随访</h3><div class="screen-kpi" style="color:#c58300"><?= e($overdueFollowups) ?></div></div>
    <div class="card"><h3>绿线人数</h3><div class="screen-kpi" style="color:#1a7f37"><?= e($risk['green']) ?></div></div>
    <div class="card"><h3>黄线人数</h3><div class="screen-kpi" style="color:#c58300"><?= e($risk['yellow']) ?></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>风险人群分布</h2>
        <canvas id="screenRiskChart" height="190"></canvas>
    </div>
    <div class="card">
        <h2>网格-家庭-个人一张图（列表化）</h2>
        <div class="table-wrap" style="max-height:420px;">
            <table>
                <tr><th>社区</th><th>网格</th><th>楼栋</th><th>家庭</th><th>居民</th><th>风险</th></tr>
                <?php foreach ($mapItems as $m): ?>
                    <tr>
                        <td><?= e($m['community_name']) ?></td>
                        <td><?= e($m['grid_name']) ?></td>
                        <td><?= e($m['building_name']) ?></td>
                        <td><?= e($m['family_address']) ?></td>
                        <td><?= e($m['resident_name']) ?></td>
                        <td><span class="badge <?= e($m['line_color']) ?>"><?= e(strtoupper($m['line_color'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <h2>重点问题自动识别</h2>
    <ul>
        <?php foreach ($issues as $issue): ?>
            <li><?= e($issue) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<script>
new Chart(document.getElementById('screenRiskChart'), {
    type: 'pie',
    data: {
        labels: ['绿线', '黄线', '红线'],
        datasets: [{
            data: [<?= (int)$risk['green'] ?>, <?= (int)$risk['yellow'] ?>, <?= (int)$risk['red'] ?>],
            backgroundColor: ['#1a7f37', '#c58300', '#bb1e10']
        }]
    }
});
setInterval(() => location.reload(), 120000);
</script>
