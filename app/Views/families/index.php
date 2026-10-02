<h1>家庭健康档案管理</h1>

<div class="grid grid-2">
    <div class="card">
        <h2>创建家庭档案</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_family">
            <select name="building_id" required>
                <option value="">选择楼栋</option>
                <?php foreach ($buildings as $b): ?>
                    <option value="<?= e($b['id']) ?>"><?= e($b['community_name'] . ' / ' . $b['grid_name'] . ' / ' . $b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="address" placeholder="家庭地址，例如：5号楼2单元301" required>
            <input name="head_name" placeholder="户主姓名" required>
            <input name="head_phone" placeholder="户主电话">
            <button type="submit">创建</button>
        </form>
    </div>

    <div class="card">
        <h2>说明</h2>
        <p>实现“一户一档”，家庭成员风险自动聚合展示。</p>
        <p>字段中含手机号等隐私信息，系统以加密方式存储。</p>
    </div>
</div>

<div class="card">
    <h2>家庭档案列表</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>社区/网格/楼栋</th><th>地址</th><th>户主</th><th>电话</th><th>成员数</th><th>红线人数</th><th>状态</th><th>操作</th></tr>
            <?php foreach ($families as $f): ?>
                <tr>
                    <td><?= e($f['id']) ?></td>
                    <td><?= e($f['community_name'] . ' / ' . $f['grid_name'] . ' / ' . $f['building_name']) ?></td>
                    <td><?= e($f['address']) ?></td>
                    <td><?= e($f['head_name']) ?></td>
                    <td><?= e($f['head_phone']) ?></td>
                    <td><?= e($f['member_count']) ?></td>
                    <td><span class="badge red"><?= e($f['red_members'] ?: 0) ?></span></td>
                    <td>
                        <?php if ($f['archive_status'] === 'active'): ?>
                            <span class="badge green">有效</span>
                        <?php else: ?>
                            <span class="badge gray">已归档</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['archive_status'] === 'active'): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="archive_family">
                                <input type="hidden" name="family_id" value="<?= e($f['id']) ?>">
                                <button class="warn" type="submit">归档</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
