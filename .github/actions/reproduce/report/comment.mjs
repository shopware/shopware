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

/**
 * Reads optional JSON report artifacts, returning null for absent leg outputs.
 */
const readJson = (p) => {
  try {
    return JSON.parse(fs.readFileSync(p, 'utf8'));
  } catch {
    return null;
  }
};
/**
 * Replaces simple `{{KEY}}` placeholders in phrase templates.
 */
const fill = (str, vars) => String(str ?? '').replace(/{{(\w+)}}/g, (_, k) => vars[k] ?? '');

/**
 * Reads an auxiliary agent artifact for inclusion in the issue comment.
 *
 * The renderer checks the collected artifact directory first and then the working directory, matching
 * the two workflow paths used by complete and incomplete reproduction reports.
 */
function readExtra(name) {
  for (const p of [`${process.env.ART || 'artifacts'}/repro-plan/${name}`, name]) {
    try {
      const text = fs.readFileSync(p, 'utf8').trim();
      if (text) {
        return text;
      }
    } catch {
      // Try the next candidate location.
    }
  }
  return '';
}

/**
 * Renders the tiny template language used by reproduction issue comments.
 *
 * Sections are resolved before a single variable pass so substituted values from agent output are
 * never scanned again for placeholders or template control syntax.
 *
 * @example
 * const markdown = render(tpl, { VERDICT_BADGE: 'live_bug', RUN_URL: runUrl });
 * fs.writeFileSync(OUT, redact(tidy(markdown)));
 */
function render(tpl, ctx) {
  // Resolve sections first, looping to handle nesting; then a single var pass (a substituted value
  // is inserted last, so it is never re-scanned for placeholders).
  let out = tpl;
  let prev;
  const sectionPattern = /{{#(\w+)}}\n?([\s\S]*?){{\/\1}}\n?/g;

  do {
    prev = out;
    out = out.replace(sectionPattern, (_, key, inner) => (ctx[key] ? inner : ''));
  } while (out !== prev);

  return out.replace(/{{(\w+)}}/g, (_, key) => ctx[key] ?? '');
}

/**
 * Redacts common secret token formats before markdown leaves the workflow.
 */
const redact = (text) => text
  .replace(/sk-ant-[A-Za-z0-9_-]{8,}/g, '[REDACTED_KEY]')
  .replace(/(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/github_pat_[A-Za-z0-9_]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/AKIA[0-9A-Z]{16}/g, '[REDACTED_AWS_KEY]')
  .replace(/([Bb]earer\s+)[A-Za-z0-9._~+/-]{16,}=*/g, '$1[REDACTED]');

/**
 * Normalizes markdown spacing after optional template sections collapse.
 *
 * This keeps headings readable and avoids excessive blank lines regardless of which verdict,
 * evidence, or agent-summary sections are present.
 */
const tidy = (md) => `${md.replace(/([^\n])\n(#{2,4} )/g, '$1\n\n$2').replace(/\n{3,}/g, '\n\n').trim()}\n`;

/**
 * Writes the final comment markdown to disk, step summary, and stdout.
 *
 * Redaction happens at this boundary so rendered agent text and reproduced scripts cannot leak common
 * token formats into issue comments.
 */
function write(markdown) {
  const redacted = redact(tidy(markdown));
  fs.writeFileSync(OUT, redacted);
  if (process.env.GITHUB_STEP_SUMMARY) {
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, redacted);
  }
  process.stdout.write(redacted);
}

if (process.env.MODE === 'incomplete') {
  const tpl = fs.readFileSync(path.join(templates, 'comment.incomplete.md'), 'utf8');
  const edits = readExtra('workspace-edits.txt');
  // Prefer the agent's own give-up reason (written by `repro giveup` into giveup.txt, carried in the
  // repro-plan artifact). Fall back to the REASON env / generic default only when it's absent.
  const giveup = readExtra('giveup.txt');
  write(render(tpl, {
    REASON: giveup || process.env.REASON || DATA.incomplete_reason_default,
    RUN_URL: process.env.RUN_URL || '',
    AGENT_SUMMARY: readExtra('agent-summary.md'),
    EDITS: edits,
  }));
} else {
  write(renderVerdict());
}

/**
 * Builds the full two-leg verdict comment context and renders it through the checked-in template.
 *
 * The renderer decides whether to expose the generated bundle based on verdict confidence and
 * whether a leg reproduced, keeping unsure/blocked comments short unless evidence is useful.
 */
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
    SURFACE_EXEC: [
      p.surface[plan.layer] || plan.layer || 'unknown',
      p.executor[plan.executor] || plan.executor || 'unknown',
    ].join(' · '),
    DATE: process.env.DATE || new Date().toISOString().slice(0, 10),
    FIX: fix,
    UNSURE: unsure,
    CALLOUT: fill(entry.callout, nhrVars),
    EDITS: readExtra('workspace-edits.txt'),
    RESULT: resultSection({
      legA,
      legB,
      as,
      bs,
      labels,
      explanation: agentExplanation(plan),
      evidence: readJson('evidence.json'),
    }),
    SCENARIO: scenarioBlock(plan),
    AGENT_SUMMARY: agentSummary,
    DETAILS_HEADING: scenarioBlock(plan) || agentSummary || script || hasFixtures
      ? '### Reproduction details'
      : '',
    TESTCASE: script,
    TESTCASE_LANG: specLeg?.evidence?.script_lang || 'sh',
    TESTCASE_TOOL: p.testcase_tool[plan.executor] || specLeg?.evidence?.script_lang || 'sh',
    ASSERTIONS: script ? assertionList(specLeg?.assertion?.checks) : '', // http: the expectations, beside the curl
    FIXTURES: hasFixtures ? fs.readFileSync(fixturesPath, 'utf8').trim() : '',
    RUN_URL: process.env.RUN_URL || '',
  };
  return render(fs.readFileSync(path.join(templates, 'comment.verdict.md'), 'utf8'), ctx);
}

