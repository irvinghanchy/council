-- 線上議事簽到與表決系統 Schema
-- 使用 utf8mb4 支援全形中文

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS council_vote CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE council_vote;

-- ─── 主辦人帳號 ───────────────────────────────────────────
CREATE TABLE hosts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(200) UNIQUE,          -- NULL 表示 admin（用密碼登入）
    is_admin      TINYINT(1) DEFAULT 0,
    password_hash VARCHAR(255),                 -- 只有 admin 使用
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 預設 admin（密碼：admin1234，安裝後請立即更改）
INSERT INTO hosts (name, email, is_admin, password_hash)
VALUES ('系統管理員', NULL, 1, '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- 上方 hash 對應明文 'password'，請用 password_hash() 替換

-- ─── 會議 ─────────────────────────────────────────────────
CREATE TABLE meeting (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(300) NOT NULL,
    location   VARCHAR(300),
    start_time DATETIME,
    reason     TEXT,
    status     ENUM('preparing','active','ended') DEFAULT 'preparing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 與會人員 ─────────────────────────────────────────────
CREATE TABLE members (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    email      VARCHAR(200) NOT NULL,
    name       VARCHAR(100) NOT NULL,
    position   VARCHAR(150),
    member_no  VARCHAR(30),
    type       ENUM('attendee','observer') DEFAULT 'attendee',
    UNIQUE KEY uq_email_meeting (email, meeting_id),
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 簽到記錄 ─────────────────────────────────────────────
CREATE TABLE attendance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    meeting_id   INT NOT NULL,
    signed_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attend (member_id, meeting_id),
    FOREIGN KEY (member_id)  REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 目前階段控制（每場會議唯一一筆） ────────────────────
CREATE TABLE phase_control (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id     INT NOT NULL UNIQUE,
    phase_type     ENUM('standby','agenda','resolution','election','temp_motion','ended') DEFAULT 'standby',
    agenda_item_id INT DEFAULT NULL,
    version        INT DEFAULT 1,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 議程項目 ─────────────────────────────────────────────
CREATE TABLE agenda_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id  INT NOT NULL,
    type        ENUM('report','resolution','election','temp') NOT NULL,
    title       VARCHAR(500) NOT NULL,
    description TEXT,
    order_no    INT DEFAULT 0,
    status      ENUM('pending','open','closed') DEFAULT 'pending',
    source      ENUM('preset','host_added','motion') DEFAULT 'preset',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 案由表決設定 ─────────────────────────────────────────
CREATE TABLE resolutions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL UNIQUE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 表決記錄 ─────────────────────────────────────────────
CREATE TABLE votes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL,
    member_id      INT NOT NULL,
    vote           ENUM('yes','no','abstain') NOT NULL,
    voted_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (agenda_item_id, member_id),
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)      REFERENCES members(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 選舉設定 ─────────────────────────────────────────────
CREATE TABLE elections (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL UNIQUE,
    seats          INT NOT NULL DEFAULT 1,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 候選人 ───────────────────────────────────────────────
CREATE TABLE candidates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    member_id   INT DEFAULT NULL,               -- 若從議員快速加入則有值
    is_elected  TINYINT(1) DEFAULT 0,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)   REFERENCES members(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 選舉投票記錄 ─────────────────────────────────────────
CREATE TABLE election_votes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    election_id  INT NOT NULL,
    member_id    INT NOT NULL,
    candidate_id INT NOT NULL,
    voted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_elec_vote (election_id, member_id, candidate_id),
    FOREIGN KEY (election_id)  REFERENCES elections(id)  ON DELETE CASCADE,
    FOREIGN KEY (member_id)    REFERENCES members(id)    ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 發言申請佇列 ─────────────────────────────────────────
CREATE TABLE speech_queue (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id     INT NOT NULL,
    agenda_item_id INT DEFAULT NULL,
    member_id      INT NOT NULL,
    status         ENUM('waiting','speaking','done','cancelled','removed') DEFAULT 'waiting',
    requested_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)  REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 臨時動議 ─────────────────────────────────────────────
CREATE TABLE temp_motions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id     INT NOT NULL,
    member_id      INT DEFAULT NULL,            -- NULL 表示主辦人直接新增
    content        TEXT NOT NULL,
    status         ENUM('pending','accepted','rejected') DEFAULT 'pending',
    agenda_item_id INT DEFAULT NULL,            -- 受理後建立的議程
    submitted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)  REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 會議紀錄流水帳 ───────────────────────────────────────
CREATE TABLE meeting_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meeting(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
