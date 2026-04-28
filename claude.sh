#!/usr/bin/env bash
# claude.sh - Launch Claude Code + Telegram in a tmux session for Crontinel
#
# Usage: ./claude.sh [dp] [da]
#   dp  - disable bypass permissions (default: --dangerously-skip-permissions is ON)
#   da  - disable auto mode (default: --enable-auto-mode is ON)
#
# Requirements: claude, tmux
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SESSION_NAME="crontinel"
AUTO_MODE=true
BYPASS_PERMS=true

# Use the real user home, not any project-local HOME that may have been inherited
# (e.g. from an amazingplugins session that sets HOME=.../amazingplugins/.claude-home).
# Wrong HOME → wrong plugin cache → Telegram plugin never spawns.
REAL_HOME="$(eval echo ~"$USER")"

# Project-local Telegram state (isolates this bot from other projects)
export TELEGRAM_STATE_DIR="$SCRIPT_DIR/.claude/channels/telegram"
mkdir -p "$TELEGRAM_STATE_DIR"

# Check dependencies
for cmd in claude tmux; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "Error: '$cmd' is not installed or not in PATH" >&2
        exit 1
    fi
done

# Parse positional flags (order-independent)
for arg in "$@"; do
    case "$arg" in
        dp) BYPASS_PERMS=false ;;
        da) AUTO_MODE=false ;;
    esac
done

# Build claude arguments
CLAUDE_ARGS=(-c --rc --channels plugin:telegram@claude-plugins-official)

if [ "$BYPASS_PERMS" = true ]; then
    CLAUDE_ARGS+=(--dangerously-skip-permissions)
fi

if [ "$AUTO_MODE" = true ]; then
    CLAUDE_ARGS+=(--enable-auto-mode)
fi

# Pin HOME to real home and clear any inherited TELEGRAM_BOT_TOKEN so the
# plugin's server.ts reads the crontinel-specific token from .claude/channels/telegram/.env
CLAUDE_CMD="HOME='$REAL_HOME' TELEGRAM_STATE_DIR='$TELEGRAM_STATE_DIR' env -u TELEGRAM_BOT_TOKEN claude ${CLAUDE_ARGS[*]}"

echo "▶ claude.sh (crontinel)"
echo "  tmux session       : $SESSION_NAME"
echo "  bypass-permissions : $BYPASS_PERMS"
echo "  auto-mode          : $AUTO_MODE"
echo "  telegram state     : $TELEGRAM_STATE_DIR"

# ── Step 1: Check for updates ─────────────────────────────────────────────────
echo ""
echo "Checking for updates..."
UPDATE_OUTPUT=$(claude update 2>&1)
echo "  $UPDATE_OUTPUT"

if echo "$UPDATE_OUTPUT" | grep -qi "up to date"; then
    UPDATE_APPLIED=false
else
    UPDATE_APPLIED=true
    echo "  Update applied — restarting claude process..."
fi

# ── Step 2: Handle update → kill entire tmux session and recreate ─────────────
if [ "$UPDATE_APPLIED" = true ] && tmux has-session -t "$SESSION_NAME" 2>/dev/null; then
    echo "  Killing tmux session '$SESSION_NAME'..."
    tmux kill-session -t "$SESSION_NAME"
    sleep 1

    echo "  Recreating session with updated claude..."
    echo "  command            : $CLAUDE_CMD"
    tmux new-session -d -s "$SESSION_NAME" -c "$SCRIPT_DIR"
    tmux send-keys -t "$SESSION_NAME" "$CLAUDE_CMD" Enter
    exec tmux attach-session -t "$SESSION_NAME"
fi

# ── Step 3: No update — attach if session exists (claude already running) ─────
if tmux has-session -t "$SESSION_NAME" 2>/dev/null; then
    echo "  session exists     : true (attaching)"
    exec tmux attach-session -t "$SESSION_NAME"
fi

# ── Step 4: No session — create it and launch claude ─────────────────────────
echo "  session exists     : false (creating)"
echo "  command            : $CLAUDE_CMD"
echo ""

tmux new-session -d -s "$SESSION_NAME" -c "$SCRIPT_DIR"
tmux send-keys -t "$SESSION_NAME" "$CLAUDE_CMD" Enter
exec tmux attach-session -t "$SESSION_NAME"