/**
 * Formats the generated Given/When/Then scenario for the report details.
 */
function scenarioBlock(plan) {
  return Array.isArray(plan.scenario) && plan.scenario.length
    ? plan.scenario.map((s) => `- ${s.replace(/^(Given|When|Then|And|But) /, '**$1** ')}`).join('\n')
    : '';
}

/**
 * Extracts a compact agent explanation for the report header.
 *
 * The plan can carry either the final agent explanation or a confidence reason; both are normalized
 * to one line so they fit inside the result section quote.
 */
function agentExplanation(plan) {
  const text = plan.agent_explanation || plan.confidence_reason;
  if (!text || text === 'null') {
    return '';
  }
  return String(text).replace(/\s+/g, ' ').trim();
}

/**
 * Builds the per-leg result section shown in the issue comment.
 *
 * Matching leg outcomes are collapsed into a single "Both versions" spoiler, while divergent
 * outcomes stay separate so screenshots and recordings line up with the leg that produced them.
 *
 * @example
 * const markdown = resultSection({ legA, legB, as, bs, labels, explanation, evidence });
 * if (markdown) {
 *   sections.push(markdown);
 * }
 */
function resultSection({ legA, legB, as, bs, labels, explanation, evidence }) {
  const evFor = (name) => (evidence?.legs || []).find((l) => l.name === name) || {};
  const out = [];
  if (explanation) {
    out.push(`> ${explanation}`);
  }
  // Bold the leg label with <strong>, not markdown — GitHub doesn't render **…** inside <summary>.
  if (legA && legB && as === bs) {
    const evidenceForSharedStatus = evFor('trunk').png ? evFor('trunk') : evFor('reported');
    out.push(legSpoiler(
      `<strong>Both versions</strong> — ${statusBadge(as)}`,
      legB || legA,
      evidenceForSharedStatus,
    ));
  } else {
    if (legA) {
      out.push(legSpoiler(`<strong>${labels.AL}</strong> — ${statusBadge(as)}`, legA, evFor('reported')));
    }
    if (legB) {
      out.push(legSpoiler(`<strong>${labels.BL}</strong> — ${statusBadge(bs)}`, legB, evFor('trunk')));
    }
  }
  return out.join('\n\n');
}

/**
 * Renders one collapsible leg section with checks, reason text, and visual evidence.
 *
 * Structured HTTP failures show only failed checks, while Playwright and direct legs show their raw
 * reporter output plus any screenshot or video URLs from the evidence manifest.
 */
