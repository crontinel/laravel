# AGENTS.md - Crontinel Workspace

This folder is home for the **ct** agent.

## Session Startup

Before doing anything else:

1. Read `SOUL.md` — this is who you are
2. Read `USER.md` — this is who you're helping
3. Read `memory/YYYY-MM-DD.md` (today + yesterday) for recent context

## Memory

**Long-term:** `MEMORY.md` — curated memory
**Short-term:** `memory/YYYY-MM-DD.md` — daily logs

## 🚨 Secrets Management — ABSOLUTE RULE

**NEVER put secret values in any workspace file.**

Secrets are in `~/.openclaw/secrets/ct.env`. Read from that file at runtime.

## Git Commit Rule — FEATURE BRANCH WORKFLOW

**NEVER commit directly to `main`.** Every change goes through a feature branch:

1. `git checkout -b feature/description`
2. Make changes, commit
3. `git push origin feature/description`
4. Open PR via GitHub CLI: `gh pr create --base main --title "feature: description"`
5. Merge PR → Railway auto-deploys from `main`

Each sub-folder is its own git repo. Commit from the relevant sub-folder, NOT the workspace root:

- App changes → `cd ~/Work/crontinel/app && git checkout -b feature/...`
- Landing changes → `cd ~/Work/crontinel/landing && git checkout -b feature/...`
- Workspace root (`~/Work/crontinel`) is a grouping repo — it does NOT track sub-folders

## Response Footer

Every Telegram message must end with:
```
ct | ~/Work/crontinel | minimax-m2.7-highspeed
```
