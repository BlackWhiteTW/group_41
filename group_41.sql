-- ============================================================
-- 社團表單系統（group_41）— 資料庫初始化腳本
-- ============================================================
-- 連線資訊：root / root123456
-- 本系統包含 13 張資料表，分為四大區塊：
--   1. 使用者與權限（users, clubs, club_memberships）
--   2. 表單系統（forms, form_questions, question_options, form_submissions, answers）
--   3. 認證機制（password_resets，remember_token 已合併至 users）
--   4. 社團互動（club_invitations, club_join_requests, club_announcements, club_activity_log）
-- ============================================================

-- 檢查是否存在 group_41 數據庫，存在則刪除
DROP DATABASE IF EXISTS group_41;
CREATE DATABASE group_41 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE group_41;

-- ============================================================
-- 1. users（使用者帳號表）
--    儲存所有使用者基本資料與系統層級角色。
--    社團內的角色（成員/幹部/持有人）請看 club_memberships。
--    關聯：users.id ← club_memberships.user_id
--          users.id ← clubs.owner_user_id
-- ============================================================
CREATE TABLE users (
    id          INT PRIMARY KEY AUTO_INCREMENT        COMMENT '使用者唯一識別碼',
    username    VARCHAR(50)  UNIQUE NOT NULL          COMMENT '登入帳號，不可重複',
    password    VARCHAR(255) NOT NULL                 COMMENT '密碼（SHA256 雜湊）',
    email       VARCHAR(100) UNIQUE NOT NULL          COMMENT '電子郵件，不可重複，用於密碼重設',
    role        ENUM('member','owner','club_officer','admin')
                         DEFAULT 'member'             COMMENT '系統層級身份：member=一般會員, owner=幹部, club_officer=社團持有人, admin=管理員',
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP   COMMENT '帳號建立時間',
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP  COMMENT '最後更新時間',
    remember_token_hash  VARCHAR(64) NULL              COMMENT '記住我 Token 的 SHA256 雜湊（每個使用者僅保留最新一個，取代獨立的 remember_tokens 表）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. clubs（社團基本資料表）
--    每個社團由一位使用者建立並持有（owner_user_id）。
--    join_mode 決定新成員如何加入；visibility 控制社團是否公開可見。
--    關聯：clubs.id → club_memberships.club_id
--          clubs.id → forms.club_id（可為 NULL 表示全域表單）
-- ============================================================
CREATE TABLE clubs (
    id            INT PRIMARY KEY AUTO_INCREMENT      COMMENT '社團唯一識別碼',
    name          VARCHAR(100) UNIQUE NOT NULL        COMMENT '社團名稱，不可重複',
    description   TEXT                                COMMENT '社團簡介說明',
    join_mode     ENUM('open','request','invite_only')
                           DEFAULT 'request'          COMMENT '加入模式：open=開放加入, request=需申請審核, invite_only=僅限邀請',
    visibility    ENUM('public','private')
                           DEFAULT 'public'           COMMENT '可見性：public=公開顯示, private=隱藏',
    owner_user_id INT NOT NULL                        COMMENT '社團建立者（持有人），參照 users.id',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '社團建立時間',
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP COMMENT '最後更新時間',
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. club_memberships（社團成員關聯表）
--    記錄每位使用者在其所屬社團中的角色，實現「多對多」關係：
--    一個使用者可加入多個社團，一個社團可有多位成員。
--    UNIQUE(user_id, club_id) 確保同一人不會重複加入同一社團。
-- ============================================================
CREATE TABLE club_memberships (
    id         INT PRIMARY KEY AUTO_INCREMENT         COMMENT '關聯記錄唯一識別碼',
    user_id    INT NOT NULL                           COMMENT '使用者 ID，參照 users.id',
    club_id    INT NOT NULL                           COMMENT '社團 ID，參照 clubs.id',
    role       ENUM('member','owner','club_officer')
                        NOT NULL                      COMMENT '社團內角色：member=一般成員, owner=幹部, club_officer=持有人',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP    COMMENT '加入時間',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_membership (user_id, club_id),
    INDEX idx_club (club_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. forms（表單主表）
--    每張表單由一位使用者建立，可選擇掛在某個社團下或設為全域表單（club_id=NULL）。
--    form_type 控制填寫權限；status 控制表單生命週期（草稿→發布→關閉）。
--    關聯：forms.id → form_questions.form_id
--          forms.id → form_submissions.form_id
-- ============================================================
CREATE TABLE forms (
    id              INT PRIMARY KEY AUTO_INCREMENT     COMMENT '表單唯一識別碼',
    creator_id      INT NOT NULL                       COMMENT '建立者 ID，參照 users.id',
    club_id         INT NULL                           COMMENT '所屬社團 ID，NULL 表示系統全域表單',
    title           VARCHAR(200) NOT NULL              COMMENT '表單標題',
    description     TEXT                               COMMENT '表單說明（選填）',
    form_type       ENUM('public','club_only')
                             DEFAULT 'public'          COMMENT '填寫權限：public=任何人可填, club_only=僅限指定社團成員',
    target_club_ids TEXT                               COMMENT 'club_only 時，可填寫的社團 ID 清單（逗號分隔）',
    status          ENUM('draft','published','closed')
                             DEFAULT 'draft'           COMMENT '表單狀態：draft=草稿, published=已發布, closed=已關閉',
    open_at         DATETIME NULL                      COMMENT '預定開放填寫時間',
    close_at        DATETIME NULL                      COMMENT '預定關閉填寫時間',
    allow_resubmit  TINYINT(1) NOT NULL DEFAULT 1      COMMENT '是否允許重複填答：1=可重複, 0=每人限填一次',
    require_login   TINYINT(1) NOT NULL DEFAULT 0      COMMENT '是否需登入才能填寫：1=需登入, 0=免登入',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP COMMENT '最後更新時間',
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id)   REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id),
    INDEX idx_club (club_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. form_questions（表單題目表）
--    每張表單可包含多個題目，透過 question_order 控制排序。
--    題型支援：簡答、長答、單選、多選、檔案上傳。
--    關聯：form_questions.id → question_options.question_id
--          form_questions.id → answers.question_id
-- ============================================================
CREATE TABLE form_questions (
    id             INT PRIMARY KEY AUTO_INCREMENT      COMMENT '題目唯一識別碼',
    form_id        INT NOT NULL                        COMMENT '所屬表單 ID，參照 forms.id',
    question_order INT NOT NULL                        COMMENT '題目排序編號（數字越小越前面）',
    question_text  VARCHAR(500) NOT NULL               COMMENT '題目文字內容',
    question_type  ENUM('short_answer','long_answer','multiple_choice','multi_choice','file_upload')
                           NOT NULL                    COMMENT '題型：short_answer=簡答, long_answer=長答, multiple_choice=單選, multi_choice=多選, file_upload=檔案上傳',
    is_required    BOOLEAN DEFAULT 1                   COMMENT '是否為必填題：1=必填, 0=選填',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    INDEX idx_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. question_options（選擇題選項表）
--    僅用於 multiple_choice 和 multi_choice 題型。
--    每個選項屬於一道題目，透過 option_order 控制選項排列順序。
-- ============================================================
CREATE TABLE question_options (
    id          INT PRIMARY KEY AUTO_INCREMENT         COMMENT '選項唯一識別碼',
    question_id INT NOT NULL                           COMMENT '所屬題目 ID，參照 form_questions.id',
    option_text VARCHAR(200) NOT NULL                  COMMENT '選項文字內容',
    option_order INT NOT NULL                          COMMENT '選項排序編號',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP    COMMENT '建立時間',
    FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. form_submissions（表單填寫記錄表）
--    每次使用者提交表單時建立一筆記錄，代表「一次填答」。
--    user_id 可為 NULL（未登入的匿名填答者）。
--    關聯：form_submissions.id → answers.submission_id
-- ============================================================
CREATE TABLE form_submissions (
    id           INT PRIMARY KEY AUTO_INCREMENT        COMMENT '提交記錄唯一識別碼',
    form_id      INT NOT NULL                          COMMENT '所屬表單 ID，參照 forms.id',
    user_id      INT                                   COMMENT '填答者 ID（已登入使用者），匿名則為 NULL',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP   COMMENT '提交時間',
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_form (form_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. answers（答案明細表）
--    記錄一次提交中每一題的具體答案內容。
--    簡答/長答存在 answer_text；選擇題存在 option_id；檔案上傳存在 file_path。
--    同一題可能有多筆答案（多選題），透過 submission_id + question_id 關聯。
-- ============================================================
CREATE TABLE answers (
    id            INT PRIMARY KEY AUTO_INCREMENT       COMMENT '答案唯一識別碼',
    submission_id INT NOT NULL                         COMMENT '所屬提交記錄 ID，參照 form_submissions.id',
    question_id   INT NOT NULL                         COMMENT '對應題目 ID，參照 form_questions.id',
    answer_text   TEXT                                 COMMENT '簡答或長答的文字內容',
    option_id     INT                                  COMMENT '選擇題的選項 ID，參照 question_options.id（單選/多選）',
    file_path     VARCHAR(255) NULL                    COMMENT '檔案上傳題型的儲存路徑',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP  COMMENT '建立時間',
    FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id)   REFERENCES form_questions(id)   ON DELETE CASCADE,
    FOREIGN KEY (option_id)     REFERENCES question_options(id) ON DELETE SET NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. password_resets（密碼重設 Token 表）
--     使用者忘記密碼時，系統產生一次性重設連結並寄送至 email。
--     token 有過期時間（expires_at），用完或過期即失效（used=1）。
--     與 users 表無 FK 關聯，因為重設時可能尚未登入。
-- ============================================================
CREATE TABLE password_resets (
    id         INT PRIMARY KEY AUTO_INCREMENT          COMMENT '重設記錄唯一識別碼',
    email      VARCHAR(100) NOT NULL                   COMMENT '申請重設的電子郵件',
    token      VARCHAR(64) NOT NULL                    COMMENT '一次性重設 Token',
    expires_at DATETIME NOT NULL                       COMMENT 'Token 過期時間',
    used       TINYINT(1) DEFAULT 0                    COMMENT '是否已使用：0=未使用, 1=已使用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     COMMENT '申請時間',
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. club_invitations（社團邀請記錄表）
--     社團幹部可主動邀請其他使用者加入社團。
--     每筆邀請有三種狀態：pending=待回覆, accepted=已接受, declined=已拒絕。
--     UNIQUE(club_id, user_id, status) 防止對同一人重複發出待處理邀請。
-- ============================================================
CREATE TABLE club_invitations (
    id         INT PRIMARY KEY AUTO_INCREMENT          COMMENT '邀請記錄唯一識別碼',
    club_id    INT NOT NULL                            COMMENT '發出邀請的社團 ID，參照 clubs.id',
    user_id    INT NOT NULL                            COMMENT '被邀請的使用者 ID，參照 users.id',
    invited_by INT NOT NULL                            COMMENT '邀請發起人 ID，參照 users.id',
    status     ENUM('pending','accepted','declined')
                        DEFAULT 'pending'              COMMENT '邀請狀態：pending=待回覆, accepted=已接受, declined=已拒絕',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     COMMENT '邀請發送時間',
    FOREIGN KEY (club_id)    REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_invite (club_id, user_id, status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. club_join_requests（社團加入申請表）
--     使用者可主動向社團提出加入申請。
--     每筆申請有三種狀態：pending=審核中, approved=已核准, rejected=已拒絕。
--     UNIQUE(club_id, user_id, status) 防止對同一社團重複送出待審核申請。
--     與 club_invitations 的差異：邀請是「社團找人」，申請是「人找社團」。
-- ============================================================
CREATE TABLE club_join_requests (
    id         INT PRIMARY KEY AUTO_INCREMENT          COMMENT '申請記錄唯一識別碼',
    club_id    INT NOT NULL                            COMMENT '目標社團 ID，參照 clubs.id',
    user_id    INT NOT NULL                            COMMENT '申請人 ID，參照 users.id',
    status     ENUM('pending','approved','rejected')
                        DEFAULT 'pending'              COMMENT '申請狀態：pending=審核中, approved=已核准, rejected=已拒絕',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     COMMENT '申請送出時間',
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_request (club_id, user_id, status),
    INDEX idx_club (club_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. club_announcements（社團公告表）
--     社團幹部可在社團內發布公告，所有社團成員皆可查看。
--     一對多關係：一個社團可有多則公告。
-- ============================================================
CREATE TABLE club_announcements (
    id         INT PRIMARY KEY AUTO_INCREMENT          COMMENT '公告唯一識別碼',
    club_id    INT NOT NULL                            COMMENT '所屬社團 ID，參照 clubs.id',
    user_id    INT NOT NULL                            COMMENT '發布者 ID，參照 users.id',
    title      VARCHAR(200) NOT NULL                   COMMENT '公告標題',
    content    TEXT                                    COMMENT '公告內文',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     COMMENT '發布時間',
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_club (club_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. club_activity_log（社團活動紀錄表）
--     記錄社團內的所有操作行為，作為稽核與歷程追蹤用。
--     action 為操作描述，details 可存放 JSON 或額外明細。
-- ============================================================
CREATE TABLE club_activity_log (
    id         INT PRIMARY KEY AUTO_INCREMENT          COMMENT '紀錄唯一識別碼',
    club_id    INT NOT NULL                            COMMENT '所屬社團 ID，參照 clubs.id',
    user_id    INT NOT NULL                            COMMENT '操作者 ID，參照 users.id',
    action     VARCHAR(255) NOT NULL                   COMMENT '操作行為摘要（如：建立表單、修改設定、邀請成員）',
    details    TEXT                                    COMMENT '操作詳細內容（可為 JSON 格式）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     COMMENT '操作時間',
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_club (club_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 測試資料
-- ============================================================

-- 測試使用者（密碼皆為 bcrypt 雜湊）
INSERT INTO users (username, password, email, role) VALUES
('admin',   '$2y$10$mfdDqKMsuoKZa1kK7sInzOdQg5tq00JjngVQx4zWOjZd7nJhIeYfa', 'admin@school.edu',   'admin'),
('officer', '$2y$10$wctpGkUzmLlUBDTP2crXR.QG5JZVr0gpCWZrtWdSmrKV7AW4vGSFy', 'officer1@school.edu','club_officer'),
('member',  '$2y$10$pcVfuWaoZl4nEtfN1AN7IOfld4kKAOxsJbhL54sV1ePHJEUc1JEjG', 'member1@school.edu', 'member');

-- 測試社團
INSERT INTO clubs (name, owner_user_id) VALUES
('學生會', 2),
('資訊社', 2);

-- 測試社團成員關聯
INSERT INTO club_memberships (user_id, club_id, role) VALUES
(2, 1, 'club_officer'),
(2, 2, 'club_officer'),
(3, 1, 'member');
