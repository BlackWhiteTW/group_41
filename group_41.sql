-- 數據庫初始化腳本
-- root / root123456

-- 檢查是否存在 group_41 數據庫，存在則刪除
DROP DATABASE IF EXISTS group_41;
CREATE DATABASE group_41 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE group_41;

-- 1. 用戶表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('member', 'owner', 'club_officer', 'admin') DEFAULT 'member' COMMENT '系統身份，社團角色請見 club_memberships',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 社團表
CREATE TABLE clubs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    owner_user_id INT NOT NULL COMMENT '社團持有人（建立者）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 社團成員關聯表（可多社團、多角色）
CREATE TABLE club_memberships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    club_id INT NOT NULL,
    role ENUM('member', 'owner', 'club_officer') NOT NULL COMMENT 'member: 成員, owner: 幹部, club_officer: 持有人',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_membership (user_id, club_id),
    INDEX idx_club (club_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 表單表
CREATE TABLE forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    creator_id INT NOT NULL,
    club_id INT NOT NULL COMMENT '發布社團',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    form_type ENUM('public', 'club_only') DEFAULT 'public' COMMENT '公開或限定社團',
    target_club_ids TEXT COMMENT 'club_only 可填寫的社團 ID（逗號分隔）',
    status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id),
    INDEX idx_club (club_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 表單題目表
CREATE TABLE form_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_id INT NOT NULL,
    question_order INT NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    question_type ENUM('short_answer', 'long_answer', 'multiple_choice', 'multi_choice') NOT NULL,
    is_required BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    INDEX idx_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 選擇題選項表
CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_text VARCHAR(200) NOT NULL,
    option_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 表單填寫記錄表
CREATE TABLE form_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_id INT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_form (form_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 答案表
CREATE TABLE answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    option_id INT COMMENT '多選題時的選項ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES question_options(id) ON DELETE SET NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入測試數據
INSERT INTO users (username, password, email, role) VALUES
('admin', SHA2('admin123456', 256), 'admin@school.edu', 'admin'),
('officer', SHA2('officer123456', 256), 'officer1@school.edu', 'club_officer'),
('member', SHA2('member123456', 256), 'member1@school.edu', 'member');

-- 插入社團與持有人
INSERT INTO clubs (name, owner_user_id) VALUES
('學生會', 2),
('資訊社', 2);

-- 插入成員關聯
INSERT INTO club_memberships (user_id, club_id, role) VALUES
(2, 1, 'club_officer'),
(2, 2, 'club_officer'),
(3, 1, 'member');
