SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS communities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'public_health', 'nurse') NOT NULL,
    real_name VARCHAR(80) NOT NULL,
    community_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grids (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    responsible_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_grids_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_grids_user FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_grids_name (community_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buildings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grid_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_buildings_grid FOREIGN KEY (grid_id) REFERENCES grids(id) ON DELETE CASCADE,
    UNIQUE KEY uk_buildings_name (grid_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS families (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    building_id INT UNSIGNED NOT NULL,
    address VARCHAR(255) NOT NULL,
    head_name VARCHAR(80) NOT NULL,
    head_phone_enc TEXT NULL,
    archive_status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_families_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS residents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    gender ENUM('男', '女') NOT NULL,
    birth_date DATE NOT NULL,
    id_card_enc TEXT NULL,
    phone_enc TEXT NULL,
    address VARCHAR(255) NOT NULL,
    chronic_history TEXT NULL,
    archived_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_residents_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    INDEX idx_resident_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS measurements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    check_date DATE NOT NULL,
    fasting_glucose DECIMAL(5,2) DEFAULT 0,
    ogtt_2h DECIMAL(5,2) DEFAULT 0,
    hba1c DECIMAL(4,2) DEFAULT 0,
    sbp SMALLINT DEFAULT 0,
    dbp SMALLINT DEFAULT 0,
    bmi DECIMAL(4,1) DEFAULT 0,
    triglyceride DECIMAL(5,2) DEFAULT 0,
    ldl DECIMAL(5,2) DEFAULT 0,
    family_history TINYINT(1) DEFAULT 0,
    smoker TINYINT(1) DEFAULT 0,
    drinker TINYINT(1) DEFAULT 0,
    inactivity TINYINT(1) DEFAULT 0,
    source VARCHAR(80) DEFAULT '门诊录入',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_measurements_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    INDEX idx_measurements_resident_date (resident_id, check_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS risk_assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    measurement_id INT UNSIGNED NOT NULL,
    score SMALLINT NOT NULL,
    line_color ENUM('green', 'yellow', 'red') NOT NULL,
    management_level ENUM('Ⅰ', 'Ⅱ', 'Ⅲ', 'Ⅳ') NOT NULL,
    prediabetes TINYINT(1) NOT NULL DEFAULT 0,
    reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_risk_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_risk_measurement FOREIGN KEY (measurement_id) REFERENCES measurements(id) ON DELETE CASCADE,
    INDEX idx_risk_resident (resident_id),
    INDEX idx_risk_line (line_color)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    risk_assessment_id INT UNSIGNED NOT NULL,
    alert_line ENUM('yellow', 'red') NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status ENUM('pending', 'handled') NOT NULL DEFAULT 'pending',
    handled_by INT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_risk FOREIGN KEY (risk_assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_user FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_alert_status (status),
    INDEX idx_alert_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interventions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    risk_assessment_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    plan_content TEXT NOT NULL,
    status ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
    executor_id INT UNSIGNED NULL,
    feedback TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_intervention_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_intervention_risk FOREIGN KEY (risk_assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE,
    CONSTRAINT fk_intervention_user FOREIGN KEY (executor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_intervention_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS followups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    visit_date DATE NULL,
    result_summary TEXT NULL,
    next_due_date DATE NULL,
    status ENUM('pending', 'done', 'overdue') NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_followup_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_followup_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_followup_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    followup_id INT UNSIGNED NULL,
    reason TEXT NOT NULL,
    status ENUM('applied', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'applied',
    apply_time DATETIME NOT NULL,
    review_time DATETIME NULL,
    result_note TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_referral_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_referral_followup FOREIGN KEY (followup_id) REFERENCES followups(id) ON DELETE SET NULL,
    CONSTRAINT fk_referral_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_referral_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT UNSIGNED NOT NULL,
    task_type VARCHAR(80) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'done') NOT NULL DEFAULT 'pending',
    assignee_id INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_user FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_task_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    resident_id INT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(120) NOT NULL,
    content VARCHAR(255) NOT NULL,
    status ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
    INDEX idx_notification_user_status (user_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    target_type VARCHAR(80) NULL,
    target_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_backups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backup_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
