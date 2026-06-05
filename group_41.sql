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

-- ════════════════════════════════════════════════════
-- 擴充測試資料：更多使用者、社團、表單與互動記錄
-- ════════════════════════════════════════════════════

-- 更多測試使用者（密碼皆為 test123456 的 bcrypt 雜湊）
INSERT INTO users (username, password, email, role) VALUES
('officer2', '$2y$10$8JCtOtpvcSLCLrurStPTQOFD/vyQOTMdVH0TGYRV./RQ6gomgZ5Ge', 'officer2@school.edu', 'club_officer'),
('member2',  '$2y$10$8JCtOtpvcSLCLrurStPTQOFD/vyQOTMdVH0TGYRV./RQ6gomgZ5Ge', 'member2@school.edu',  'member'),
('member3',  '$2y$10$8JCtOtpvcSLCLrurStPTQOFD/vyQOTMdVH0TGYRV./RQ6gomgZ5Ge', 'member3@school.edu',  'member');

-- 更多社團（多樣化 join_mode 與 visibility）
INSERT INTO clubs (name, description, owner_user_id, join_mode, visibility) VALUES
('攝影社', '捕捉光影之美，學習攝影技巧與後製。', 4, 'open', 'public'),
('音樂社', '不論樂器或歌唱，一起享受音樂的樂趣。', 4, 'request', 'public'),
('運動社', '籃球、羽球、排球等多項運動交流。', 1, 'open', 'public'),
('讀書會', '每週進行書籍討論與心得分享。', 1, 'invite_only', 'public'),
('實驗社', '不公開的實驗性社團。', 1, 'invite_only', 'private');

-- ═══ 社團成員關聯（交叉加入，展現多樣關係）═══
INSERT INTO club_memberships (user_id, club_id, role) VALUES
-- 攝影社（club_id=3）：officer2 是持有人，member2 成員
(4, 3, 'club_officer'),
(5, 3, 'member'),
-- 音樂社（club_id=4）：officer2 是持有人
(4, 4, 'club_officer'),
-- 運動社（club_id=5）：admin 是持有人，member 和 member2 加入
(2, 5, 'member'),
(3, 5, 'member'),
(5, 5, 'member'),
-- 讀書會（club_id=6）：member3 是成員
(6, 6, 'member'),
-- member 也在資訊社（club_id=2）以 member 身份
(3, 2, 'member');

-- ═══ 表單（涵蓋各種狀態與類型組合）═══
INSERT INTO forms (creator_id, club_id, title, description, form_type, status, allow_resubmit, require_login) VALUES
-- 已發布的全域公開表單
(1, NULL, '校園活動滿意度調查', '請協助填寫本學期校園活動的滿意度。', 'public', 'published', 1, 0),
-- 已發布的社團限定表單（學生會）
(2, 1, '學生會意見回饋', '對學生會運作的建議與回饋。', 'club_only', 'published', 1, 1),
-- 已關閉表單
(1, NULL, '上學期課程問卷', '已截止的課程問卷調查。', 'public', 'closed', 0, 0),
-- 草稿表單
(4, 4, '音樂社社員登記', '請音樂社社員填寫基本資料與樂器專長。', 'club_only', 'draft', 1, 1),
-- 另一份已發布表單（資訊社）
(2, 2, '資訊社活動提案', '提案下學期想舉辦的資訊相關活動。', 'club_only', 'published', 1, 1),
-- 已發布的開放表單（攝影社）
(4, 3, '攝影比賽報名', '校內攝影比賽線上報名。', 'club_only', 'published', 0, 1);

-- ═══ 表單題目（多樣化題型）═══
-- 表單 1：校園活動滿意度調查（已發布，全域）
INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES
(1, 1, '你對本學期校園活動的整體滿意度如何？', 'multiple_choice', 1),
(1, 2, '你最喜歡哪一類型的活動？', 'short_answer', 1),
(1, 3, '請寫下你對校園活動的建議。', 'long_answer', 0);

