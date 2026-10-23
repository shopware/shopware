#!/usr/bin/env node
// Produce the "Agent summary" spoiler content for the issue comment.
//
// PRIMARY: the agent writes a short agent-summary.md alongside its bundle (see prompt/task.md); if
// present, that authored recap is used verbatim (capped). This is a declared artifact the agent owns,
// so it survives gh-aw changes.
// FALLBACK: when no agent-summary.md exists, scrape the agent's final natural-language message from
// gh-aw's stream-json debug log — preferring the CLI's final result text, then the last assistant
// text block. If the scrape yields nothing, emit nothing (as before). This path is best-effort.
import fs from 'node:fs';

interface StreamContentBlock {
  type?: string;
  text?: string;
}
interface StreamMessage {
  type?: string;
  result?: unknown;
  message?: { content?: StreamContentBlock[] };
}

const MAX = 6000;
const cap = (text: string): string => (text.length > MAX
  ? `${text.slice(0, MAX)}\n\n… (truncated — see the agent run for the full log)`
  : text);

// PRIMARY: agent-authored summary. It lives at the workspace root next to the bundle, so no path
// argument is needed; AGENT_SUMMARY_FILE overrides the location when set.
const authoredPath = process.env.AGENT_SUMMARY_FILE || 'agent-summary.md';
try {
  const authored = fs.readFileSync(authoredPath, 'utf8').trim();
  if (authored) {
    process.stdout.write(cap(authored));
    process.exit(0);
  }
} catch {
  // No authored summary — fall back to scraping the gh-aw log below.
}

const logPath = process.argv[2] || process.env.AGENT_LOG || '/tmp/gh-aw/agent-stdio.log';
if (!fs.existsSync(logPath)) {
  process.exit(0);
}

let finalResult = '';
let lastAssistant = '';
for (const line of fs.readFileSync(logPath, 'utf8').split('\n')) {
  const s = line.trim();
  if (!s.startsWith('{')) {
    continue;
  }
  let msg: StreamMessage;
  try {
    msg = JSON.parse(s);
  } catch {
    continue;
  }
  if (msg.type === 'result' && typeof msg.result === 'string') {
    finalResult = msg.result;
  }
  if (msg.type === 'assistant') {
    const text = (msg.message?.content || []).filter((c) => c.type === 'text').map((c) => c.text).join('\n').trim();
    if (text) {
      lastAssistant = text;
    }
  }
}

const out = (finalResult || lastAssistant).trim();
if (!out) {
  process.exit(0);
}
process.stdout.write(cap(out));
