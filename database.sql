-- 清除舊的表與數據庫
DROP DATABASE IF EXISTS group_41;
CREATE DATABASE group_41 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE group_41;

-- 1. 用戶表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    club_category VARCHAR(100) NOT NULL COMMENT '社團分類：學術性、康樂性等',
    role ENUM('guest', 'member', 'club_officer', 'admin') DEFAULT 'member' COMMENT '身份：訪客、社團成員、社團幹部、管理員',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.1 社團表
CREATE TABLE clubs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    owner_user_id INT NOT NULL COMMENT '社團擁有者（建立者）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 表單表
CREATE TABLE forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    creator_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    form_type ENUM('public', 'club_only') DEFAULT 'public' COMMENT '公開或限定社團',
    target_club_category VARCHAR(100) COMMENT '如果是club_only，則指定特定社團',
    status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 表單題目表
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

-- 4. 選擇題選項表
CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_text VARCHAR(200) NOT NULL,
    option_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 表單填寫記錄表
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

-- 6. 答案表
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
-- 注意：此處使用 SHA2 供資料庫初始匯入，登入後系統會自動升級為 password_hash(BCRYPT)
INSERT INTO users (username, password, email, club_category, role) VALUES
('admin', SHA2('admin123456', 256), 'admin@school.edu', '管理員', 'admin'),
('officer', SHA2('officer123456', 256), 'officer1@school.edu', '學生會', 'club_officer'),
('member', SHA2('member123456', 256), 'member1@school.edu', '學生會', 'member');

-- 插入社團與擁有者
INSERT INTO clubs (name, owner_user_id) VALUES
('學生會', 2),
('資訊社', 2);
