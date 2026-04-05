# Crontinel — Product Requirements Document

**Version:** 1.0
**Date:** 2026-04-05
**Status:** In progress — pre-coding reference
**Owner:** Harun Ray

> This PRD is the single source of truth for autonomous development. Every feature, flow, schema, API contract, UI spec, and acceptance criterion is defined here. No implementation decision should require asking the founder unless explicitly marked `[DECISION NEEDED]`.

---

## Table of Contents

1. [Product Vision](#1-product-vision)
2. [Target Users & Personas](#2-target-users--personas)
3. [Problem & Solution](#3-problem--solution)
4. [Feature Matrix](#4-feature-matrix)
5. [Information Architecture](#5-information-architecture)
6. [Data Models](#6-data-models)
7. [API Contracts](#7-api-contracts)
8. [User Flows](#8-user-flows)
9. [UI Specifications](#9-ui-specifications)
10. [Email & Notification Specs](#10-email--notification-specs)
11. [Billing & Subscription Flows](#11-billing--subscription-flows)
12. [OSS Package Spec](#12-oss-package-spec)
13. [Landing Page Spec](#13-landing-page-spec)
14. [Build Milestones & Acceptance Criteria](#14-build-milestones--acceptance-criteria)

---

## 1. Product Vision

**Crontinel** is a Laravel-native monitoring tool for Cron jobs and Queue workers. It goes deeper than generic cron monitors by reading Horizon internals — supervisor health, queue depth, dead-letter queues, failed job rates — things no other tool sees.

**One-line pitch:** "Cronitor monitors if your job ran. Crontinel knows if your Horizon is healthy."

**Model:** MIT open-source package (self-host free) + hosted SaaS at app.crontinel.com (convenience + premium features).

**North star metric:** Monthly Recurring Revenue (MRR)
**Secondary metric:** Active apps connected (SaaS) + Packagist downloads (OSS)

---

## 2. Target Users & Personas

### Persona A — Solo Laravel Developer ("The Indie Dev")
- Runs 1–3 Laravel apps in production
- Uses Horizon + Redis queues
- Has been burned by silent cron failures or queue backlogs
- Budget-conscious, wants value before paying
- Discovers via Reddit, Laracasts, Twitter/X, Packagist

**Pain:** "My cron job silently failed for 3 days and I had no idea."
**Job to be done:** Get alerted the moment something breaks, not after a customer complains.

### Persona B — Small Dev Team / Agency ("The Team Lead")
- 2–5 devs, managing 5–20 client Laravel apps
- Needs visibility across multiple apps from one dashboard
- Willing to pay for team access and longer history
- Discovers via word of mouth, GitHub, dev tool directories

**Pain:** "We manage 12 client apps and have no unified view of what's healthy."
**Job to be done:** One dashboard to see all apps' health, one place to set alerts.

### Persona C — Laravel Freelancer ("The Consultant")
- Builds and hands off apps, occasionally maintains them
- Wants to install the package and forget about it
- Self-hosted preferred (no recurring cost for clients)
- Discovers via Packagist, blog posts

**Pain:** "I want to give clients something that monitors their app without charging them monthly."
**Job to be done:** Composer install, configure, done. Works forever without SaaS.

---

## 3. Problem & Solution

### The problem

Generic cron monitors (Cronitor, Better Stack heartbeats, Forge Heartbeats) only check if a job "pinged back." They cannot answer:

- Is Horizon running? Is it paused?
- Which supervisor is down?
- How deep is the `emails` queue right now?
- Are failed jobs accumulating faster than they're being processed?
- Did the `send-invoices` job run late or not at all?

Laravel Forge now ships basic HTTP health checks — but Horizon internals remain completely unmonitored by any existing tool.

### The solution

Crontinel hooks into Laravel's internals:
- Reads Horizon's Redis keys directly (supervisor state, queue depths, failed rates)
- Wraps scheduled commands to record execution time, exit code, and duration
- Exposes a clean dashboard and configurable alerts
- Works standalone (self-hosted) or connects to our SaaS for hosted dashboards + multi-app views

---

## 4. Feature Matrix

### 4.1 OSS Package (self-hosted, MIT, free forever)

| Feature | Details |
|---|---|
| Horizon monitoring | Supervisor status, paused detection, failed jobs/min |
| Queue monitoring | Depth per queue, failed count, oldest job age |
| Cron tracking | Records every scheduled command run, exit code, duration |
| Blade dashboard | Dark UI at `/cron-sentinel`, auto-refreshes every 30s |
| CLI check | `php artisan crontinel:check` (table + JSON output) |
| Install command | `php artisan crontinel:install` |
| Alerts | Slack webhook, email (configurable in config file) |
| Config | Publish config with thresholds, channels, toggles |
| History | Unlimited (stored in user's own DB) |
| Multi-app | N/A (one install per app) |

### 4.2 SaaS Free Tier ($0)

Everything in OSS, plus:

| Feature | Limit |
|---|---|
| Apps | 1 |
| Monitors per app | 5 |
| History retention | 7 days |
| Team members | 1 (owner only) |
| Alerts | None (upgrade to Pro) |
| API access | No |
| Status page | No |

### 4.3 SaaS Pro ($19/mo or $182/yr)

Everything in Free, plus:

| Feature | Limit |
|---|---|
| Apps | 5 |
| Monitors per app | Unlimited |
| History retention | 90 days |
| Team members | 3 |
| Slack alerts | ✅ |
| PagerDuty alerts | ✅ |
| Email alerts | ✅ |
| Webhook alerts | ✅ |
| API access | ✅ (REST API) |
| Status page | ✅ (1 status page per app) |
| 14-day trial | ✅ (no credit card) |

### 4.4 SaaS Team ($49/mo or $470/yr)

Everything in Pro, plus:

| Feature | Limit |
|---|---|
| Apps | Unlimited |
| Monitors per app | Unlimited |
| History retention | 1 year |
| Team members | Unlimited |
| Status page | Unlimited |
| Priority support | ✅ (email, 24h response) |
| Custom alert rules | ✅ |

---

## 5. Information Architecture

### 5.1 crontinel.com (landing)

```
/                          Homepage
/pricing                   Pricing page
/features                  Feature breakdown
/about                     About / founder story
/changelog                 Product changelog

/blog                      Blog index (SSR)
/blog/[slug]               Blog post (SSR)
/vs/[competitor]           Comparison pages (SSR) — /vs/cronitor, /vs/better-stack
/use-cases/[slug]          Use case pages (SSR)
/integrations/[slug]       Integration pages (SSR) — /integrations/slack

/legal/privacy             Privacy policy
/legal/terms               Terms of service
```

### 5.2 app.crontinel.com (SaaS)

```
Auth:
  /login
  /register
  /forgot-password
  /reset-password/[token]

Onboarding:
  /onboarding                Step 1: create team
  /onboarding/app            Step 2: create first app
  /onboarding/install        Step 3: install package

Main app (requires auth):
  /dashboard                 Overview — all apps health summary
  /apps                      App list
  /apps/create               New app form
  /apps/[slug]               App dashboard (Horizon + queues + crons)
  /apps/[slug]/monitors      Monitor list + toggle
  /apps/[slug]/alerts        Alert channel config
  /apps/[slug]/api-key       API key management
  /apps/[slug]/settings      App settings (name, timezone, delete)

  /team                      Team settings
  /team/members              Invite / manage members
  /team/billing              Subscription, upgrade, invoices
  /team/settings             Team name, slug

  /profile                   User profile, password change
  /notifications             Notification preferences
```

### 5.3 docs.crontinel.com (Astro Starlight)

```
/                           Introduction
/installation               Installation guide
/configuration              Config reference
/monitors/horizon           Horizon monitor docs
/monitors/queues            Queue monitor docs
/monitors/cron              Cron monitor docs
/alerts                     Alert channels setup
/saas/connecting            Connecting to hosted SaaS
/saas/api                   REST API reference
/self-hosting               Self-hosting guide
/upgrade                    Upgrade / changelog
```

---

## 6. Data Models

### 6.1 users

```sql
id                  bigint PK
name                varchar(255)
email               varchar(255) unique
email_verified_at   timestamp nullable
password            varchar(255)
remember_token      varchar(100) nullable
current_team_id     bigint FK → teams.id nullable
created_at          timestamp
updated_at          timestamp
```

### 6.2 teams

```sql
id                  bigint PK
name                varchar(255)
slug                varchar(100) unique
plan                enum('free','pro','team') default 'free'
trial_ends_at       timestamp nullable
created_at          timestamp
updated_at          timestamp
```

### 6.3 team_user (pivot)

```sql
id                  bigint PK
team_id             bigint FK → teams.id
user_id             bigint FK → users.id
role                enum('owner','admin','member')
created_at          timestamp
updated_at          timestamp

UNIQUE (team_id, user_id)
```

### 6.4 team_invitations

```sql
id                  bigint PK
team_id             bigint FK → teams.id
email               varchar(255)
role                enum('admin','member')
token               varchar(255) unique
expires_at          timestamp
created_at          timestamp
```

### 6.5 apps

```sql
id                  bigint PK
team_id             bigint FK → teams.id
name                varchar(255)
slug                varchar(100)
api_key             varchar(64) unique    -- used by package to auth
timezone            varchar(50) default 'UTC'
is_active           boolean default true
last_ping_at        timestamp nullable
created_at          timestamp
updated_at          timestamp

UNIQUE (team_id, slug)
INDEX (api_key)
```

### 6.6 monitors

```sql
id                  bigint PK
app_id              bigint FK → apps.id
type                enum('horizon','queue','cron')
name                varchar(255)           -- queue name or cron command
config              json                   -- type-specific thresholds
is_enabled          boolean default true
created_at          timestamp
updated_at          timestamp

UNIQUE (app_id, type, name)
```

### 6.7 monitor_events

```sql
id                  bigint PK
monitor_id          bigint FK → monitors.id
status              enum('ok','warning','critical','unknown')
payload             json                   -- raw data from agent
occurred_at         timestamp
created_at          timestamp

INDEX (monitor_id, occurred_at)
-- Pruned based on team plan (7d / 90d / 1yr)
```

### 6.8 alert_channels

```sql
id                  bigint PK
app_id              bigint FK → apps.id
type                enum('slack','email','pagerduty','webhook')
name                varchar(255)
config              json                   -- webhook_url, email, routing_key, etc.
is_enabled          boolean default true
created_at          timestamp
updated_at          timestamp
```

### 6.9 alert_events

```sql
id                  bigint PK
monitor_id          bigint FK → monitors.id
channel_id          bigint FK → alert_channels.id
status              enum('fired','resolved','failed')
message             text
sent_at             timestamp nullable
created_at          timestamp
```

### 6.10 crontinel_runs (OSS package + SaaS)

```sql
id                  bigint PK
app_id              bigint FK → apps.id nullable  -- null if self-hosted OSS
command             varchar(500)
ran_at              timestamp
exit_code           integer
duration_ms         integer
output              text nullable
created_at          timestamp

INDEX (app_id, command, ran_at)
```

### 6.11 subscriptions (Laravel Cashier managed)

Cashier creates and manages this table automatically. Attached to `teams` model (not `users`).

---

## 7. API Contracts

### 7.1 Agent API (package → SaaS)

Base URL: `https://app.crontinel.com/api/v1`
Auth: `Authorization: Bearer {api_key}` header on all requests.

---

#### `POST /ingest/ping`
Package sends a heartbeat every 60 seconds.

**Request:**
```json
{
  "agent_version": "1.0.0",
  "php_version": "8.3.0",
  "laravel_version": "12.0.0",
  "horizon": {
    "enabled": true,
    "status": "running",
    "supervisors": [
      { "name": "supervisor-1", "status": "running", "processes": 3, "queue": "default" }
    ],
    "failed_per_minute": 0.5,
    "paused_at": null
  },
  "queues": [
    {
      "connection": "redis",
      "queue": "default",
      "depth": 12,
      "failed_count": 2,
      "oldest_job_age_seconds": 45
    }
  ]
}
```

**Response:** `200 OK`
```json
{ "ok": true, "config_hash": "abc123" }
```

If `config_hash` differs from last sent, package should call `GET /config` to refresh thresholds.

---

#### `POST /ingest/cron`
Package sends this after every scheduled command execution.

**Request:**
```json
{
  "command": "inspire",
  "expression": "0 9 * * *",
  "ran_at": "2026-04-05T15:00:00Z",
  "exit_code": 0,
  "duration_ms": 124,
  "output": null
}
```

**Response:** `201 Created`
```json
{ "ok": true, "run_id": 4821 }
```

---

#### `POST /ingest/event`
Package sends this for discrete events (job failed, queue spike, supervisor down).

**Request:**
```json
{
  "type": "job_failed",
  "severity": "critical",
  "monitor_type": "queue",
  "monitor_name": "emails",
  "payload": {
    "job_class": "App\\Jobs\\SendInvoice",
    "exception": "Connection refused",
    "failed_at": "2026-04-05T15:01:22Z"
  }
}
```

**Response:** `201 Created`
```json
{ "ok": true, "event_id": 9921 }
```

---

#### `GET /config`
Package fetches its current thresholds from SaaS (called on startup + when config_hash changes).

**Response:**
```json
{
  "horizon": {
    "enabled": true,
    "failed_jobs_per_minute_threshold": 5,
    "supervisor_alert_after_seconds": 60
  },
  "queues": {
    "enabled": true,
    "watch": ["default", "emails"],
    "depth_alert_threshold": 1000,
    "wait_time_alert_seconds": 300
  },
  "cron": {
    "enabled": true,
    "late_alert_after_seconds": 120
  }
}
```

---

### 7.2 REST API (Pro/Team users)

Base URL: `https://app.crontinel.com/api/v1`
Auth: `Authorization: Bearer {personal_access_token}` (Laravel Sanctum)

---

#### `GET /apps`
List all apps in current team.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "My Laravel App",
      "slug": "my-laravel-app",
      "last_ping_at": "2026-04-05T15:01:00Z",
      "health": "ok"
    }
  ]
}
```

---

#### `GET /apps/{slug}/status`
Full health snapshot for one app.

**Response:**
```json
{
  "app": { "id": 1, "name": "My Laravel App" },
  "horizon": {
    "status": "running",
    "supervisors": [...],
    "failed_per_minute": 0.0,
    "healthy": true
  },
  "queues": [
    { "queue": "default", "depth": 5, "failed_count": 0, "healthy": true }
  ],
  "crons": [
    { "command": "inspire", "expression": "0 9 * * *", "status": "ok", "last_run_at": "..." }
  ],
  "checked_at": "2026-04-05T15:01:00Z"
}
```

---

#### `GET /apps/{slug}/monitors`
List monitors for an app.

#### `GET /apps/{slug}/events?monitor_id=X&from=Y&to=Z`
Paginated event history.

#### `GET /apps/{slug}/cron-runs?command=X&from=Y&to=Z`
Paginated cron run history.

---

## 8. User Flows

### 8.1 New user signup + onboarding

```
1. User lands on crontinel.com → clicks "Start free"
2. /register — email + password (no credit card)
3. Email verification sent → user clicks link
4. Redirected to /onboarding
5. Step 1: Create team — enter team name
6. Step 2: Create first app — enter app name, select timezone
7. Step 3: Install — shown:
     composer require harunrrayhan/crontinel
     php artisan crontinel:install --saas-key={api_key}
8. Dashboard loads, shows "Waiting for first ping..."
9. When first ping arrives, dashboard updates live (Livewire polling)
10. 14-day Pro trial starts automatically at registration
```

### 8.2 Package installation (self-hosted)

```
1. composer require harunrrayhan/crontinel
2. php artisan crontinel:install
   - Publishes config/crontinel.php
   - Runs migrations (creates crontinel_runs table)
   - Prints dashboard URL
3. Add CRON_SENTINEL_PATH=cron-sentinel to .env (optional)
4. Visit /cron-sentinel
5. To connect to SaaS: add CRON_SENTINEL_API_KEY=xxx to .env
```

### 8.3 Alert firing flow

```
1. Package sends POST /ingest/ping or POST /ingest/event
2. SaaS ingests data, stores monitor_event
3. AlertEvaluationJob runs (dispatched after ingest)
4. Checks monitor_event against monitor.config thresholds
5. If threshold crossed AND no open alert for same monitor:
   a. Creates alert_event record (status=fired)
   b. Dispatches SendAlertJob per configured channel
   c. SendAlertJob sends Slack/email/PagerDuty/webhook
6. When next ping shows recovery:
   a. Creates alert_event (status=resolved)
   b. Sends "resolved" notification to same channels
```

### 8.4 Trial → upgrade flow

```
1. User on Pro trial, day 12 → email sent: "2 days left on trial"
2. Day 14 → trial ends, team.plan drops to 'free'
3. User logs in → upgrade banner shown on every page
4. Clicks "Upgrade" → /team/billing
5. Selects Pro or Team, monthly or annual
6. Stripe Checkout (via Cashier) — card entry
7. Stripe webhook → subscription created → team.plan updated
8. User redirected to dashboard, banner gone
```

### 8.5 Team invitation flow

```
1. Owner goes to /team/members → "Invite member"
2. Enters email + role (admin/member)
3. team_invitations record created, invitation email sent
4. Invitee clicks link → /accept-invitation/{token}
5. If not registered: shown register form (pre-filled email)
6. If registered: clicks "Accept"
7. team_user record created, invitation deleted
8. Invitee redirected to team dashboard
```

---

## 9. UI Specifications

### 9.1 Design system

- **Theme:** Dark by default (monitors are viewed under stress — dark is easier on eyes)
- **Colors:** Gray-950 background, Gray-900 cards, Green-400 healthy, Red-400 critical, Yellow-400 warning, Blue-400 info
- **Font:** System monospace for metric values; system sans-serif for UI text
- **Framework:** Tailwind CSS + Livewire 3 + Alpine.js
- **No custom JS build step** — Alpine via CDN, Tailwind via Vite in Laravel (or CDN in dev)

### 9.2 App dashboard (`/apps/[slug]`)

**Layout:** Three sections stacked vertically, each collapsible

**Section 1 — Horizon**
```
┌─────────────────────────────────────────────────────┐
│ HORIZON                              [running ✓]     │
├────────────┬──────────────┬─────────────────────────┤
│ Status     │ Supervisors  │ Failed / min             │
│ running    │ 2 active     │ 0.0                      │
├────────────┴──────────────┴─────────────────────────┤
│ supervisor-1  running  3 workers  queue:default      │
│ supervisor-2  running  2 workers  queue:emails       │
└─────────────────────────────────────────────────────┘
```

**Section 2 — Queues**
```
┌──────────────────────────────────────────────────────────┐
│ QUEUES                                                    │
├──────────┬────────┬────────┬──────────────┬──────────────┤
│ Queue    │ Depth  │ Failed │ Oldest job   │ Health       │
├──────────┼────────┼────────┼──────────────┼──────────────┤
│ default  │ 5      │ 0      │ 12s          │ ✓ ok         │
│ emails   │ 1,204  │ 3      │ 8m 32s       │ ✗ alert      │
└──────────┴────────┴────────┴──────────────┴──────────────┘
```
Row highlighted red when unhealthy.

**Section 3 — Scheduled Commands**
```
┌──────────────────────────────────────────────────────────────────┐
│ SCHEDULED COMMANDS                                                │
├──────────────────────┬──────────┬────────────┬────────┬──────────┤
│ Command              │ Schedule │ Last run   │ Dur    │ Status   │
├──────────────────────┼──────────┼────────────┼────────┼──────────┤
│ inspire              │ 0 9 * *  │ 6h ago     │ 124ms  │ ✓ ok     │
│ send-invoices        │ 0 0 1 *  │ 5d ago     │ 2.1s   │ ✓ ok     │
│ cleanup-old-logs     │ 0 3 * *  │ 23h ago    │ —      │ ⚠ late   │
└──────────────────────┴──────────┴────────────┴────────┴──────────┘
```

**Auto-refresh:** Livewire polling every 30 seconds. Show "Last updated: Xs ago" counter.

### 9.3 Main dashboard (`/dashboard`)

Shows summary cards for all connected apps.

```
┌─────────────────────────┐ ┌─────────────────────────┐
│ My Laravel App          │ │ Client Project X        │
│ ✓ All healthy           │ │ ✗ 1 alert               │
│ Horizon: running        │ │ Horizon: paused ⚠       │
│ Queues: 2 ok            │ │ Queues: 3 ok             │
│ Crons: 4 ok             │ │ Crons: 2 ok, 1 late      │
│ Last ping: 28s ago      │ │ Last ping: 4m ago        │
└─────────────────────────┘ └─────────────────────────┘
```

### 9.4 Navigation

**Sidebar (desktop):**
```
[Crontinel logo]

Dashboard
Apps
  ↳ My Laravel App
  ↳ Client X
  ↳ + Add app

──────────────
Team
Billing
Settings
──────────────
Docs ↗
Status ↗
```

**Top bar:** Team switcher (if user is in multiple teams), user avatar dropdown (profile, logout).

### 9.5 Alert channel setup (`/apps/[slug]/alerts`)

Form with tabs: Slack | Email | PagerDuty | Webhook

**Slack:**
```
Webhook URL: [input]
Channel:     [input, optional, overrides webhook default]
[Test] [Save]
```

**Email:**
```
To: [input, comma-separated]
[Test] [Save]
```

Test button sends a test alert immediately.

---

## 10. Email & Notification Specs

All emails sent via Resend. From: `alerts@crontinel.com`. Reply-to: `support@crontinel.com`.

### 10.1 Welcome email
- **Trigger:** Email verified
- **Subject:** "Welcome to Crontinel — let's connect your first app"
- **Body:** Short welcome, link to onboarding, link to docs

### 10.2 Trial reminder (day 12)
- **Trigger:** `trial_ends_at` is 2 days away, scheduled job
- **Subject:** "Your Crontinel Pro trial ends in 2 days"
- **Body:** What you'll lose, upgrade CTA button

### 10.3 Trial expired
- **Trigger:** `trial_ends_at` has passed, plan set to free
- **Subject:** "Your Crontinel Pro trial has ended"
- **Body:** Features now unavailable, upgrade CTA

### 10.4 Alert fired
- **Trigger:** alert_event created (status=fired), channel=email
- **Subject:** "🔴 [App Name] — [Monitor Name] is critical"
- **Body:** What's wrong, current values vs threshold, link to dashboard

### 10.5 Alert resolved
- **Trigger:** alert_event created (status=resolved), channel=email
- **Subject:** "✅ [App Name] — [Monitor Name] resolved"
- **Body:** What recovered, duration of incident

### 10.6 Team invitation
- **Trigger:** Invitation created
- **Subject:** "[Owner name] invited you to [Team Name] on Crontinel"
- **Body:** Accept button (link expires in 72 hours)

### 10.7 Payment failed
- **Trigger:** Stripe `invoice.payment_failed` webhook
- **Subject:** "Action required — Crontinel payment failed"
- **Body:** Update payment method CTA, grace period info (3 days)

---

## 11. Billing & Subscription Flows

### 11.1 Plans + Stripe products

| Crontinel Plan | Stripe Product | Monthly Price ID | Annual Price ID |
|---|---|---|---|
| Pro | `prod_crontinel_pro` | `price_pro_monthly` | `price_pro_annual` |
| Team | `prod_crontinel_team` | `price_team_monthly` | `price_team_annual` |

Annual prices = monthly × 12 × 0.8 (20% discount).

### 11.2 Subscription states

```
new user → [free, trial_ends_at = now+14d, plan=free]
                    ↓ trial active: features gated as Pro
trial ends → [plan=free, trial_ends_at in past]
upgrade → Stripe Checkout → webhook → [plan=pro or team]
payment fails → 3-day grace → [plan=free if not resolved]
cancel → at period end → [plan=free]
```

### 11.3 Plan enforcement

Plan limits enforced via a `PlanEnforcement` service class:

```php
// Check before allowing action
PlanEnforcement::check($team, 'create_app');  // throws PlanLimitException if over limit
PlanEnforcement::check($team, 'add_monitor');
PlanEnforcement::check($team, 'invite_member');
```

UI shows upgrade prompts when limit reached — never hard errors without explanation.

### 11.4 Stripe webhooks handled

| Event | Action |
|---|---|
| `checkout.session.completed` | Create subscription, update team.plan |
| `customer.subscription.updated` | Update team.plan |
| `customer.subscription.deleted` | Set team.plan = free |
| `invoice.payment_failed` | Send payment failed email, start grace period |
| `invoice.paid` | Clear any payment_failed flags |

---

## 12. OSS Package Spec

### 12.1 Package identity

- **Composer name:** `harunrrayhan/crontinel`
- **Namespace:** `CronSentinel\`
- **Laravel auto-discovery:** Yes (`CronSentinelServiceProvider`)
- **Min requirements:** PHP 8.2+, Laravel 11+

### 12.2 Configuration (`config/crontinel.php`)

All values have sane defaults. Zero required config for local use.

| Key | Default | Description |
|---|---|---|
| `path` | `cron-sentinel` | Dashboard URL path |
| `middleware` | `['web', 'auth']` | Dashboard middleware |
| `saas_key` | `env('CRON_SENTINEL_API_KEY')` | API key for SaaS reporting |
| `saas_url` | `https://app.crontinel.com` | SaaS endpoint (overridable for self-hosted SaaS) |
| `horizon.enabled` | `true` | Toggle Horizon monitoring |
| `horizon.supervisor_alert_after_seconds` | `60` | Alert threshold |
| `horizon.failed_jobs_per_minute_threshold` | `5` | Failed rate threshold |
| `queues.enabled` | `true` | Toggle queue monitoring |
| `queues.watch` | `[]` | Empty = auto-discover |
| `queues.depth_alert_threshold` | `1000` | Queue depth alert |
| `queues.wait_time_alert_seconds` | `300` | Oldest job age alert |
| `cron.enabled` | `true` | Toggle cron monitoring |
| `cron.late_alert_after_seconds` | `120` | Late detection threshold |
| `cron.retain_days` | `30` | History retention |
| `alerts.channel` | `null` | `slack`, `mail`, or `null` |
| `alerts.slack.webhook_url` | `env('CRON_SENTINEL_SLACK_WEBHOOK')` | Slack webhook |
| `alerts.mail.to` | `env('CRON_SENTINEL_ALERT_EMAIL')` | Alert email |

### 12.3 Artisan commands

| Command | Description |
|---|---|
| `crontinel:install` | Publish config, run migrations, print dashboard URL |
| `crontinel:check` | Print health table to console (exit 1 if any alerts) |
| `crontinel:check --format=json` | JSON output (for CI/CD integration) |

### 12.4 Scheduler integration

To track cron runs, user adds one line to `routes/console.php` or `Kernel.php`:

```php
use CronSentinel\Facades\CronSentinel;

// Wrap existing scheduled commands:
CronSentinel::track($schedule);
// OR add to individual commands:
$schedule->command('inspire')->everyHour()->withoutOverlapping();
// CronSentinel hooks into the scheduler automatically via event listeners
```

Package listens to Laravel's `ScheduledTaskFinished` and `ScheduledTaskFailed` events — no manual wrapping needed.

### 12.5 SaaS reporting (when api_key configured)

If `CRON_SENTINEL_API_KEY` is set:
- Package sends `POST /ingest/ping` to SaaS every 60 seconds via a queued job
- Package sends `POST /ingest/cron` after each scheduled task
- Package sends `POST /ingest/event` on threshold crossings
- All outbound calls are fire-and-forget (queued, non-blocking)
- If SaaS is unreachable, fails silently (never breaks user's app)

---

## 13. Landing Page Spec

### 13.1 Homepage sections

**Hero:**
- Headline: "Your Laravel Horizon has been silently broken for 3 days."
- Sub: "Crontinel monitors Horizon internals, queue depth, and cron health — things generic monitors can't see."
- CTA: "Start free — no credit card" → /register
- Secondary CTA: "Self-host for free" → /docs/installation
- Social proof: "Used by X Laravel developers" (placeholder until real data)

**Problem section:**
- "Forge Heartbeats tell you a job ran. They don't tell you your Horizon supervisor is paused."
- Three problem cards: Silent cron failures / Queue backlog accumulation / Horizon supervisor crashes

**Solution section:**
- Dashboard screenshot (dark UI)
- Three feature callouts: Horizon internals / Queue depth alerts / Cron run history

**Feature grid:**
- 6 features with icons: Horizon monitoring, Queue depth, Cron tracking, Slack alerts, Multi-app, REST API

**Pricing section:**
- Three-column pricing cards (Free / Pro / Team)
- Monthly/Annual toggle (shows 20% discount)
- "Start free" CTAs

**OSS callout:**
- "Also available as a free, open-source Laravel package"
- `composer require harunrrayhan/crontinel`
- GitHub stars badge

**Footer:**
- Links: Docs, Changelog, GitHub, Twitter/X, Privacy, Terms
- "Built by Harun Ray"

### 13.2 Comparison pages (`/vs/[competitor]`)

Template structure:
1. Hero: "Crontinel vs [Competitor]"
2. Side-by-side feature table
3. Key differentiator section
4. Pricing comparison
5. CTA to try free

Competitors to create: `cronitor`, `better-stack`, `oh-dear`, `forge-heartbeats`

### 13.3 SEO pages content plan

| Type | Count (v1) | Source |
|---|---|---|
| Blog posts | 10 | MDX in repo |
| Comparison pages | 4 | MDX in repo |
| Use case pages | 6 | MDX in repo |
| Integration pages | 4 | MDX in repo |

---

## 14. Build Milestones & Acceptance Criteria

### Milestone 0 — Landing page (1 day)
**Done when:**
- [ ] `crontinel.com` resolves (Cloudflare DNS configured)
- [ ] Homepage loads with hero, features, pricing, OSS callout
- [ ] Email capture form works (submits to Resend audience)
- [ ] `/pricing` page exists with correct plan details
- [ ] Deployed to CF Pages on push to `main`

---

### Milestone 1 — OSS Package MVP (2–3 weeks)
**Done when:**
- [ ] `composer require harunrrayhan/crontinel` installs cleanly on fresh Laravel 11 + 12
- [ ] `php artisan crontinel:install` publishes config + runs migrations
- [ ] Dashboard at `/cron-sentinel` loads in browser with dark theme
- [ ] HorizonMonitor returns correct status when Horizon is running
- [ ] HorizonMonitor returns `running=false` when Horizon is stopped
- [ ] QueueMonitor returns correct depth for database driver queues
- [ ] CronMonitor records runs after scheduler executes
- [ ] `php artisan crontinel:check` exits 0 when healthy, 1 when alerts
- [ ] Slack alert fires when queue depth exceeds threshold
- [ ] Email alert fires when cron job exits with non-zero code
- [ ] All Pest unit tests pass
- [ ] README covers installation, config, and CLI usage
- [ ] Package submitted to Packagist

---

### Milestone 2 — SaaS Auth + Teams (1 week)
**Done when:**
- [ ] `/register` creates user + team + starts 14-day trial
- [ ] `/login` authenticates and redirects to `/dashboard`
- [ ] Email verification works
- [ ] Password reset works
- [ ] Onboarding flow (3 steps) completes and creates app + api_key
- [ ] `/apps` lists apps for current team
- [ ] `/apps/create` creates app with unique api_key
- [ ] Team invitation email sends and works end-to-end
- [ ] `/team/members` shows members and pending invitations
- [ ] Role enforcement: members cannot invite or delete apps; owners can

---

### Milestone 3 — SaaS Agent Integration (1 week)
**Done when:**
- [ ] `POST /api/v1/ingest/ping` accepts valid payload and stores monitor_events
- [ ] `POST /api/v1/ingest/cron` creates crontinel_runs record
- [ ] `POST /api/v1/ingest/event` creates monitor_event with correct severity
- [ ] Invalid API key returns 401
- [ ] Rate limit: max 120 requests/minute per api_key (returns 429 if exceeded)
- [ ] App dashboard shows live data from package pings
- [ ] Dashboard auto-refreshes every 30s via Livewire
- [ ] "Waiting for first ping..." state shown when no data yet
- [ ] Main `/dashboard` shows health summary cards for all apps

---

### Milestone 4 — Billing (1 week)
**Done when:**
- [ ] Stripe products and prices created in Stripe dashboard
- [ ] `/team/billing` shows current plan, next billing date, invoice history
- [ ] Monthly → annual toggle works on pricing page
- [ ] Stripe Checkout flow completes and updates team.plan
- [ ] Downgrade to free works (at period end)
- [ ] Payment failed webhook handled + email sent
- [ ] Plan limits enforced: Free can't create >1 app, >5 monitors, or invite members
- [ ] Upgrade prompt shown when limit reached
- [ ] Trial banner shown during trial period
- [ ] Trial expiry drops plan to free, email sent

---

### Milestone 5 — Alerts (1 week)
**Done when:**
- [ ] Slack alert fires when Horizon stops
- [ ] Slack alert fires when queue depth exceeds threshold
- [ ] Slack alert fires when cron job is late or failed
- [ ] "Resolved" Slack alert fires when issue clears
- [ ] Email alert works for all same conditions
- [ ] PagerDuty webhook fires (Basic integration)
- [ ] Custom webhook fires with correct JSON payload
- [ ] "Test" button on alert channel sends test message
- [ ] Alert deduplication: same alert doesn't fire twice for same open issue

---

### Milestone 6 — MCP Server + Polish + Launch (2 weeks)

**Why MCP at launch:** 12,000+ MCP servers exist in 2026. MCP is the de facto standard backed by OpenAI, Google, Microsoft, and AWS. Being MCP-native at launch is a headline no Laravel monitoring competitor has. Cronitor has zero MCP support.

**MCP vision:** Crontinel exposes an MCP server so AI coding assistants (Claude Code, Cursor, GitHub Copilot, etc.) can query cron job status, queue health, and manage alerts directly from their chat interface.

**Example:**
> Developer in Claude Code: "Did my `send-invoices` job run last night?"
> Claude Code → Crontinel MCP `get_cron_status` → answer inline, no browser needed

**MCP tools to expose:**

| Tool | Description |
|---|---|
| `list_scheduled_jobs` | List all monitored cron commands and schedules |
| `get_cron_status` | Last run status, exit code, duration for a command |
| `get_queue_status` | Depth, failed count, oldest job age per queue |
| `get_horizon_status` | Horizon supervisor health snapshot |
| `list_recent_alerts` | Fired alerts in the last N hours |
| `create_alert` | Create a new alert threshold for a monitor |
| `acknowledge_alert` | Dismiss an active alert |

**Implementation:**
1. SaaS exposes MCP-compatible endpoint at `app.crontinel.com/mcp`
2. Auth via existing `api_key`
3. Publish `@crontinel/mcp-server` npm package (thin wrapper over REST API)
4. Users add to their AI assistant config:

```json
{
  "crontinel": {
    "command": "npx",
    "args": ["-y", "@crontinel/mcp-server"],
    "env": {
      "CRONTINEL_API_KEY": "your-api-key",
      "CRONTINEL_APP": "your-app-slug"
    }
  }
}
```

**Done when:**
- [ ] `status.crontinel.com` (Gatus) monitors app.crontinel.com + api endpoints
- [ ] `docs.crontinel.com` (Astro Starlight) has Installation, Configuration, and all monitor docs
- [ ] OpenAPI spec published at `app.crontinel.com/openapi.json`
- [ ] REST API (Pro) returns correct data for `/apps` and `/apps/{slug}/status`
- [ ] `@crontinel/mcp-server` npm package published and installable
- [ ] All 7 MCP tools implemented and tested
- [ ] Claude Code MCP integration documented and tested end-to-end
- [ ] MCP setup guide on `docs.crontinel.com`
- [ ] Listed on MCP registries (Smithery, Glama, mcp.so)
- [ ] All onboarding emails send correctly
- [ ] Mobile-responsive dashboard (usable on phone)
- [ ] Error pages (404, 500) styled
- [ ] Privacy Policy and Terms of Service pages exist
- [ ] Product Hunt launch checklist complete
- [ ] First user can complete full flow: register → install package → see data → get alerted

---

*End of PRD v1.2*