-- 表單 2：學生會意見回饋（已發布，club_only）
INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES
(2, 1, '你認為學生會在本學期的表現如何？', 'multiple_choice', 1),
(2, 2, '你希望學生會加強哪些服務？（可複選）', 'multi_choice', 1),
(2, 3, '其他具體建議', 'long_answer', 0);

-- 表單 3：上學期課程問卷（已關閉）
INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES
(3, 1, '這門課的教學品質如何？', 'multiple_choice', 1),
(3, 2, '你對教材的滿意度？', 'multiple_choice', 1);

-- 表單 5：資訊社活動提案（已發布，club_only）
INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES
(5, 1, '請簡述你的活動提案名稱。', 'short_answer', 1),
(5, 2, '活動類型', 'multiple_choice', 1),
(5, 3, '請上傳活動企劃書（如有）。', 'file_upload', 0);

-- 表單 6：攝影比賽報名（已發布，club_only）
INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES
(6, 1, '參賽者姓名', 'short_answer', 1),
(6, 2, '參賽組別', 'multiple_choice', 1),
(6, 3, '作品主題', 'short_answer', 1);

-- ═══ 選擇題選項 ═══
-- 表單 1 題目 1（question_id=1）：滿意度
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(1, '非常滿意', 1),
(1, '滿意', 2),
(1, '普通', 3),
(1, '不滿意', 4),
(1, '非常不滿意', 5);

-- 表單 2 題目 1（question_id=4）：學生會表現
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(4, '非常良好', 1),
(4, '良好', 2),
(4, '尚可', 3),
(4, '不佳', 4);

-- 表單 2 題目 2（question_id=5）：希望加強服務（多選）
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(5, '學術講座', 1),
(5, '社團補助', 2),
(5, '活動宣傳', 3),
(5, '校園環境', 4),
(5, '學生權益', 5);

-- 表單 3 題目 1（question_id=7）：教學品質
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(7, '非常好', 1),
(7, '好', 2),
(7, '普通', 3),
(7, '差', 4);

-- 表單 3 題目 2（question_id=8）：教材滿意度
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(8, '非常滿意', 1),
(8, '滿意', 2),
(8, '普通', 3),
(8, '不滿意', 4);

-- 表單 5 題目 2（question_id=10）：活動類型
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(10, '程式競賽', 1),
(10, '技術講座', 2),
(10, '黑客松', 3),
(10, '社群交流', 4);

-- 表單 6 題目 2（question_id=13）：參賽組別
INSERT INTO question_options (question_id, option_text, option_order) VALUES
(13, '風景組', 1),
(13, '人像組', 2),
(13, '生態組', 3),
(13, '創意組', 4);

-- ═══ 表單填寫記錄 ═══
INSERT INTO form_submissions (form_id, user_id) VALUES
(1, 3),  (1, 5),  (1, 6),  (1, NULL),  (1, 2),  (1, NULL),
(2, 3),  (2, 2),
(3, 3),  (3, 5);

-- ═══ 答案明細 ═══
-- 表單 1 的提交 (submission 1-6)
-- 提交 1：member 填寫（滿意度：滿意，最愛：社團博覽會，建議：無）
INSERT INTO answers (submission_id, question_id, option_id) VALUES (1, 1, 2);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (1, 2, '社團博覽會');
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (1, 3, '希望增加更多社團聯合活動。');

-- 提交 2：member2 填寫
INSERT INTO answers (submission_id, question_id, option_id) VALUES (2, 1, 1);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (2, 2, '運動會');
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (2, 3, NULL);

-- 提交 3：member3 填寫
INSERT INTO answers (submission_id, question_id, option_id) VALUES (3, 1, 3);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (3, 2, '校園演唱會');

-- 提交 4：匿名填寫
INSERT INTO answers (submission_id, question_id, option_id) VALUES (4, 1, 4);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (4, 2, '都還好');

