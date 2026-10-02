<div class="card">
    <h1>社区健康管理平台登录</h1>
    <p class="hint">账号由本地初始化脚本创建，密码请使用环境变量配置。</p>
    <form method="post" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div>
            <label>用户名</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">登录系统</button>
    </form>
</div>
