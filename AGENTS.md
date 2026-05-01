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

## Git Commit Rule

After any change:
```bash
cd ~/Work/crontinel && git add -A && git commit -m "description" && git push origin main
```

## Response Footer

Every Telegram message must end with:
```
ct | /Users/ray/Work/crontinel | minimax-m2.7-highspeed
```