function legSpoiler(summary, leg, ev) {
  // Result shows only what happened: the FAILING check(s) for a structured (http) leg, or the raw
  // reporter for a playwright/direct leg — then the `→` gloss/reason. The full assertion list lives
  // in the Reproduction test spoiler. Recording link goes above the screenshot (tall image).
  const reason = leg.blocked_reason && leg.blocked_reason !== 'null' ? leg.blocked_reason : '';
  const body = [resultChecks(leg), reason ? `→ ${reason}` : (DATA.phrases.gloss[leg.status] || '')];
  if (ev.webm) {
    body.push(`▶ [Watch the recording](${ev.webm})`);
  }
  if (ev.png) {
    body.push(`![screenshot](${ev.png})`);
  }
  return spoiler(summary, body.filter(Boolean).join('\n\n'));
}

/**
 * Maps a machine leg status to the human-facing badge phrase.
 */
function statusBadge(s) {
  return DATA.phrases.status[s] || DATA.phrases.status.null;
}

/**
 * Wraps markdown content in a GitHub-compatible details block.
 */
function spoiler(summary, body) {
  return `<details><summary>${summary}</summary>\n\n${body}\n\n</details>`;
}

/**
 * Formats expected or actual values for pseudo assertion output.
 */
function qval(v) {
  return /^\d+$/.test(v) ? v : `'${v}'`;
}

/**
 * Normalizes inline labels before embedding them in generated pseudo code.
 */
function clean(s) {
  return String(s).replace(/\s+/g, ' ').replace(/\*\//g, '* /').trim();
}

/**
 * Formats one structured HTTP check as reviewer-facing pseudo test code.
 *
 * Preconditions become `require*` calls and symptom checks become `assert*` calls, mirroring how the
 * HTTP executor classifies setup failures versus reproduced symptoms.
 *
 * @example
 * const call = callOf({ role: 'assert', op: 'equals', subject: 'status', expected: '200' });
 * lines.push(`${call} // failed`);
 */
function callOf(c) {
  const ops = {
    present: 'Present',
    absent: 'Absent',
    contains: 'Contains',
    matches: 'Matches',
    gt: 'GreaterThan',
    lt: 'LessThan',
    equals: 'Equals',
  };
  const verb = c.role === 'precondition' ? 'require' : 'assert';
  const name = ops[c.op] || 'Equals';
  if (['present', 'absent'].includes(c.op)) {
    return `${verb}${name}(${c.subject})`;
  }
  return `${verb}${name}(${c.subject}, ${qval(String(c.expected))})`;
}

/**
 * Builds the concise result body shown for a single leg.
 *
 * HTTP legs expose only failed structured checks; non-HTTP legs fall back to the executor reporter
 * unless the leg is a plain healthy pass.
 */
function resultChecks(leg) {
  const checks = leg.assertion?.checks;
  if (Array.isArray(checks) && checks.length) {
    const failed = checks.filter((c) => c.ok === false); // ok:null (not-run) checks are excluded
    if (!failed.length) {
      return '';
    }
    const lines = ['```js'];
    for (const c of failed) {
      if (c.label && c.label !== 'null') {
        lines.push(`// failed: "${clean(c.label)}"`);
      }
      const actual = String(c.actual);
      if (actual.includes('\n')) {
        lines.push(`${callOf(c)} // ❌`, `/* got:\n${blockSafe(actual)}\n*/`);
      } else {
        lines.push(`${callOf(c)} // ❌ got ${qval(actual)}`);
      }
    }
    lines.push('```');
    return lines.join('\n');
  }
  // No structured checks (playwright/direct): show the reporter, except on a plain healthy pass.
  const reporter = leg.evidence?.reporter_output;
  return (leg.status !== 'not_reproduced' && reporter && reporter !== 'null') ? `\`\`\`\n${reporter}\n\`\`\`` : '';
}

/**
 * Formats the full structured HTTP expectation list for the reproduction details spoiler.
 *
 * Unlike the result section, this includes every check without pass/fail annotations so reviewers can
 * see the complete contract beside the generated curl script.
 */
function assertionList(checks) {
  if (!Array.isArray(checks) || !checks.length) {
    return '';
  }
  const lines = ['```js'];
  for (const c of checks) {
    lines.push(c.label && c.label !== 'null' ? `${callOf(c)} // ${clean(c.label)}` : callOf(c));
  }
  lines.push('```');
  return lines.join('\n');
}

/**
 * Prepares a multi-line actual value for embedding in a generated block comment.
 *
 * Long response fragments are capped and block-comment terminators are neutralized so failed-check
 * output cannot break the fenced pseudo test block in the issue comment.
 */
function blockSafe(s) {
  const capped = s.length > 1200 ? `${s.slice(0, 1200)}\n… (truncated)` : s;
  return capped.replace(/\*\//g, '* /');
}
