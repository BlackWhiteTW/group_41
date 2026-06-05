# 社團表單系統（Group_41）說明

此專案為一個簡易的社團表單系統（PHP + MySQL），包含建立表單、填寫、統計、社團管理與使用者驗證等功能。此文件說明各資料夾的主要用途、重要檔案，以及建議的分類與備份/搬移指令。

## 快速說明

- 必要環境：PHP 7.4+、MySQL、Apache（可使用 XAMPP）
- 初始化：匯入 `group_41.sql`，並在 `includes/db.php` 設定資料庫連線資訊
- 專案入口：`index.php`

## 資料夾說明（逐一說明主要用途與重要檔案）

- **admin/**：管理員專用工具與管理頁面
  - 功能：系統安裝/初始化、簡易 debug、檢視 SQL、管理後台入口
  - 重要檔案：`admin/index.php`、`admin/install.php`、`admin/sql_view.php`、`admin/debug_assets.php`

- **api/**：給前端或 AJAX 的輕量 API（無視圖）
  - 功能：回傳 JSON / 檢查值（例如帳號是否存在）
  - 重要檔案：`api/check_username.php`

- **archive/**：歷史程式碼或備份（不可直接使用的舊版）
  - 目前包含 `php_legacy/`（舊版程式碼與 includes）
  - 若保留歷史紀錄，請維持此處不放在公開路徑下供測試用

- **clubs/**：社團管理相關頁面
  - 功能：建立社團、管理社團（幹部權限、成員管理）
  - 重要檔案：`clubs/create.php`、`clubs/manage.php`

- **forms/**：表單核心功能與使用者互動頁面
  - 功能：建立/修改/檢視/填寫表單、查看統計、列出表單等
  - 重要檔案：`forms/create.php`、`forms/edit.php`、`forms/list.php`、`forms/view.php`、`forms/submit.php`、`forms/success.php`、`forms/statistics.php`
  - 備註：`forms/save_form.php` 目前內容僅為重導（`Location: ./create.php`），專案中沒有其他程式引用；可視為可刪除或先備份再移除。

- **includes/**：共用程式與頁首/頁尾、資料庫連線等
  - 重要檔案：`includes/db.php`（資料庫連線）、`includes/header.php`、`includes/footer.php`（若存在）、`includes/login_auth.php`

- **css/**：樣式表（靜態資源）
  - 包含：`app.css`、`bootstrap.min.css`、`datatables.css`、`style.css` 等

- **js/**：前端 JavaScript 檔案
  - 包含：`app.js`、`jquery-3.7.1.min.js`、`datatables.min.js` 等

- 根目錄重要檔案：
  - `index.php`：首頁（專案入口）
  - `login.php`、`logout.php`、`register.php`：認證相關頁面
  - `group_41.sql`：資料表與初始資料（匯入到 MySQL）

## 建議的分類原則（以保守、安全為主）

1. 先備份：任何搬移/刪除動作前請先備份原始檔案
2. 公開資源（CSS/JS/圖片）應集中於 `css/`、`js/`，並由網域根目錄或 `public/` 提供
3. 後端頁面（PHP）可保留現有目錄結構以避免相對路徑中斷；若要大規模重構，請搭配自動化調整 (如修改 `includes/header.php` 的資源路徑)
4. 歷史或不再使用的檔案建議移至 `archive/` 或 `archive/unused_by_readme/` 做紀錄，不直接刪除

## 常用 PowerShell 範例（備份 / 移動 / 還原）

以下指令在專案根目錄（本案為 `d:\xampp\htdocs\group_41`）執行：

備份單一檔案：

```powershell
Copy-Item -Path "forms\save_form.php" -Destination "archive\save_form.php.bak"
```

將檔案移到 archive（示範，不會自動更新程式內引用）：

```powershell
Move-Item -Path "forms\save_form.php" -Destination "archive\unused_by_readme\save_form.php"
```

批次建立 archive 子資料夾並備份整個目錄（範例）：

```powershell
# 建立備份資料夾
New-Item -ItemType Directory -Path "archive\classified_by_readme" -Force

# 複製整個 forms 資料夾備份
Copy-Item -Path "forms\*" -Destination "archive\classified_by_readme\forms_backup" -Recurse -Force
```

還原檔案（從備份恢復）：

```powershell
Move-Item -Path "archive\unused_by_readme\save_form.php" -Destination "forms\save_form.php"
```

注意：若執行 `Move-Item` 導致原始檔案路徑變動，專案中以相對路徑載入的 `require`、`include` 或前端資源路徑可能會中斷，需要同步修改引用。

## 我已完成的工作與下一步

- 我已整理並更新本 `readme.md`，說明各資料夾用途並加入建議分類與備份/還原指令。
- 下一步我可以：
  1.  依 README 建議幫你把「未使用或可移除的檔案」移到 `archive/`（預設會先備份）；或
  2.  直接在專案中做完整重構（移動檔案並同時更新引用路徑）—此選項風險較高，會需要更多測試。

請回覆你要我採取哪一個動作（`備份並移到 archive` 或 `直接重構並更新引用`），我就開始執行。

---

檔案： [readme.md](readme.md)
