<h1>系统配置与安全管理</h1>

<div class="card">
    <h2>风险分线阈值配置</h2>
    <form method="post" class="grid grid-3">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div>
            <label>黄线阈值（分）</label>
            <input name="line_yellow" type="number" step="1" value="<?= e($settings['line_yellow']) ?>" required>
        </div>
        <div>
            <label>红线阈值（分）</label>
            <input name="line_red" type="number" step="1" value="<?= e($settings['line_red']) ?>" required>
        </div>
        <div>
            <label>控制目标 FPG（mmol/L）</label>
            <input name="control_fpg_target" type="number" step="0.1" value="<?= e($settings['control_fpg_target']) ?>" required>
        </div>
        <div>
            <button type="submit">保存配置</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>安全说明</h2>
    <ul>
        <li>密码采用 `password_hash` 存储。</li>
        <li>手机号、身份证号等隐私字段采用 AES 加密。</li>
        <li>所有关键操作自动写入审计日志。</li>
    </ul>
</div>

<div class="card inline">
    <a class="btn-link" href="/index.php?r=system_users">返回用户管理</a>
    <a class="btn-link" href="/index.php?r=system_logs">查看日志与备份</a>
</div>
