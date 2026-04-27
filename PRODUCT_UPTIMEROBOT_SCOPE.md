# Crontinel Feature Scope: Competitive Feature Analysis

**Date:** 2026-04-10
**Project:** Crontinel app (HarunRRayhan/crontinel-app)

---

## 1. Feature Comparison Table

| Feature | Crontinel (Current) | UptimeRobot | Cronitor |
|---|---|---|---|
| Cron job monitoring (heartbeats) | Yes (exit code, duration, late detection) | Yes (basic heartbeats) | Yes (heartbeats) |
| Queue monitoring | Yes (depth, workers, failed jobs) | No | No |
| Horizon supervisor monitoring | Yes (native) | No | No |
| HTTP/HTTPS uptime checks | No | Yes (5min free, 60s paid) | Yes |
| SSL certificate expiry | No | Yes | Yes |
| Keyword/content monitoring | No | Yes | No |
| Ping monitoring | No | Yes | No |
| Port monitoring | No | Yes | No |
| E2E browser checks | No | No | Yes (Playwright) |
| Status pages | No | Yes (free basic) | Yes |
| Alert channels | Email only (planned) | Email, SMS, Slack, Telegram, Discord, PagerDuty, webhooks | Email, Slack, PagerDuty, OpsGenie, Teams, webhooks |
| Response time tracking | No | Yes | Yes |
| Maintenance windows | No | Yes | No |
| Global check locations | No | Yes (multiple regions) | Yes |
| API | Planned | Yes | Yes |
| MCP server for AI | Yes | No | No |
| Laravel-native integration | Yes (package install) | No (external only) | No (external only) |

---

## 2. Feature Value Rankings

Ranked by a composite of user demand among Laravel developers, technical effort, and revenue impact.

### Tier A: High Value, Build Before Launch

**1. Alert Channels (Slack, Discord, webhooks)**
- User demand: Critical. Monitoring without alerts is a dashboard nobody checks. Laravel devs live in Slack and Discord. Webhooks unlock everything else.
- Technical effort: 20-30 hours. Abstract a notification channel interface, build Slack (OAuth app or webhook URL), Discord (webhook URL), and generic webhook. Email should already exist or be trivial.
- Revenue impact: High. This is table stakes for converting free users to paid. Gate channel count or channel types by tier.
- Pricing: Free gets email only. Pro gets Slack + Discord + webhooks. Team gets SMS + PagerDuty + custom integrations.

**2. HTTP/HTTPS Uptime Monitoring (basic)**
- User demand: Very high. Every Laravel dev currently pairs their internal monitoring with UptimeRobot or similar. Capturing both in one tool is the single biggest reason to switch.
- Technical effort: 30-40 hours. Build a check runner (simple HTTP client with timeout tracking), a scheduler (configurable intervals), response time storage, and status history. Start with a single check location (your server). Do not build global locations pre-launch.
- Revenue impact: Very high. This is the "one-stop shop" pitch. It directly eliminates the need for a second tool.
- Pricing: Free gets 2 HTTP monitors, 5-min intervals. Pro gets unlimited HTTP monitors, 1-min intervals. Team gets 30-second intervals.

**3. SSL Certificate Expiry Monitoring**
- User demand: High. Expired SSL certs cause production outages. Laravel devs on shared hosting or manual cert setups need this.
- Technical effort: 8-12 hours. Piggyback on HTTP checks. When performing an HTTPS check, read the certificate expiry date from the TLS handshake. Store it, alert at 30/14/7/1 day thresholds.
- Revenue impact: Medium. It is a checkbox feature that makes the product feel complete. Low effort, high perceived value.
- Pricing: Included with HTTP monitoring on all tiers.

### Tier B: High Value, Build Shortly After Launch

**4. Status Pages**
- User demand: High. Teams want to share uptime status with stakeholders or customers without giving dashboard access.
- Technical effort: 40-50 hours. Public page with selected monitors, incident history, overall uptime percentage. Custom subdomain support (e.g., status.yourapp.com) for paid tiers. This is a meaningful chunk of UI work.
- Revenue impact: High. Status pages are a strong upgrade trigger. Free users want branding removed or custom domains.
- Pricing: Free gets a basic Crontinel-branded page. Pro gets custom subdomain + branding removal. Team gets custom domain + multiple pages.

**5. Response Time Tracking and Graphing**
- User demand: Medium-high. Developers want to see trends, not just up/down. Slow response times often precede outages.
- Technical effort: 15-20 hours. Store response time per check (already captured during HTTP checks). Build time-series graphs. Alerting on response time thresholds (e.g., "alert if > 2s for 3 consecutive checks").
- Revenue impact: Medium. Adds depth to the HTTP monitoring feature. Makes dashboards look professional.
- Pricing: Basic graphs on all tiers. Threshold alerts on Pro+. Extended history follows existing tier limits (7d/90d/1yr).

