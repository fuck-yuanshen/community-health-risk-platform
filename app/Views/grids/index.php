<h1>社区网格精细化管理</h1>

<div class="grid grid-3">
    <?php if (($authUser['role'] ?? '') === 'admin'): ?>
    <div class="card">
        <h2>新增社区</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_community">
            <input name="name" placeholder="社区名称，例如：东风社区" required>
            <button type="submit">保存</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>新增网格</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_grid">
            <select name="community_id" required>
                <option value="">选择社区</option>
                <?php foreach ($communities as $c): ?>
                    <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="name" placeholder="网格名称，例如：第一网格" required>
            <select name="responsible_user_id">
                <option value="">责任人（可选）</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= e($u['id']) ?>"><?= e($u['real_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">保存</button>
        </form>
    </div>

    <div class="card">
        <h2>新增楼栋</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_building">
            <select name="grid_id" required>
                <option value="">选择网格</option>
                <?php foreach ($grids as $g): ?>
                    <option value="<?= e($g['id']) ?>"><?= e($g['community_name'] . ' / ' . $g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="name" placeholder="楼栋名称，例如：5号楼" required>
            <button type="submit">保存</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>网格列表</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>社区</th><th>网格</th><th>责任人</th></tr>
            <?php foreach ($grids as $g): ?>
                <tr>
                    <td><?= e($g['id']) ?></td>
                    <td><?= e($g['community_name']) ?></td>
                    <td><?= e($g['name']) ?></td>
                    <td><?= e($g['responsible_name'] ?: '未分配') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card">
    <h2>楼栋列表</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>社区</th><th>网格</th><th>楼栋</th></tr>
            <?php foreach ($buildings as $b): ?>
                <tr>
                    <td><?= e($b['id']) ?></td>
                    <td><?= e($b['community_name']) ?></td>
                    <td><?= e($b['grid_name']) ?></td>
                    <td><?= e($b['name']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
