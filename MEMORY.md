# Crontinel — Memory

## Core Rules
- IF STUCK, ASK before trying alternatives
- ALWAYS do what user asks
- Save important things in CAPS immediately

## Workflow
- **Branch workflow:** `feat/` / `fix/` / `docs/` / `chore/`
- **Never commit directly to main**
- **Coding/review:** Use `model=opus` for planning and reviews
- **Coding/implementation:** Use faster model

## Bot-to-Bot Routing
- When `@CrontinelOnM4AirCCBot` mentions arrive from `@HarunsOpenClawMBA_bot`, lead response with `@HarunsOpenClawMBA_bot`

## 🚫 ACP Sessions DISABLED
- No ACP sessions, no acpx, no ACP subagent spawning
- All tasks use direct tool access

## Infrastructure

### Railway
- Project ID: `47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d`
- Service ID: `722f074a-4b8a-498e-8318-e5654f6f2d50`
- App live: `app.crontinel.com` → Railway

### Neon PostgreSQL (ACTIVE)
- Project: `org-falling-pine-93359503`
- Host: `ep-billowing-fog-andid5ey-pooler.c-6.us-east-1.aws.neon.tech`
- DB: `neondb` | User: `neondb_owner`
- SSL: required

### Cloudflare
- Email: `info@crontinel.com`
- Zone ID: `4c38cf818082c60b0ccc47d5e7e402ac`
- Account ID: `521e07f80bbfb2000edd88a75c73f34b`
- Zone managed via Cloudflare API

### Email (AgentMail)
- INBOX: `rayclaw@agentmail.to`
- All `info@crontinel.com` mail forwards here

### npm Packages
- `@crontinel/node` — github.com/crontinel/node
- `@crontinel/mcp-server` — github.com/crontinel/mcp-server
- Token in `~/.openclaw/secrets/ct.env`

### Packagist
- Org packages: `crontinel/php`, `crontinel/laravel`
- Personal token in `~/.openclaw/secrets/packagist.env`
- Webhook setup needs manual re-setup

## Secrets
All secrets in `~/.openclaw/secrets/ct.env` — NEVER in workspace files.
