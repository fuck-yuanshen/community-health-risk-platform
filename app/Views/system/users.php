<h1>系统管理 - 用户与权限</h1>

<div class="grid grid-2">
    <div class="card">
        <h2>新增用户</h2>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_user">
            <input name="username" placeholder="登录名" required>
            <input name="real_name" placeholder="姓名" required>
            <input name="password" placeholder="初始密码" required>
            <select name="role" required>
                <option value="doctor">社区医生</option>
                <option value="public_health">公卫医师</option>
                <option value="nurse">护士</option>
                <option value="admin">管理员</option>
            </select>
            <select name="community_id">
                <option value="">不限制社区（管理员可用）</option>
                <?php foreach ($communities as $c): ?>
                    <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">创建用户</button>
        </form>
    </div>

    <div class="card">
        <h2>说明</h2>
        <p>支持管理员、社区医生、公卫医师、护士分级权限。</p>
        <p>非管理员默认按社区隔离数据范围。</p>
        <p class="hint">安全要求：密码使用哈希，隐私字段加密存储。</p>
    </div>
</div>

<div class="card">
    <h2>用户列表</h2>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>用户名</th><th>姓名</th><th>角色</th><th>社区</th><th>状态</th><th>操作</th></tr>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['id']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['real_name']) ?></td>
                    <td><?= e($u['role']) ?></td>
                    <td><?= e($u['community_name'] ?: '-') ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge green">启用</span>' : '<span class="badge gray">停用</span>' ?></td>
                    <td>
                        <form method="post" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="toggle_user">
                            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                            <button type="submit" class="secondary">切换状态</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card inline">
    <a class="btn-link" href="/index.php?r=system_settings">进入系统配置</a>
    <a class="btn-link" href="/index.php?r=system_logs">查看日志与备份</a>
</div>
