#!/usr/bin/env node
/**
 * Simple PostHog MCP server for error tracking queries.
 * Wraps PostHog REST API as an MCP stdio server.
 */

import { readFileSync } from 'fs';
import { resolve } from 'path';

// Load from project secrets (never commit this value)
const SECRETS_FILE = '/Users/ray/.openclaw/secrets/ct.env';
function loadSecret(key) {
  try {
    const lines = readFileSync(SECRETS_FILE, 'utf8').split('\n');
    for (const line of lines) {
      const [k, v] = line.split('=');
      if (k === key) return v?.trim();
    }
  } catch {}
  return null;
}

// phc_ key for sending events (ingestion)
const POSTHOG_API_KEY = loadSecret('POSTHOG_API_KEY') || 'phc_pevB3bXAJEhgawyAp6fQjxxT5PVJpjYXTUHgoEAZ8kfP';
// phx_ personal key for reading data
const POSTHOG_PERSONAL_KEY = loadSecret('POSTHOG_PERSONAL_API_KEY') || '';
const POSTHOG_HOST = 'https://us.i.posthog.com';
const PROJECT_ID = '401221';

async function posthogRead(path) {
  const url = new URL(path, POSTHOG_HOST);
  const key = POSTHOG_PERSONAL_KEY || POSTHOG_API_KEY;
  const response = await fetch(url, {
    headers: { 'Authorization': `Bearer ${key}` },
  });
  return response.json();
}

async function posthogWrite(path, body) {
  const url = new URL(path, POSTHOG_HOST);
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${POSTHOG_API_KEY}`,
    },
    body: JSON.stringify(body),
  });
  return response.json();
}

const tools = {
  list_errors: {
    description: 'List recent errors from PostHog Error Tracking',
    inputSchema: {
      type: 'object',
      properties: {
        limit: { type: 'number', description: 'Max errors to return (default 20)', default: 20 },
        period: { type: 'string', description: 'Time period: 1h, 24h, 7d, 30d (default 7d)' },
      },
    },
  },
  get_error_detail: {
    description: 'Get details of a specific error by exception UUID',
    inputSchema: {
      type: 'object',
      properties: {
        exception_uuid: { type: 'string', description: 'The exception UUID from PostHog' },
      },
      required: ['exception_uuid'],
    },
  },
};

let requestId = 0;

process.stdin.on('data', (chunk) => {
  const lines = chunk.toString().split('\n').filter(l => l.trim());
  for (const line of lines) {
    try {
      const msg = JSON.parse(line);
      if (msg.method === 'initialize') {
        process.stdout.write(JSON.stringify({
          jsonrpc: '2.0',
          id: msg.id,
          result: {
            protocolVersion: '2024-11-05',
            capabilities: { tools: {} },
            serverInfo: { name: 'posthog-errors', version: '1.0.0' },
          },
        }) + '\n');
      } else if (msg.method === 'tools/list') {
        const toolDefs = Object.entries(tools).map(([name, t]) => ({
          name,
          description: t.description,
          inputSchema: t.inputSchema,
        }));
        process.stdout.write(JSON.stringify({
          jsonrpc: '2.0',
          id: msg.id,
          result: { tools: toolDefs },
        }) + '\n');
      } else if (msg.method === 'tools/call') {
        const { name, arguments: args = {} } = msg.params;
        handleTool(name, args, msg.id);
      }
    } catch (e) {
      // ignore parse errors
    }
  }
});

async function handleTool(name, args, id) {
  try {
    let result;
    if (name === 'list_errors') {
      const limit = args.limit || 20;
      const period = args.period || '7d';
      // Query $exception events from PostHog events API
      const params = new URLSearchParams({
        event: '$exception',
        limit: String(limit),
        orderBy: '["-timestamp"]',
      });
      const data = await posthogRead(`/api/projects/${PROJECT_ID}/events/?${params}`);
      const results = data.results || [];
      const errors = results.map(e => ({
        event_id: e.id,
        distinct_id: e.distinct_id,
        type: e.properties?.$exception_type || e.properties?.$exception_types?.[0],
        message: e.properties?.$exception_message || e.properties?.$exception_values?.[0],
        handled: e.properties?.$exception_handled,
        fingerprint: e.properties?.$exception_fingerprint,
        issue_id: e.properties?.$exception_issue_id,
        timestamp: e.timestamp,
        lib: e.properties?.$lib,
        lib_version: e.properties?.$lib_version,
      }));
      result = {
        content: [{
          type: 'text',
          text: JSON.stringify({ errors, total: errors.length, period }, null, 2),
        }],
      };
    } else if (name === 'get_error_detail') {
      const data = await posthogRead(`/api/projects/${PROJECT_ID}/errors/${args.exception_uuid}/`);
      result = {
        content: [{
          type: 'text',
          text: JSON.stringify(data, null, 2),
        }],
      };
    } else {
      result = { content: [{ type: 'text', text: `Unknown tool: ${name}` }], isError: true };
    }
    process.stdout.write(JSON.stringify({
      jsonrpc: '2.0',
      id,
      result,
    }) + '\n');
  } catch (e) {
    process.stdout.write(JSON.stringify({
      jsonrpc: '2.0',
      id,
      error: { message: e.message },
    }) + '\n');
  }
}
