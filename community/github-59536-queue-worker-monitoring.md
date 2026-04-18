# Community Comment — GitHub Discussion #59536

**Thread:** Laravel Queue Worker not processing jobs after deployment
**URL:** https://github.com/laravel/framework/discussions/59536
**Platform:** GitHub Discussions (laravel/framework)
**Topic:** Queue worker monitoring in production

---

## Comment Draft

> Beyond Supervisor/systemd keeping the process alive — a separate concern is actually *knowing* when your queue is unhealthy. Workers can stay up but silently stop processing jobs (e.g., Redis connection drops, a job throws a fatal error and kills the worker loop, a deployment leaves stale processes).

A few things worth adding to your production stack:

**1. Monitor job throughput, not just process uptime**

`queue:work` being alive doesn't mean jobs are completing. Set up a lightweight health check — push a ping job every minute and alert if it hasn't been consumed within N minutes. This catches the "worker is running but dead inside" scenario.

**2. Watch for the failed_jobs pile-up**

After Supervisor restarts a crashed worker, the jobs it was mid-processing are typically released back to the queue (if you're using the `redis` driver with `--release` logic). But jobs that hit `max_tries` and fail silently? They go to `failed_jobs`. If you're not monitoring that table, you won't know a silent failure rate started.

**3. Horizon makes this observable**

If you're on Redis, Laravel Horizon gives you a real-time dashboard and lets you define alert rules. It's overkill for some setups but for anything at scale it's worth the lift.

**4. External monitoring for worker-alive checks**

For a lightweight option, you can use a cron-triggered monitoring service (like crontinel.com or similar) to ping an endpoint whenever your queue workers are supposed to be active. If the ping stops, you get an alert. This complements Supervisor — Supervisor keeps the process alive, monitoring tells you *when to look*.

The Supervisor answer covers "how do I keep it running." The missing half is "how do I know it's actually working."

---

## Notes for poster
- Genuinely helpful, not promotional — mentions the category of tools rather than hard-selling one
- Thread is active (Apr 5, 2026) — relevant and timely
- No link dropped without context; crontinel.com mentioned only as an example of the category, not pushed
