# Task: Write SEO Blog Posts for Crontinel

## Goal
Write 3 blog posts for the Crontinel landing site (crontinel.com/blog) that answer common Laravel cron/queue questions. Target: rank for long-tail search queries from developers experiencing cron/queue problems.

## Blog posts to write

### Post 1: "Why Your Laravel Cron Job Isn't Running (And How to Fix It)"
- Common reasons cron jobs silently fail in Laravel
- How to debug: check scheduler logs, check exit codes, verify the cron entry
- How Crontinel detects silent failures

### Post 2: "Laravel Queue Worker Died: How to Detect and Recover"
- Horizon supervisor death scenarios (OOM, segfault, restarts)
- Signs: jobs piling up, queue depth spiking
- How Crontinel monitors Horizon supervisors

### Post 3: "Laravel Cron vs Queue Monitoring — What's the Difference?"
- Uptime monitors miss scheduler failures
- Queue depth monitors miss Horizon death
- Why you need both for full coverage

## Output location
Create .md files in `landing/content/blog/` (or appropriate blog content directory)
Check the landing repo structure: `~/Work/crontinel/landing/`

## Requirements
- Conversational, technical but accessible
- Include real code examples where helpful
- End with a soft CTA toward Crontinel
- Do NOT mention specific competitor products by name
- Each post: 400-600 words

## Success
Files created in the landing blog content directory, ready to publish
