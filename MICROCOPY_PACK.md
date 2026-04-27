# Crontinel microcopy pack

Date: 2026-04-09
Purpose: patch-ready copy for app, onboarding, alerts, and docs contexts
Tone: clear, calm, specific

## Alert states

### Critical alert title options
- Critical issue detected
- Background work needs attention
- Important job flow appears unhealthy

### Critical alert body options
- Crontinel detected a failure or silent stoppage that could affect customer-facing work.
- A scheduled task, queue, or worker may have stopped behaving normally.
- Something important in your background workflow needs a check.

### Warning alert title options
- Something looks off
- Warning sign detected
- Background activity needs a look

### Warning alert body options
- Work is still flowing, but recent signals suggest something may be degrading.
- This is not a confirmed failure, but it is worth checking before it becomes one.
- A queue, worker, or scheduled task is behaving outside its usual pattern.

### Recovery alert title options
- Recovered and reporting normally
- Activity is back to normal
- Background work has recovered

### Recovery alert body options
- The app is reporting healthy activity again.
- The issue appears resolved and monitoring is back to normal.
- Recent checks look healthy again.

## Empty-state copy

### Dashboard with no apps
**Heading**
- Monitor your first Laravel app in a few minutes

**Body**
- Create your app, copy the install snippet, and wait for the first ping. You can fine-tune queues and alerts after setup.

**CTA**
- Add your app

**Support line**
- Need a hand? Start with the docs.

### Apps list empty state
**Heading**
- No monitored apps yet

**Body**
- Add your first app to track scheduler runs, queue health, and silent failures before users notice them.

**CTA**
- Add your first app

### Alert channels empty state
**Heading**
- No alert channels yet

**Body**
- Add one now so failed jobs and silent issues reach you before users do.

**CTA**
- Add alert channel

### Live monitor waiting for first ping
**Heading**
- Waiting for your first ping

**Body**
- Once the package is installed and your scheduler or queue activity runs, data will appear here automatically.

**Reassurance line**
- No data yet does not always mean something is wrong. New apps stay empty until the first reported activity.

**Quick checklist label**
- Check these first

**Checklist items**
- `CRONTINEL_API_KEY` is set in `.env`
- `php artisan crontinel:install` has been run
- `schedule:run` is active on the server
- a scheduled task or queue job has run at least once

### No Horizon data
- No Horizon data yet. If you do not use Laravel Horizon, you can ignore this. If you do, check that Horizon is running and reporting normally.

### No queue data
- No queue activity reported yet. If this app uses queues, run a job or wait for the next worker cycle.

### No cron runs yet
- No cron runs recorded yet. Once your scheduler fires and the app reports in, recent runs will show up here.

**Optional helper line**
- If this stays empty after setup, double-check your server crontab and app configuration.

## Loading-state copy

### Live monitor
- Updates every 30 seconds
- Refreshing monitor data…

### App creation
- Creating app…
- Saving app…

### Onboarding step save
- Saving…
- Continuing…

### Alert test action
- Sending…
- Test alert sent. Check your channel for delivery.

### Alert channel creation
- Adding channel…
- Channel added successfully.

## Onboarding copy

### Step 1
**Title**
- Welcome to Crontinel

**Subtitle**
- Let’s get you live in 3 quick steps.

**Field help**
- You can rename this later.

**Button**
- Continue

### Step 2
**Title**
- Add your first app

**Subtitle**
- Each app gets its own API key, dashboard, and alert context.

**App name help**
- Use the name your team will recognize in alerts.

**Timezone help**
- Choose the timezone your team uses to judge “late” scheduled work.

**Button**
- Add app

### Step 3
**Title**
- Install the package

**Subtitle**
- Run these steps in your Laravel project, then wait for the first activity to report in.

**After-install expectation**
- After the first scheduler or queue activity, this app will start showing data on the dashboard.

**Verification nudge**
- If you want to verify setup quickly, trigger a scheduled task or queue job after installation.

**Final button**
- I’ve installed it, show my dashboard

## Warning and helper copy for docs or UI

### Scheduler warning
- A scheduler that never fires usually fails silently. Make sure `schedule:run` is active on the server.

### Queue warning
- A worker can look alive while important jobs are still backing up.

### Horizon helper
- Horizon status is useful, but queue depth and oldest pending work often tell the real story faster.

### Setup reassurance
- You do not need every feature configured on day one. Start with reporting, then add alerts once the app is live.

### Upgrade framing for alerts
- Upgrade to add Slack, email, or webhook alerts when critical jobs fail or stop reporting.

## Doc-friendly plain-language lines

- Crontinel helps you catch the background failures users usually discover first.
- Uptime tells you the app responds. It does not tell you the scheduler ran or the queue drained.
- Running is not the same as working.
- If nothing is showing yet, check for first activity before assuming setup is broken.
- New apps often stay quiet until the first real scheduler or queue event arrives.