**6. Maintenance Windows**
- User demand: Medium. Teams doing deployments get false alerts. This solves that.
- Technical effort: 10-15 hours. Scheduled time windows where alerts are suppressed. Recurring windows (e.g., every Tuesday 2-3am) and one-off windows.
- Revenue impact: Low-medium. Quality-of-life feature that reduces churn on paid tiers.
- Pricing: Pro and Team only.

### Tier C: Lower Priority, Post-Traction

**7. Keyword/Content Monitoring**
- User demand: Low-medium. Useful for checking if a deploy broke a page (e.g., "500 error" appears, or expected content disappears). Niche but appreciated.
- Technical effort: 5-8 hours on top of HTTP monitoring. Parse response body, check for string presence/absence.
- Revenue impact: Low. Nice differentiator but not a purchase driver.
- Pricing: Pro+ only.

**8. Ping and Port Monitoring**
- User demand: Low for Laravel devs specifically. More relevant for sysadmins. Most Laravel devs care about HTTP, not raw TCP.
- Technical effort: 10-15 hours each. Ping requires ICMP (may need raw sockets or system calls). Port checks are simpler (TCP connect with timeout).
- Revenue impact: Low. Does not move the needle for the target audience.
- Pricing: Skip or add to Team tier only if requested.

**9. E2E Browser Checks**
- User demand: Low. Cool feature but massively complex. Cronitor offers it but it is not their core draw.
- Technical effort: 80-100+ hours. Running headless browsers, managing Playwright infrastructure, storing screenshots. This is practically a separate product.
- Revenue impact: Low relative to effort. Do not build this.
- Pricing: N/A. Do not build.

---

## 3. Technical Effort Summary

| Feature | Effort (hours) | Dependencies |
|---|---|---|
| Alert channels (Slack, Discord, webhooks) | 20-30 | None |
| HTTP uptime monitoring (basic) | 30-40 | None |
| SSL certificate expiry | 8-12 | HTTP monitoring |
| Status pages | 40-50 | HTTP monitoring, alert history |
| Response time tracking | 15-20 | HTTP monitoring |
| Maintenance windows | 10-15 | Alert system |
| Keyword monitoring | 5-8 | HTTP monitoring |
| Ping/port monitoring | 20-30 | None |

**Total for Tier A (pre-launch):** 58-82 hours
**Total for Tier B (post-launch):** 65-85 hours

---

## 4. Phased Roadmap

### Phase 1: Pre-Launch (weeks 1-3)
1. Alert channels: email, Slack webhook, Discord webhook, generic webhook
2. HTTP/HTTPS uptime monitoring with single check location and configurable intervals
3. SSL certificate expiry (rides on HTTP checks for free)

This gives Crontinel the "inside + outside" story at launch. A Laravel dev installs the package for Horizon/queue/cron monitoring, then adds HTTP checks for external visibility, all in one dashboard.

### Phase 2: Post-Launch Month 1 (weeks 4-7)
4. Response time tracking and threshold alerts
5. Status pages (basic version, Crontinel subdomain only)
6. Maintenance windows

### Phase 3: Post-Launch Month 2-3
7. Status pages with custom domains
8. Keyword monitoring
9. Additional alert channels (SMS via Twilio, PagerDuty)
10. Evaluate ping/port based on user requests

### Not Planned
- E2E browser checks (too much effort, wrong audience)
- Global check locations (start with one region, add more only when users ask; most Laravel apps serve one primary region anyway)

---

## 5. Pricing by Feature

| Feature | Free | Pro ($19/mo) | Team ($49/mo) |
|---|---|---|---|
| Internal monitors (Horizon, Queue, Cron) | 5 per app | Unlimited | Unlimited |
| HTTP monitors | 2 (5-min interval) | Unlimited (1-min interval) | Unlimited (30s interval) |
| SSL expiry alerts | Included with HTTP | Included with HTTP | Included with HTTP |
| Alert channels | Email only | + Slack, Discord, webhooks | + SMS, PagerDuty |
| Status pages | None | 1 page, Crontinel subdomain | Unlimited, custom domain |
| Response time graphs | 7-day history | 90-day history + threshold alerts | 1-year history + threshold alerts |
| Maintenance windows | None | Yes | Yes |
| Keyword monitoring | None | Yes | Yes |

The pricing philosophy: Free tier proves the product works. Pro captures the solo dev who wants HTTP monitoring and real alerts (this replaces their $7/mo UptimeRobot subscription, so $19 for internal + external monitoring is an easy sell). Team captures agencies and companies who need status pages, longer history, and premium alert channels.

---

**Core positioning remains unchanged:** Crontinel is the monitoring tool built for Laravel, from the inside out. HTTP monitoring is an additive layer that eliminates tool sprawl, not a pivot toward competing with generic uptime tools on breadth or global infrastructure.
