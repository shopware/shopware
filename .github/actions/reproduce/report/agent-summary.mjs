#!/usr/bin/env node
// Extract the agent's final natural-language message from the stream-json debug log, for the
// "Agent summary" spoiler in the issue comment — it reads well and explains what the agent did.
// Prefers the CLI's final result text; falls back to the last assistant text block. Capped so it
// fits comfortably in a GitHub comment.
import fs from 'node:fs';

const logPath = process.argv[2] || process.env.AGENT_LOG || '/tmp/gh-aw/agent-stdio.log';
const MAX = 6000;
if (!fs.existsSync(logPath)) process.exit(0);

let finalResult = '';
let lastAssistant = '';
for (const line of fs.readFileSync(logPath, 'utf8').split('\n')) {
  const s = line.trim();
  if (!s.startsWith('{')) continue;
  let msg;
  try { msg = JSON.parse(s); } catch { continue; }
  if (msg.type === 'result' && typeof msg.result === 'string') finalResult = msg.result;
  if (msg.type === 'assistant') {
    const text = (msg.message?.content || []).filter((c) => c.type === 'text').map((c) => c.text).join('\n').trim();
    if (text) lastAssistant = text;
  }
}

let out = (finalResult || lastAssistant).trim();
if (!out) process.exit(0);
if (out.length > MAX) out = `${out.slice(0, MAX)}\n\n… (truncated — see the agent run for the full log)`;
process.stdout.write(out);
