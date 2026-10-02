<h1>风险分线分级评估</h1>

<div class="card">
    <form method="get" class="inline">
        <input type="hidden" name="r" value="risks">
        <select name="resident_id">
            <option value="">全部居民</option>
            <?php foreach ($residents as $r): ?>
                <option value="<?= e($r['id']) ?>" <?= (int)$residentId === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="secondary">筛选</button>
        <?php if ($residentId): ?><a class="btn-link" href="/index.php?r=risks">清空</a><?php endif; ?>
    </form>
</div>

<?php if ($residentId): ?>
<div class="card">
    <h2>个人风险趋势对比图</h2>
    <canvas id="trendChart" height="120"></canvas>
</div>
<?php endif; ?>

<div class="card">
    <h2>评估记录</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>居民</th><th>测量日期</th><th>风险分值</th><th>预警线</th><th>管理等级</th><th>前期识别</th><th>判定依据</th></tr>
            <?php foreach ($assessments as $a): ?>
                <tr>
                    <td><?= e($a['id']) ?></td>
                    <td><?= e($a['resident_name']) ?></td>
                    <td><?= e(fmt_date($a['check_date'])) ?></td>
                    <td><?= e($a['score']) ?></td>
                    <td><span class="badge <?= e($a['line_color']) ?>"><?= e(strtoupper($a['line_color'])) ?></span></td>
                    <td><?= e($a['management_level']) ?></td>
                    <td><?= $a['prediabetes'] ? '<span class="badge yellow">是</span>' : '否' ?></td>
                    <td><?= e($a['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php if ($residentId): ?>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: '风险分值',
            data: <?= json_encode($trendScores, JSON_UNESCAPED_UNICODE) ?>,
            borderColor: '#1f4a6a',
            backgroundColor: 'rgba(31,74,106,0.2)',
            tension: 0.25,
            fill: true
        }]
    },
    options: { scales: { y: { beginAtZero: true, suggestedMax: 100 } } }
});
</script>
<?php endif; ?>
