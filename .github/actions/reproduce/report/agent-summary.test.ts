import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT = path.join(path.dirname(fileURLToPath(import.meta.url)), 'agent-summary.ts');

// The truncation marker the source appends verbatim (mind the … and — glyphs).
const SUFFIX = '\n\n… (truncated — see the agent run for the full log)';
const MAX = 6000;

const tmp = () => fs.mkdtempSync(path.join(os.tmpdir(), 'repro-agent-summary-'));

// Run the script with a clean env so the caller's own AGENT_* vars can't leak in.
function run({ cwd = tmp(), args = [], env = {} }: { cwd?: string; args?: string[]; env?: NodeJS.ProcessEnv } = {}) {
  const clean = { ...process.env };
  delete clean.AGENT_SUMMARY_FILE;
  delete clean.AGENT_LOG;
  const r = spawnSync('node', [SCRIPT, ...args], { cwd, encoding: 'utf8', env: { ...clean, ...env } });
  return { ...r, cwd };
}

// Serialise objects as the gh-aw stream-json log: one JSON value per line.
const logLines = (...objs: unknown[]) => objs.map((o) => (typeof o === 'string' ? o : JSON.stringify(o))).join('\n');
const writeLog = (cwd: string, content: string) => {
  const p = path.join(cwd, 'agent.log');
  fs.writeFileSync(p, content);
  return p;
};
const result = (text: string) => ({ type: 'result', result: text });
const assistant = (...blocks: unknown[]) => ({ type: 'assistant', message: { content: blocks } });
const textBlock = (text: string) => ({ type: 'text', text });

// --- PRIMARY: agent-authored summary --------------------------------------

test('an authored agent-summary.md is used verbatim (trimmed)', () => {
  const cwd = tmp();
  fs.writeFileSync(path.join(cwd, 'agent-summary.md'), '\n  Reproduced on 6.7.0.0.  \n');
  const r = run({ cwd });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'Reproduced on 6.7.0.0.');
});

test('AGENT_SUMMARY_FILE overrides the default path and wins over a cwd file', () => {
  const cwd = tmp();
  fs.writeFileSync(path.join(cwd, 'agent-summary.md'), 'the default file');
  const chosen = path.join(cwd, 'custom.md');
  fs.writeFileSync(chosen, 'the chosen file');
  const r = run({ cwd, env: { AGENT_SUMMARY_FILE: chosen } });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'the chosen file');
});

test('a whitespace-only authored summary falls through to the log scrape', () => {
  const cwd = tmp();
  fs.writeFileSync(path.join(cwd, 'agent-summary.md'), '   \n\t  \n');
  const log = writeLog(cwd, logLines(result('from the log')));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'from the log');
});

test('a long authored summary is truncated with the marker', () => {
  const cwd = tmp();
  const body = 'x'.repeat(MAX + 500);
  fs.writeFileSync(path.join(cwd, 'agent-summary.md'), body);
  const r = run({ cwd });
  assert.equal(r.status, 0);
  assert.equal(r.stdout.length, MAX + SUFFIX.length);
  assert.ok(r.stdout.startsWith('x'.repeat(MAX)));
  assert.ok(r.stdout.endsWith(SUFFIX));
});

test('an authored summary at exactly MAX chars is not truncated', () => {
  const cwd = tmp();
  const body = 'y'.repeat(MAX);
  fs.writeFileSync(path.join(cwd, 'agent-summary.md'), body);
  const r = run({ cwd });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, body);
  assert.ok(!r.stdout.includes('truncated'));
});

// --- FALLBACK: scraping the gh-aw stream-json log -------------------------

test('a missing log yields empty output and exit 0', () => {
  const cwd = tmp();
  const r = run({ cwd, args: [path.join(cwd, 'does-not-exist.log')] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, '');
});

test('AGENT_LOG is used when no argv path is given', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(result('via AGENT_LOG')));
  const r = run({ cwd, env: { AGENT_LOG: log } });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'via AGENT_LOG');
});

test('an argv log path takes precedence over AGENT_LOG', () => {
  const cwd = tmp();
  const argvLog = writeLog(cwd, logLines(result('from argv')));
  const envLog = path.join(cwd, 'env.log');
  fs.writeFileSync(envLog, logLines(result('from env')));
  const r = run({ cwd, args: [argvLog], env: { AGENT_LOG: envLog } });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'from argv');
});

test('the final result text is preferred over assistant messages', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    assistant(textBlock('an intermediate assistant note')),
    result('the authoritative result'),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'the authoritative result');
});

test('falls back to the last assistant text when there is no result', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    assistant(textBlock('first message')),
    assistant(textBlock('second, latest message')),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'second, latest message');
});

test('assistant content joins only text blocks, with newlines', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    assistant(
      { type: 'tool_use', name: 'bash', input: { cmd: 'ls' } },
      textBlock('line one'),
      { type: 'tool_result', content: 'ignored' },
      textBlock('line two'),
    ),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'line one\nline two');
});

test('non-JSON and malformed lines are skipped', () => {
  const cwd = tmp();
  const log = writeLog(cwd, [
    'this is plain narration, not JSON',
    '{ not valid json',
    '',
    '   ',
    JSON.stringify(result('the only real payload')),
    'trailing noise',
  ].join('\n'));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'the only real payload');
});

test('the last result line wins when several are present', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    result('early result'),
    result('final result'),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'final result');
});

test('scraped output is trimmed of surrounding whitespace', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(result('\n\n  padded verdict  \n')));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'padded verdict');
});

test('a log with only empty text yields empty output and exit 0', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    result(''),
    assistant(textBlock('   ')),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, '');
});

test('a long scraped result is truncated with the marker', () => {
  const cwd = tmp();
  const body = 'z'.repeat(MAX + 42);
  const log = writeLog(cwd, logLines(result(body)));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout.length, MAX + SUFFIX.length);
  assert.ok(r.stdout.startsWith('z'.repeat(MAX)));
  assert.ok(r.stdout.endsWith(SUFFIX));
});

test('an assistant message with no content array is tolerated', () => {
  const cwd = tmp();
  const log = writeLog(cwd, logLines(
    { type: 'assistant', message: {} },
    { type: 'assistant' },
    result('survived the empty assistant frames'),
  ));
  const r = run({ cwd, args: [log] });
  assert.equal(r.status, 0);
  assert.equal(r.stdout, 'survived the empty assistant frames');
});
