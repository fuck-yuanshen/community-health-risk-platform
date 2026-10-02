<h1>居民健康档案管理</h1>

<div class="grid grid-2">
    <div class="card">
        <h2><?= $editResident ? '编辑居民档案' : '新增居民档案' ?></h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="<?= $editResident ? 'update_resident' : 'create_resident' ?>">
            <?php if ($editResident): ?>
                <input type="hidden" name="resident_id" value="<?= e($editResident['id']) ?>">
            <?php endif; ?>
            <select name="family_id" required <?= $editResident ? 'disabled' : '' ?>>
                <option value="">选择家庭</option>
                <?php foreach ($families as $f): ?>
                    <option value="<?= e($f['id']) ?>" <?= ($editResident && (int)$editResident['family_id'] === (int)$f['id']) ? 'selected' : '' ?>>
                        <?= e($f['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($editResident): ?>
                <input type="hidden" name="family_id" value="<?= e($editResident['family_id']) ?>">
            <?php endif; ?>
            <input name="name" placeholder="姓名" value="<?= e($editResident['name'] ?? '') ?>" required>
            <select name="gender" required>
                <option value="">性别</option>
                <option value="男" <?= (($editResident['gender'] ?? '') === '男') ? 'selected' : '' ?>>男</option>
                <option value="女" <?= (($editResident['gender'] ?? '') === '女') ? 'selected' : '' ?>>女</option>
            </select>
            <input type="date" name="birth_date" value="<?= e($editResident['birth_date'] ?? '') ?>" required>
            <input name="id_card" placeholder="身份证号" value="<?= e($editResident['id_card'] ?? '') ?>">
            <input name="phone" placeholder="手机号" value="<?= e($editResident['phone'] ?? '') ?>">
            <input name="address" placeholder="居住地址" value="<?= e($editResident['address'] ?? '') ?>">
            <textarea name="chronic_history" placeholder="慢病史/家族史说明"><?= e($editResident['chronic_history'] ?? '') ?></textarea>
            <button type="submit"><?= $editResident ? '保存修改' : '新增居民' ?></button>
        </form>
    </div>

    <div class="card">
        <h2>批量导入居民</h2>
        <p class="hint">格式：姓名,性别,出生日期,身份证,电话,地址,慢病史（一行一人）</p>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="import_residents">
            <select name="family_id" required>
                <option value="">选择家庭</option>
                <?php foreach ($families as $f): ?>
                    <option value="<?= e($f['id']) ?>"><?= e($f['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="batch_text" placeholder="张建国,男,1969-03-10,320...,139...,东风路8号,高血压\n李桂芬,女,1972-11-02,320...,138...,东风路8号,无"></textarea>
            <button type="submit">批量导入</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>居民列表</h2>
    <form method="get" class="inline">
        <input type="hidden" name="r" value="residents">
        <input name="keyword" value="<?= e($keyword) ?>" placeholder="按姓名/地址查询">
        <button type="submit" class="secondary">查询</button>
        <?php if ($keyword !== ''): ?><a class="btn-link" href="/index.php?r=residents">清空</a><?php endif; ?>
    </form>

    <div class="table-wrap" style="margin-top:10px;">
        <table>
            <tr>
                <th>ID</th><th>姓名</th><th>性别</th><th>出生日期</th><th>电话</th><th>地址</th>
                <th>社区/网格</th><th>最新风险</th><th>状态</th><th>操作</th>
            </tr>
            <?php foreach ($residents as $r): ?>
                <tr>
                    <td><?= e($r['id']) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['gender']) ?></td>
                    <td><?= e(fmt_date($r['birth_date'])) ?></td>
                    <td><?= e($r['phone']) ?></td>
                    <td><?= e($r['address']) ?></td>
                    <td><?= e($r['community_name'] . ' / ' . $r['grid_name']) ?></td>
                    <td>
                        <?php if ($r['latest_line']): ?>
                            <?php $line = $r['latest_line']; ?>
                            <span class="badge <?= e($line) ?>"><?= e(strtoupper($line)) ?></span>
                            分值 <?= e($r['latest_score']) ?> / 等级 <?= e($r['latest_level']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= $r['archived_at'] ? '<span class="badge gray">已归档</span>' : '<span class="badge green">在管</span>' ?></td>
                    <td class="inline">
                        <a class="btn-link" href="/index.php?r=residents&edit=<?= e($r['id']) ?>">编辑</a>
                        <?php if (!$r['archived_at']): ?>
                        <form method="post" class="inline" onsubmit="return confirm('确认归档该居民?')">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="archive_resident">
                            <input type="hidden" name="resident_id" value="<?= e($r['id']) ?>">
                            <button class="warn" type="submit">归档</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
