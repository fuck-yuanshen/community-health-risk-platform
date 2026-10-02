PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS communities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL,
    real_name TEXT NOT NULL,
    community_id INTEGER NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS grids (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    community_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    responsible_user_id INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (community_id, name)
);

CREATE TABLE IF NOT EXISTS buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grid_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grid_id) REFERENCES grids(id) ON DELETE CASCADE,
    UNIQUE (grid_id, name)
);

CREATE TABLE IF NOT EXISTS families (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    building_id INTEGER NOT NULL,
    address TEXT NOT NULL,
    head_name TEXT NOT NULL,
    head_phone_enc TEXT NULL,
    archive_status TEXT NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS residents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    gender TEXT NOT NULL,
    birth_date TEXT NOT NULL,
    id_card_enc TEXT NULL,
    phone_enc TEXT NULL,
    address TEXT NOT NULL,
    chronic_history TEXT NULL,
    archived_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_resident_name ON residents(name);

CREATE TABLE IF NOT EXISTS measurements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    check_date TEXT NOT NULL,
    fasting_glucose REAL DEFAULT 0,
    ogtt_2h REAL DEFAULT 0,
    hba1c REAL DEFAULT 0,
    sbp INTEGER DEFAULT 0,
    dbp INTEGER DEFAULT 0,
    bmi REAL DEFAULT 0,
    triglyceride REAL DEFAULT 0,
    ldl REAL DEFAULT 0,
    family_history INTEGER DEFAULT 0,
    smoker INTEGER DEFAULT 0,
    drinker INTEGER DEFAULT 0,
    inactivity INTEGER DEFAULT 0,
    source TEXT DEFAULT '门诊录入',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_measurements_resident_date ON measurements(resident_id, check_date);

CREATE TABLE IF NOT EXISTS risk_assessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    measurement_id INTEGER NOT NULL,
    score INTEGER NOT NULL,
    line_color TEXT NOT NULL,
    management_level TEXT NOT NULL,
    prediabetes INTEGER NOT NULL DEFAULT 0,
    reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (measurement_id) REFERENCES measurements(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_risk_resident ON risk_assessments(resident_id);
CREATE INDEX IF NOT EXISTS idx_risk_line ON risk_assessments(line_color);

CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    risk_assessment_id INTEGER NOT NULL,
    alert_line TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    handled_by INTEGER NULL,
    handled_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (risk_assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_alert_status ON alerts(status);
CREATE INDEX IF NOT EXISTS idx_alert_created ON alerts(created_at);

CREATE TABLE IF NOT EXISTS interventions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    risk_assessment_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    plan_content TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'todo',
    executor_id INTEGER NULL,
    feedback TEXT NULL,
    attachment_path TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (risk_assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (executor_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_intervention_status ON interventions(status);

CREATE TABLE IF NOT EXISTS followups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    due_date TEXT NOT NULL,
    visit_date TEXT NULL,
    result_summary TEXT NULL,
    next_due_date TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_followup_due ON followups(due_date);

CREATE TABLE IF NOT EXISTS referrals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    followup_id INTEGER NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'applied',
    apply_time DATETIME NOT NULL,
    review_time DATETIME NULL,
    result_note TEXT NULL,
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (followup_id) REFERENCES followups(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_referral_status ON referrals(status);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resident_id INTEGER NOT NULL,
    task_type TEXT NOT NULL,
    due_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    assignee_id INTEGER NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_task_due ON tasks(due_date);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    resident_id INTEGER NULL,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'unread',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_notification_user_status ON notifications(user_id, status, created_at);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action TEXT NOT NULL,
    target_type TEXT NULL,
    target_id INTEGER NULL,
    ip TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_log_created ON audit_logs(created_at);

CREATE TABLE IF NOT EXISTS data_backups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_path TEXT NOT NULL,
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
