# AGENTS.md

This file contains high-signal information for agents working on this project.

## Project Overview
- **Type**: PHP + MySQL Web Application (XAMPP environment).
- **Entrypoint**: `index.php`
- **DB Connection**: `includes/db.php`
- **Authentication**: `includes/login_auth.php`

## Schema Notes
- **`forms.club_id`** can be `NULL` (admin global forms). Use `LEFT JOIN clubs` when querying forms.
- **`forms.form_type`**: `public` (anyone) / `club_only` (restricted to `target_club_ids`).
- **`forms.status`**: `draft` / `published` / `closed`.
- **`club_memberships.role`**: `member` (成員) / `owner` (幹部) / `club_officer` (持有人) — `manage.php` treats `owner`+`club_officer` as "officers".
- **`users.remember_token_hash`**: SHA256 hash of remember-me cookie. Cookie format: `userId:randomHex`. Each user keeps only the latest token.
- **`password_resets`**: stores token, email, expires_at, used for forgot-password flow.
- **`club_invitations`**: stores invitation records (pending/accepted/declined). UNIQUE on (club_id, user_id, status) prevents duplicate pending invites.
- **`club_join_requests`**: stores join requests (pending/approved/rejected). UNIQUE on (club_id, user_id, status) prevents duplicate pending requests.

## CSS Structure
- **`css/app.css`** — main entry point, only contains `@import` statements for the files below.
- **`css/base.css`** — variables, reset, typography, layout (`.container`, `.topbar`, `.hero`, `.footer`), animations, responsive `@media`.
- **`css/components.css`** — reusable UI: `.btn*`, `.badge*`, `.panel`, `.form-page`, `.form-card`, `.field`, `input/select/textarea`, `.error`, `.data-table`, `.action-group`.
- **`css/clubs.css`** — club management pages: `.setting-layout`, `.club-selector`, `.section-title`, `.mgmt-grid`.
- All pages link only `css/app.css`; internal `@import` ensures the split is transparent to PHP.

## Sidebar (`includes/right.php`)
- **Sidebar CSS is embedded** as a `<style>` block inside `right.php` (not in `css/app.css`) to guarantee positioning.
- **Auto-detects section** from script directory (`forms/`, `clubs/`, `admin/`, `users/`, root).
- No need to set `$right_title` / `$right_links` in page files anymore.
- To add/change links, edit the `$all_sections` array in `right.php`.
- Context-dependent pages (need `?id=` to work) should NOT be in the sidebar.

## CSRF Protection
- **`includes/csrf.php`** provides `csrf_generate()`, `csrf_verify($token)`, `csrf_field()`.
- Included automatically by `includes/header.php` (token generation for every page).
- All POST handlers must include `csrf.php` and call `csrf_verify()` before processing.

## Known Bugs / Patterns
- **Don't initialize `$pdo = null`** before calling `get_db()` — it overwrites the global PDO connection from `db.php` and breaks all queries.
- **JS paths**: always `../js/app.js` — there is no `public/` directory.
- **`forms/edit.php`** is dual-mode: lists forms (no `?id=`) or edits a specific form (`?id=X`).
- **`includes/footer.php`** is deleted (was unused, contained broken `public/js/` paths).

## Gotchas
- Always check `includes/db.php` for database configuration before running or testing.
- `forms/save_form.php` is deprecated/unused, but refer to `readme.md` before removing.
- New schema changes must be manually applied via `ALTER TABLE`; `group_41.sql` is the reference but not auto-applied.
