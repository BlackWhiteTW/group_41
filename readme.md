# 社團表單系統（Group_41）說明

此專案為社團問卷發布與填寫系統（PHP + MySQL），包含建立表單、填寫、統計、社團管理與使用者驗證等功能。

## 環境需求

- PHP 7.4+、MySQL、Apache（可使用 XAMPP）
- 初始化：匯入 `group_41.sql`，並在 `includes/db.php` 設定資料庫連線資訊
- 專案入口：`index.php`

## 專案結構

### 根目錄
| 檔案 | 用途 |
|------|------|
| `index.php` | 首頁（公開表單總覽 + 系統統計） |
| `login.php` | 登入頁面 |
| `logout.php` | 登出處理 |
| `register.php` | 註冊頁面 |
| `forgot_password.php` | 忘記密碼（產生重設連結） |
| `reset_password.php` | 重設密碼（驗證 token） |
| `group_41.sql` | 完整資料庫結構 + 測試資料 |

### admin/ — 管理員專用工具
| 檔案 | 用途 |
|------|------|
| `admin_index.php` | 管理控制台（系統概況 + 管理工具入口） |
| `user_CRUD.php` | 使用者管理（建立 / 編輯 / 刪除 / 角色 / 社團關聯） |
| `forms_CRUD.php` | 表單管理（搜尋 / 狀態篩選 / 刪除 / 跳轉編輯） |
| `clubs_CRUD.php` | 社團管理（編輯 / 持有人轉移 / 刪除 / 成員檢視） |
| `activity_log.php` | 活動記錄（全系統社團操作稽核日誌） |
| `sql_view.php` | SQL 資料檢視（14 張資料表瀏覽） |
| `sql_reset.php` | 資料庫重新匯入（POST + CSRF 保護） |
| `test.php` | 簡易系統測試（檔案 / 資料庫 / 環境檢查） |

### api/ — AJAX API
| 檔案 | 用途 |
|------|------|
| `check_username.php` | 檢查帳號是否可用（JSON 回傳） |

### clubs/ — 社團管理
| 檔案 | 用途 |
|------|------|
| `clubs_index.php` | 社團中心（列表 / 搜尋 / 加入操作） |
| `create.php` | 建立新社團 |
| `manage.php` | 社團資訊（成員 / 表單 / 公告） |
| `setting.php` | 社團設定（基本資料 / 公告 / 成員管理 / 邀請 / 審核 / 活動記錄） |
| `update_setting.php` | POST 處理（所有社團操作） |

### forms/ — 表單核心
| 檔案 | 用途 |
|------|------|
| `forms_index.php` | 表單中心（統計 + 近期表單 + 快速連結） |
| `create.php` | 建立表單（完整表單建構器） |
| `edit.php` | 編輯表單（雙模式：列表 / 編輯特定表單） |
| `view.php` | 檢視表單（權限控制 + 填寫入口） |
| `submit.php` | 提交表單（驗證 + 檔案上傳 + 儲存） |
| `list.php` | 表單列表（瀏覽 + 管理控制） |
| `my_forms.php` | 我的表單（建立者管理檢視） |
| `statistics.php` | 統計（圖表 + CSV 匯出 + 逐筆檢視） |
| `copy_form.php` | 複製表單（POST） |
| `delete.php` | 刪除表單（POST + 檔案清理） |
| `download.php` | 檔案下載 / 預覽（權限控制） |
| `edit_submission.php` | 編輯已提交的回應 |
| `success.php` | 提交成功頁面 |

### includes/ — 共用程式
| 檔案 | 用途 |
|------|------|
| `db.php` | PDO 資料庫連線 + `get_db()` 函數 |
| `cookies.php` | Session / Cookie / 記住我（Remember-me）管理 |
| `csrf.php` | CSRF 防護（產生 / 驗證 / 欄位） |
| `admin_auth.php` | 集中式管理員授權檢查 |
| `header.php` | 全域頂部導覽列 + SweetAlert2 + Flash 訊息 |
| `login_auth.php` | 密碼驗證輔助函數（bcrypt + SHA256 相容） |
| `right.php` | 左側側邊欄（自動偵測區域 + 連結） |

### users/ — 使用者中心
| 檔案 | 用途 |
|------|------|
| `user_index.php` | 個人資料頁（統計 + 記錄） |
| `setting.php` | 帳號設定（名稱 / 信箱 / 密碼修改） |

### css/ — 樣式表
| 檔案 | 用途 |
|------|------|
| `app.css` | 入口檔案（@import base, components, clubs） |
| `base.css` | 變數、重設、排版、動畫、RWD |
| `components.css` | 按鈕、面板、表單卡片、欄位、表格、操作群組 |
| `clubs.css` | 社團管理頁面樣式 |

### js/ — 前端 JavaScript
| 檔案 | 用途 |
|------|------|
| `app.js` | 表單建構器、驗證、確認對話框、提交防重複 |
| `sweetalert2@11.js` | SweetAlert2（全局載入） |
| `chart.umd.js` | Chart.js（僅 statistics.php 載入） |

### uploads/ — 上傳檔案
| 檔案 | 用途 |
|------|------|
| `.htaccess` | 禁止直接存取（`Require all denied`） |

## 資料庫結構（14 張表）
1. `users` — 使用者帳號
2. `clubs` — 社團基本資料
3. `club_memberships` — 社團成員關聯
4. `forms` — 表單主表
5. `form_questions` — 表單題目
6. `question_options` — 選擇題選項
7. `form_submissions` — 填寫記錄
8. `answers` — 答案明細
9. `remember_tokens` — 記住我 Token
10. `password_resets` — 密碼重設
11. `club_invitations` — 社團邀請
12. `club_join_requests` — 加入申請
13. `club_announcements` — 社團公告
14. `club_activity_log` — 活動稽核日誌

## 安全機制
- CSRF 防護：所有 POST 表單需通過 `csrf_verify()`
- 管理員授權：集中於 `includes/admin_auth.php`
- 密碼雜湊：bcrypt（含 SHA256 舊密碼相容）
- 記住我：Token-based（SHA256 hash），單一裝置
- 檔案上傳：隨機檔名（32 位元 hex），`.htaccess` 禁止直接存取
- SQL Injection：全站使用 Prepared Statements
