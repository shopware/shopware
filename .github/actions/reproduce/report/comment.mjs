#!/usr/bin/env node
// Render the GitHub issue comment from templates/ — no prose lives here, only selection and layout.
// Two shapes: a full verdict comment (legs ran) and the short "incomplete" comment (no verdict
// possible). All wording is in templates/verdicts.json + templates/comment.*.md.
//
//   verdict comment:  VERDICT, UNSURE, FIX, RUN_URL, ISSUE, ART(=artifacts) → comment.md
//   incomplete:       MODE=incomplete, REASON, RUN_URL                      → comment.md
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const templates = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'templates');
const DATA = JSON.parse(fs.readFileSync(path.join(templates, 'verdicts.json'), 'utf8'));
const OUT = process.env.OUT || 'comment.md';

const readJson = (p) => { try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch { return null; } };
const fill = (str, vars) => String(str ?? '').replace(/{{(\w+)}}/g, (_, k) => vars[k] ?? '');

// Read an extra file written by the agent job (agent-summary.md, workspace-edits.txt): from the
// collected artifact dir first, then the working dir (where the incomplete path extracts it).
function readExtra(name) {
  for (const p of [`${process.env.ART || 'artifacts'}/repro-plan/${name}`, name]) {
    try { const t = fs.readFileSync(p, 'utf8').trim(); if (t) return t; } catch { /* next */ }
  }
  return '';
}