-- 提交 5：officer 填寫
INSERT INTO answers (submission_id, question_id, option_id) VALUES (5, 1, 2);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (5, 2, '社團博覽會');
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (5, 3, '希望能延長活動時間。');

-- 提交 6：匿名填寫
INSERT INTO answers (submission_id, question_id, option_id) VALUES (6, 1, 2);

-- 表單 2 的提交 (submission 7-8)：form 2 的題目為 Q4(選擇), Q5(多選), Q6(長答)
-- 提交 7：member 填寫（表現：良好=option 7，服務：學術講座=option 10 + 社團補助=option 11）
INSERT INTO answers (submission_id, question_id, option_id) VALUES (7, 4, 7);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (7, 5, 10);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (7, 5, 11);

-- 提交 8：officer 填寫（表現：良好=option 7，服務：學生權益=option 14，建議：加強宿舍管理）
INSERT INTO answers (submission_id, question_id, option_id) VALUES (8, 4, 7);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (8, 5, 14);
INSERT INTO answers (submission_id, question_id, answer_text) VALUES (8, 6, '建議加強宿舍管理。');

-- 表單 3 的提交 (submission 9-10)：form 3 的題目為 Q7(選擇), Q8(選擇)
INSERT INTO answers (submission_id, question_id, option_id) VALUES (9, 7, 16);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (9, 8, 21);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (10, 7, 15);
INSERT INTO answers (submission_id, question_id, option_id) VALUES (10, 8, 19);

-- ═══ 社團公告 ═══
INSERT INTO club_announcements (club_id, user_id, title, content) VALUES
(1, 2, '學生會期末大會通知', '本學期期末大會將於 6/15 下午 3 點在活動中心舉行，請各位成員踴躍參加。'),
(1, 2, '學生會幹部改選公告', '下學期學生會幹部改選將於 6/20 開始受理報名。'),
(2, 2, '資訊社迎新活動', '歡迎新社員！本週五晚上 7 點將舉辦迎新茶會。'),
(3, 4, '攝影社外拍活動', '本週六早上 8 點校門口集合，前往陽明山外拍，請自備器材。'),
(5, 2, '運動社友誼賽', '下週三與隔壁大學舉辦籃球友誼賽，歡迎大家報名參加。');

-- ═══ 社團活動記錄（稽核日誌）═══
INSERT INTO club_activity_log (club_id, user_id, action, details) VALUES
(1, 2, '建立社團', '創建學生會社團'),
(1, 2, '新增成員', '邀請 member 加入學生會'),
(1, 2, '發布公告', '發布「學生會期末大會通知」'),
(2, 2, '建立社團', '創建資訊社社團'),
(2, 2, '新增成員', '邀請 member 加入資訊社'),
(2, 2, '建立表單', '建立表單「資訊社活動提案」'),
(3, 4, '建立社團', '創建攝影社社團'),
(3, 4, '新增成員', '邀請 member2 加入攝影社'),
(3, 4, '建立表單', '建立表單「攝影比賽報名」'),
(4, 4, '建立社團', '創建音樂社社團'),
(4, 4, '建立表單', '建立表單「音樂社社員登記」（草稿）'),
(5, 1, '建立社團', '創建運動社社團'),
(1, 2, '修改設定', '更新社團簡介');

-- ═══ 社團邀請記錄 ═══
INSERT INTO club_invitations (club_id, user_id, invited_by, status) VALUES
-- 已接受
(1, 5, 2, 'accepted'),
-- 待處理
(2, 6, 2, 'pending'),
(3, 6, 4, 'pending'),
-- 已拒絕
(4, 3, 4, 'declined');

-- ═══ 社團加入申請 ═══
INSERT INTO club_join_requests (club_id, user_id, status) VALUES
-- 已核准
(5, 3, 'approved'),
-- 待審核
(1, 6, 'pending'),
(2, 5, 'pending'),
-- 已拒絕
(3, 2, 'rejected');
