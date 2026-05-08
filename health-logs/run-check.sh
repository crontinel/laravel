#!/bin/bash
# Crontinel Health Check — silent on healthy, log always, alert on failure
set -e

LOGFILE="/Users/ray/Work/crontinel/health-logs/health-check.log"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S %Z")

# Check landing (Cloudflare Pages)
LANDING_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://crontinel.com --max-time 10)

# Check app (Railway) — app.crontinel.com returns 302 to /login when healthy
APP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://app.crontinel.com --max-time 10 --location)

# Always log
echo "[$TIMESTAMP] landing:$LANDING_STATUS app:$APP_STATUS" >> "$LOGFILE"

# If healthy, do nothing (silent)
if [[ "$LANDING_STATUS" == "200" ]] && [[ "$APP_STATUS" == "200" || "$APP_STATUS" == "302" ]]; then
    exit 0
fi

# Something is down — send Telegram alert to thread 4
BOT_TOKEN="8342796863:AAEu4hLgslsD-J8J05DyaRpqNqBn2GrqKFY"
MSG="🚨 Crontinel DOWN — landing: $LANDING_STATUS, app: $APP_STATUS | $(date +"%-l:%M %P")"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" \
    -d chat_id="-1003905269197" \
    -d message_thread_id=4 \
    -d text="${MSG}" > /dev/null

# Check Railway if app is down
if [[ "$APP_STATUS" != "200" && "$APP_STATUS" != "302" ]]; then
    source ~/.openclaw/secrets/ct.env
    SERVICE_INFO=$(curl -s -X POST https://backboard.railway.com/graphql/v2 \
        -H "Project-Access-Token: $PROJECT_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"query":"{ project(id: \"47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d\") { services { edges { node { id name status } } } } }"}')
    echo "[$TIMESTAMP] Railway check: $SERVICE_INFO" >> "$LOGFILE"
fi
