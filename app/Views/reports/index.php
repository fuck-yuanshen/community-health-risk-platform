<h1>公卫考核自动统计与报表</h1>

<div class="card inline">
    <a class="btn-link" href="/index.php?r=reports&export=excel">导出 Excel(CSV)</a>
    <a class="btn-link" href="/index.php?r=reports&export=pdf" target="_blank">导出 PDF(打印)</a>
</div>

<div class="grid grid-3">
    <div class="card"><h3>筛查率</h3><div class="stat"><?= e($metrics['screening_rate']) ?>%</div></div>
    <div class="card"><h3>管理率</h3><div class="stat"><?= e($metrics['management_rate']) ?>%</div></div>
    <div class="card"><h3>随访率</h3><div class="stat"><?= e($metrics['followup_rate']) ?>%</div></div>
    <div class="card"><h3>控制率</h3><div class="stat"><?= e($metrics['control_rate']) ?>%</div></div>
    <div class="card"><h3>预警处理率</h3><div class="stat"><?= e($metrics['alert_handle_rate']) ?>%</div></div>
    <div class="card"><h3>在管居民</h3><div class="stat"><?= e($metrics['total_residents']) ?></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>风险等级分布</h2>
        <canvas id="riskDistChart" height="160"></canvas>
    </div>
    <div class="card">
        <h2>近14天新增预警趋势</h2>
        <canvas id="alertTrendChart" height="160"></canvas>
    </div>
</div>

<div class="card">
    <h2>社区管理效果对比</h2>
    <div class="table-wrap">
        <table>
            <tr><th>社区</th><th>居民数</th><th>红线人数</th><th>红线占比</th></tr>
            <?php foreach ($communityCompare as $row): ?>
                <?php $ratio = ((int)$row['resident_count'] > 0) ? round((int)$row['red_count'] / (int)$row['resident_count'] * 100, 2) : 0; ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['resident_count']) ?></td>
                    <td><?= e($row['red_count']) ?></td>
                    <td><?= e($ratio) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>国家基本公卫糖尿病管理标准报表（简版）</h2>
        <div class="table-wrap">
            <table>
                <?php foreach ($nationalTemplate as $idx => $row): ?>
                    <?php if ($idx === 0): ?>
                        <tr>
                            <th><?= e($row[0]) ?></th>
                            <th><?= e($row[1]) ?></th>
                            <th><?= e($row[2]) ?></th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td><?= e($row[0]) ?></td>
                            <td><?= e($row[1]) ?></td>
                            <td><?= e($row[2]) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>干预成效分析</h2>
        <div class="table-wrap">
            <table>
                <tr><th>指标</th><th>数值</th></tr>
                <tr><td>追踪人数</td><td><?= e($interventionEffect['tracked_count']) ?></td></tr>
                <tr><td>改善人数</td><td><?= e($interventionEffect['improved_count']) ?></td></tr>
                <tr><td>恶化人数</td><td><?= e($interventionEffect['worsened_count']) ?></td></tr>
                <tr><td>持平人数</td><td><?= e($interventionEffect['stable_count']) ?></td></tr>
                <tr><td>改善率</td><td><?= e($interventionEffect['improvement_rate']) ?>%</td></tr>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <h2>家庭医生/团队考核数据支撑</h2>
    <div class="table-wrap">
        <table>
            <tr><th>姓名</th><th>角色</th><th>任务总数</th><th>任务完成数</th><th>干预总数</th><th>干预完成数</th><th>创建随访数</th><th>处理预警数</th></tr>
            <?php foreach ($doctorPerformance as $row): ?>
                <tr>
                    <td><?= e($row['real_name']) ?></td>
                    <td><?= e($row['role']) ?></td>
                    <td><?= e($row['assigned_tasks']) ?></td>
                    <td><?= e($row['done_tasks']) ?></td>
                    <td><?= e($row['intervention_tasks']) ?></td>
                    <td><?= e($row['intervention_done']) ?></td>
                    <td><?= e($row['followups_created']) ?></td>
                    <td><?= e($row['handled_alerts']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<script>
new Chart(document.getElementById('riskDistChart'), {
    type: 'bar',
    data: {
        labels: ['绿线', '黄线', '红线'],
        datasets: [{
            label: '人数',
            data: [<?= (int)$metrics['risk_dist']['green'] ?>, <?= (int)$metrics['risk_dist']['yellow'] ?>, <?= (int)$metrics['risk_dist']['red'] ?>],
            backgroundColor: ['#1a7f37', '#c58300', '#bb1e10']
        }]
    }
});

new Chart(document.getElementById('alertTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend['labels'], JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: '新增预警',
            data: <?= json_encode($trend['data'], JSON_UNESCAPED_UNICODE) ?>,
            borderColor: '#1f4a6a',
            backgroundColor: 'rgba(31,74,106,0.18)',
            fill: true,
            tension: 0.25
        }]
    }
});
</script>
