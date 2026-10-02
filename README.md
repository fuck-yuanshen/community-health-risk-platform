# 社区健康管理平台（PHP + SQLite / MySQL）

按 `功能需求.md` 实现的糖尿病风险分线分级预警平台（MVP）。

## 1. 运行环境

- PHP 8.1+
- Web 服务器（Apache/Nginx）或 `php -S`
- 默认本地数据库：SQLite（无需单独安装）
- 可选：MySQL 8+

## 2. 配置

在系统环境变量中配置（可选，未配置则使用默认值）：

- `DB_DRIVER`（默认 `sqlite`）
- `DB_PATH`（默认 `项目根目录/community_health.sqlite`）
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_KEY`（建议使用 32 位以上随机字符串）

默认本地数据库文件：`community_health.sqlite`（位于项目根目录）。

## 3. 初始化数据库

```bash
php scripts/init_db.php
php scripts/migrate_partial_items.php
```

会自动：

- 创建数据库与全量表结构（SQLite 默认生成 `community_health.sqlite`）
- 写入风险阈值配置
- 写入示例中文数据（社区/网格/家庭/居民/体检/评估/预警/干预/随访/转诊）
- 写入默认账号

初始化后请在本地设置管理员和社区医生账号。仓库不保存固定密码。

## 4. 启动项目

```bash
php -S 127.0.0.1:8080 -t public
```

访问：<http://127.0.0.1:8080/index.php?r=login>

## 5. 模块对应关系

- 居民健康档案与家庭网格管理：`grids` / `families` / `residents`
- 风险采集与智能评估：`measurements` / `risks`
- 动态预警与提醒：`alerts`
- 分层分类干预：`interventions`
- 随访与转诊闭环：`followups` / `referrals`
- 公卫统计与报表：`reports`
- 数据可视化大屏：`screen`
- 系统管理与安全：`system_users` / `system_settings` / `system_logs`

## 6. 安全实现

- 密码：`password_hash`
- 身份证、手机号等敏感字段：AES 加密存储（`Crypto`）
- 操作审计：`audit_logs`
- 数据备份：系统管理页面一键导出 SQL 到 `storage/backups`

## 7. 说明

这是可运行的教学/课程项目 MVP，核心流程可用：

评估 -> 预警 -> 干预 -> 随访 -> 转诊 -> 再提醒。

## 8. 部署文档

- 小皮面板部署与扫码枪配置：`部署说明_小皮面板.md`