// mustache-lite: {{#KEY}}…{{/KEY}} keeps the block iff ctx[KEY] is truthy; {{KEY}} substitutes.
// Single var pass (sections just inline their body), so substituted values — e.g. the agent summary —
// are never re-scanned for placeholders.
function render(tpl, ctx) {
  // Resolve sections first, looping to handle nesting; then a single var pass (a substituted value
  // is inserted last, so it is never re-scanned for placeholders).
  let out = tpl; let prev;
  do { prev = out; out = out.replace(/{{#(\w+)}}\n?([\s\S]*?){{\/\1}}\n?/g, (_, key, inner) => (ctx[key] ? inner : '')); } while (out !== prev);
  return out.replace(/{{(\w+)}}/g, (_, key) => ctx[key] ?? '');
}

const redact = (text) => text
  .replace(/sk-ant-[A-Za-z0-9_-]{8,}/g, '[REDACTED_KEY]')
  .replace(/(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/github_pat_[A-Za-z0-9_]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/AKIA[0-9A-Z]{16}/g, '[REDACTED_AWS_KEY]')
  .replace(/([Bb]earer\s+)[A-Za-z0-9._~+/-]{16,}=*/g, '$1[REDACTED]');

// Guarantee a blank line before every heading (sections may collapse the spacing) and squeeze
// runs of blank lines — so the layout is robust to which optional sections rendered.
const tidy = (md) => `${md.replace(/([^\n])\n(#{2,4} )/g, '$1\n\n$2').replace(/\n{3,}/g, '\n\n').trim()}\n`;

function write(markdown) {
  const redacted = redact(tidy(markdown));
  fs.writeFileSync(OUT, redacted);
  if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, redacted);
  process.stdout.write(redacted);
}

if (process.env.MODE === 'incomplete') {
  const tpl = fs.readFileSync(path.join(templates, 'comment.incomplete.md'), 'utf8');
  const edits = readExtra('workspace-edits.txt');
  write(render(tpl, {
    REASON: process.env.REASON || DATA.incomplete_reason_default,
    RUN_URL: process.env.RUN_URL || '',
    AGENT_SUMMARY: readExtra('agent-summary.md'),
    EDITS: edits,
  }));
} else {
  write(renderVerdict());
}

function renderVerdict() {
  const art = process.env.ART || 'artifacts';
  const plan = readJson(`${art}/repro-plan/reproduction-plan.json`) || {};
  const legA = readJson(`${art}/repro-reported/result.json`);
  const legB = readJson(`${art}/repro-trunk/result.json`);
  const as = legA?.status ?? 'null';
  const bs = legB?.status ?? 'null';
  const rv = (legA || plan).version ?? '?';
  const labels = { AL: `v${rv}`, BL: 'trunk' };

  const verdict = process.env.VERDICT || 'needs_human_review';
  const fix = process.env.FIX || '';
  const unsure = process.env.UNSURE || '';
  const p = DATA.phrases;
  // The only copy with placeholders: the needs_human_review headline/callout vary with the fix.
  const nhrVars = {
    NHR_HEADLINE: fix ? p.nhr_headline_with_fix : p.nhr_headline,
    NHR_CALLOUT: fix ? fill(p.nhr_callout_with_fix, { FIX: fix }) : p.nhr_callout,
  };
  const entry = DATA.verdicts[verdict] || { headline: verdict, badge: verdict, callout: '' };

  // Show the bundle for confident verdicts, and always when a leg actually reproduced (the bundle is
  // demonstrably meaningful then) — only hide it for a blocked/unsure verdict where neither leg did.
  const reproduced = as === 'reproduced' || bs === 'reproduced';
  const omitBundle = (verdict === 'needs_human_review' || verdict === 'blocked') && !reproduced;
  const specLeg = legA || legB;
  const script = omitBundle ? '' : (specLeg?.evidence?.script || '');
  const fixturesPath = `${art}/repro-plan/fixtures.json`;
  const hasFixtures = !omitBundle && fs.existsSync(fixturesPath);
  const agentSummary = readExtra('agent-summary.md');

  const legStatus = (s) => p.status[s] || p.status.null;
  const ctx = {
    HEADLINE: fill(entry.headline, nhrVars),
    VERDICT_BADGE: entry.badge || verdict,
    RV: rv,
    REPORTED_STATUS: legStatus(as),
    TRUNK_STATUS: legStatus(bs),
    SURFACE_EXEC: `${p.surface[plan.layer] || plan.layer || 'unknown'} · ${p.executor[plan.executor] || plan.executor || 'unknown'}`,
    DATE: process.env.DATE || new Date().toISOString().slice(0, 10),
    FIX: fix,
    UNSURE: unsure,
    CALLOUT: fill(entry.callout, nhrVars),
    EDITS: readExtra('workspace-edits.txt'),
    RESULT: resultSection({ legA, legB, as, bs, labels, explanation: agentExplanation(plan), evidence: readJson('evidence.json') }),
    SCENARIO: scenarioBlock(plan),
    AGENT_SUMMARY: agentSummary,
    DETAILS_HEADING: (scenarioBlock(plan) || agentSummary || script || hasFixtures) ? '### Reproduction details' : '',
    TESTCASE: script,
    TESTCASE_LANG: specLeg?.evidence?.script_lang || 'sh',
    TESTCASE_TOOL: p.testcase_tool[plan.executor] || specLeg?.evidence?.script_lang || 'sh',
    ASSERTIONS: script ? assertionList(specLeg?.assertion?.checks) : '', // http: the expectations, beside the curl
    FIXTURES: hasFixtures ? fs.readFileSync(fixturesPath, 'utf8').trim() : '',
    RUN_URL: process.env.RUN_URL || '',
  };
  return render(fs.readFileSync(path.join(templates, 'comment.verdict.md'), 'utf8'), ctx);
}

function scenarioBlock(plan) {
  return Array.isArray(plan.scenario) && plan.scenario.length
    ? plan.scenario.map((s) => `- ${s.replace(/^(Given|When|Then|And|But) /, '**$1** ')}`).join('\n')
    : '';
}

function agentExplanation(plan) {
  const text = plan.agent_explanation || plan.confidence_reason;
  if (!text || text === 'null') return '';
  return String(text).replace(/\s+/g, ' ').trim();
}

// One collapsible per leg — its status in the summary line, and everything for that leg inside it
// (checks + gloss, screenshot, recording). Combined into a single "Both versions" spoiler when the
// legs share an outcome, split otherwise. Evidence URLs come from the manifest embed-evidence.sh wrote.
function resultSection({ legA, legB, as, bs, labels, explanation, evidence }) {
  const evFor = (name) => (evidence?.legs || []).find((l) => l.name === name) || {};
  const out = [];
  if (explanation) out.push(`> ${explanation}`);
  // Bold the leg label with <strong>, not markdown — GitHub doesn't render **…** inside <summary>.
  if (legA && legB && as === bs) {
    out.push(legSpoiler(`<strong>Both versions</strong> — ${statusBadge(as)}`, legB || legA, evFor('trunk').png ? evFor('trunk') : evFor('reported')));
  } else {
    if (legA) out.push(legSpoiler(`<strong>${labels.AL}</strong> — ${statusBadge(as)}`, legA, evFor('reported')));
    if (legB) out.push(legSpoiler(`<strong>${labels.BL}</strong> — ${statusBadge(bs)}`, legB, evFor('trunk')));
  }
  return out.join('\n\n');
}

function legSpoiler(summary, leg, ev) {
  // Result shows only what happened: the FAILING check(s) for a structured (http) leg, or the raw
  // reporter for a playwright/direct leg — then the `→` gloss/reason. The full assertion list lives
  // in the Reproduction test spoiler. Recording link goes above the screenshot (tall image).
  const reason = leg.blocked_reason && leg.blocked_reason !== 'null' ? leg.blocked_reason : '';
  const body = [resultChecks(leg), reason ? `→ ${reason}` : (DATA.phrases.gloss[leg.status] || '')];
  if (ev.webm) body.push(`▶ [Watch the recording](${ev.webm})`);
  if (ev.png) body.push(`![screenshot](${ev.png})`);
  return spoiler(summary, body.filter(Boolean).join('\n\n'));
}

function statusBadge(s) { return DATA.phrases.status[s] || DATA.phrases.status.null; }
function spoiler(summary, body) { return `<details><summary>${summary}</summary>\n\n${body}\n\n</details>`; }

function qval(v) { return /^\d+$/.test(v) ? v : `'${v}'`; }
function clean(s) { return String(s).replace(/\s+/g, ' ').replace(/\*\//g, '* /').trim(); }

// require*/assert* call for a check (require* = precondition, assert* = symptom).
function callOf(c) {
  const ops = { present: 'Present', absent: 'Absent', contains: 'Contains', matches: 'Matches', gt: 'GreaterThan', lt: 'LessThan', equals: 'Equals' };
  const verb = c.role === 'precondition' ? 'require' : 'assert';
  const name = ops[c.op] || 'Equals';
  return ['present', 'absent'].includes(c.op) ? `${verb}${name}(${c.subject})` : `${verb}${name}(${c.subject}, ${qval(String(c.expected))})`;
}

// What Result shows for a leg: only the failing checks (http), or the raw reporter (playwright/direct).
function resultChecks(leg) {
  const checks = leg.assertion?.checks;
  if (Array.isArray(checks) && checks.length) {
    const failed = checks.filter((c) => c.ok === false); // ok:null (not-run) checks are excluded
    if (!failed.length) return '';
    const lines = ['```js'];
    for (const c of failed) {
      if (c.label && c.label !== 'null') lines.push(`// failed: "${clean(c.label)}"`);
      const actual = String(c.actual);
      if (actual.includes('\n')) lines.push(`${callOf(c)} // ❌`, `/* got:\n${blockSafe(actual)}\n*/`);
      else lines.push(`${callOf(c)} // ❌ got ${qval(actual)}`);
    }
    lines.push('```');
    return lines.join('\n');
  }
  // No structured checks (playwright/direct): show the reporter, except on a plain healthy pass.
  const reporter = leg.evidence?.reporter_output;
  return (leg.status !== 'not_reproduced' && reporter && reporter !== 'null') ? `\`\`\`\n${reporter}\n\`\`\`` : '';
}

// The full expectation list for the Reproduction test spoiler (definitions, no pass/fail).
function assertionList(checks) {
  if (!Array.isArray(checks) || !checks.length) return '';
  const lines = ['```js'];
  for (const c of checks) lines.push(c.label && c.label !== 'null' ? `${callOf(c)} // ${clean(c.label)}` : callOf(c));
  lines.push('```');
  return lines.join('\n');
}

// Keep a multi-line value intact for a block comment: cap the length and neutralize any `*/`.
function blockSafe(s) {
  const capped = s.length > 1200 ? `${s.slice(0, 1200)}\n… (truncated)` : s;
  return capped.replace(/\*\//g, '* /');
}
