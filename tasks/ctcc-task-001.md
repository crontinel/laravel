# Task: Verify status_pages table has crontinel row

## Background
- `/status/crontinel` route was just fixed (PR #42 merged)
- But the route only works if `status_pages` table has a row with `slug = 'crontinel'`
- DB on Railway is **SQLite** (`database/database.sqlite`)

## What to do
1. **READ this file** fully before starting
2. Go to `crontinel/app` repo: `~/Work/crontinel/app`
3. Check if `status_pages` table has a row: `SELECT * FROM status_pages WHERE slug = 'crontinel';`
4. If no row exists, insert one:
   ```sql
   INSERT INTO status_pages (team_id, slug, name, branding_settings, created_at, updated_at)
   VALUES (1, 'crontinel', 'Crontinel', '{}', datetime('now'), datetime('now'));
   ```
5. Verify `SELECT * FROM status_pages;` returns at least the crontinel row
6. Test: `curl -s https://app.crontinel.com/status/crontinel` — should return HTML/JSON, not 404
7. Commit to a branch and push if any changes made

## Success criteria
`curl -s https://app.crontinel.com/status/crontinel` returns a valid status page response (not 404).

## Repo
`crontinel/app` — Railway deployment, SQLite at `database/database.sqlite`
