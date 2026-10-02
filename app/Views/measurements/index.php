<h1>多维风险数据采集</h1>

<div class="grid grid-2">
    <div class="card">
        <h2>新增体检数据</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_measurement">
            <select name="resident_id" required>
                <option value="">选择居民</option>
                <?php foreach ($residents as $r): ?>
                    <option value="<?= e($r['id']) ?>"><?= e($r['name'] . '（' . $r['location'] . '）') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="check_date" value="<?= e(date('Y-m-d')) ?>" required>

            <div class="grid grid-3">
                <div><label>空腹血糖 mmol/L</label><input name="fasting_glucose" type="number" step="0.1"></div>
                <div><label>OGTT 2h mmol/L</label><input name="ogtt_2h" type="number" step="0.1"></div>
                <div><label>HbA1c %</label><input name="hba1c" type="number" step="0.1"></div>
                <div><label>收缩压 mmHg</label><input name="sbp" type="number" step="1"></div>
                <div><label>舒张压 mmHg</label><input name="dbp" type="number" step="1"></div>
                <div><label>BMI</label><input name="bmi" type="number" step="0.1"></div>
                <div><label>甘油三酯</label><input name="triglyceride" type="number" step="0.1"></div>
                <div><label>LDL</label><input name="ldl" type="number" step="0.1"></div>
                <div><label>来源</label><input name="source" value="门诊录入"></div>
            </div>

            <div class="inline">
                <label><input type="checkbox" name="family_history" value="1">家族史</label>
                <label><input type="checkbox" name="smoker" value="1">吸烟</label>
                <label><input type="checkbox" name="drinker" value="1">饮酒</label>
                <label><input type="checkbox" name="inactivity" value="1">缺乏运动</label>
            </div>
            <button type="submit">保存并自动评估</button>
        </form>
    </div>

    <div class="card">
        <h2>体检批量导入</h2>
        <p class="hint">格式：居民ID,日期,FPG,OGTT,HbA1c,SBP,DBP,BMI,TG,LDL,家族史,吸烟,饮酒,缺乏运动,来源</p>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="batch_import">
            <textarea name="batch_text" placeholder="1,2026-02-10,6.4,8.2,5.9,138,86,25.1,1.8,3.2,1,0,0,1,社区筛查\n2,2026-02-10,8.9,12.1,7.3,162,96,30.2,2.9,4.0,1,1,1,1,上门随访"></textarea>
            <button type="submit">导入并评估</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>近期采集记录</h2>
    <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th><th>居民</th><th>日期</th><th>FPG</th><th>血压</th><th>BMI</th>
                <th>风险分值</th><th>预警线</th><th>管理等级</th>
            </tr>
            <?php foreach ($measurements as $m): ?>
                <tr>
                    <td><?= e($m['id']) ?></td>
                    <td><?= e($m['resident_name']) ?></td>
                    <td><?= e(fmt_date($m['check_date'])) ?></td>
                    <td><?= e($m['fasting_glucose']) ?></td>
                    <td><?= e($m['sbp'] . '/' . $m['dbp']) ?></td>
                    <td><?= e($m['bmi']) ?></td>
                    <td><?= e($m['score'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($m['line_color'])): ?>
                            <span class="badge <?= e($m['line_color']) ?>"><?= e(strtoupper($m['line_color'])) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['management_level'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
