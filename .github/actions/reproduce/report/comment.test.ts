import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// comment.ts is a top-level script: it renders on import from env + an `artifacts/` tree and writes
// the comment to OUT (default comment.md) + stdout. So drive it exactly like the workflow does —
// lay out the artifact fixtures, spawn `node comment.ts`, and assert on the rendered markdown.
const SCRIPT = path.join(path.dirname(fileURLToPath(import.meta.url)), 'comment.ts');
const ZWSP = '​';

// Build the `artifacts/repro-plan` + per-leg tree the workflow uploads, plus any co-located extras
// (agent-summary.md, giveup.txt, workspace-edits.txt), the spec file, and fixtures.json.
interface LayoutOpts {
  plan?: unknown;
  reported?: unknown;
  trunk?: unknown;
  extras?: Record<string, string>;
  spec?: { name: string; content: string };
  fixtures?: string;
  evidence?: unknown;
}

function layout({ plan, reported, trunk, extras = {}, spec, fixtures, evidence }: LayoutOpts = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'repro-comment-'));
  const art = path.join(dir, 'artifacts');
  const planDir = path.join(art, 'repro-plan');
  fs.mkdirSync(planDir, { recursive: true });
  if (plan !== undefined) {
    fs.writeFileSync(path.join(planDir, 'reproduction-plan.json'), JSON.stringify(plan));
  }
  const leg = (name: string, result?: unknown) => {
    if (result === undefined) {
      return;
    }
    fs.mkdirSync(path.join(art, name), { recursive: true });
    fs.writeFileSync(path.join(art, name, 'result.json'), JSON.stringify(result));
  };
  leg('repro-reported', reported);
  leg('repro-trunk', trunk);
  for (const [name, content] of Object.entries(extras)) {
    fs.writeFileSync(path.join(planDir, name), content);
  }
  if (spec !== undefined) {
    fs.writeFileSync(path.join(planDir, spec.name), spec.content);
  }
  if (fixtures !== undefined) {
    fs.writeFileSync(path.join(planDir, 'fixtures.json'), fixtures);
  }
  // resultSection reads evidence.json from the working directory, not the artifact dir.
  if (evidence !== undefined) {
    fs.writeFileSync(path.join(dir, 'evidence.json'), JSON.stringify(evidence));
  }
  return { dir, art };
}

// Run with a deliberately minimal env (PATH only + our vars) so the caller's MODE/VERDICT/
// GITHUB_STEP_SUMMARY can never leak in and contaminate the render.
function run(env: NodeJS.ProcessEnv, tree: { dir: string; art: string }) {
  const r = spawnSync('node', [SCRIPT], {
    cwd: tree.dir,
    encoding: 'utf8',
    env: { PATH: process.env.PATH, ART: tree.art, ...env },
  });
  const outFile = path.join(tree.dir, env.OUT || 'comment.md');
  return {
    status: r.status,
    stdout: r.stdout,
    stderr: r.stderr,
    out: fs.existsSync(outFile) ? fs.readFileSync(outFile, 'utf8') : '',
  };
}

// --- VERDICT comment from artifact fixtures -------------------------------

test('verdict render: headline, summary matrix, collapsed both-versions result, scenario, testcase, fixtures', () => {
  const tree = layout({
    plan: {
      executor: 'playwright',
      layer: 'storefront-ui',
      version: '6.6.0.0',
      confidence: 1,
      scenario: ['Given a filled cart', 'When the customer checks out', 'Then an error is shown'],
    },
    reported: { status: 'reproduced', version: '6.6.0.0', evidence: { script_lang: 'ts' } },
    trunk: { status: 'reproduced' },
    spec: { name: 'repro.spec.ts', content: "test('checkout', async () => { expect(page).toBeTruthy(); });" },
    fixtures: '{"product":{"name":"Demo"}}',
  });
  const { status, out } = run({ VERDICT: 'live_bug', RUN_URL: 'https://ci/run/1' }, tree);
  assert.equal(status, 0);

  // Headline + badge from verdicts.json.
  assert.match(out, /## AI Report \(Reproduction\): Bug reproduced/);
  assert.match(out, /\| \*\*Verdict\*\* \| 🔴 Live bug \|/);
  // Reported version + per-leg status phrases.
  assert.match(out, /\| \*\*Reported\*\* `6\.6\.0\.0` \| 🔴 reproduced \|/);
  assert.match(out, /\| \*\*Trunk\*\* \| 🔴 reproduced \|/);
  // Surface · executor gloss.
  assert.match(out, /Storefront \(browser\) · Playwright/);
  // Both legs share a status → collapsed into one "Both versions" spoiler.
  assert.match(out, /<strong>Both versions<\/strong> — 🔴 reproduced/);
  assert.ok(!out.includes('<strong>trunk</strong>'), 'matching statuses must not render separate legs');
  // Scenario keywords get bolded.
  assert.match(out, /- \*\*Given\*\* a filled cart/);
  assert.match(out, /- \*\*Then\*\* an error is shown/);
  // Authored test case + fixtures surfaced.
  assert.match(out, /```ts\ntest\('checkout'/);
  assert.match(out, /```json\n\{"product":\{"name":"Demo"\}\}/);
  assert.match(out, /🔁 \[Reproduce run\]\(https:\/\/ci\/run\/1\)/);
});

test('verdict render: divergent leg statuses render both labelled legs, not the collapsed form', () => {
  const tree = layout({
    plan: { executor: 'playwright', layer: 'admin-ui', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0' },
    trunk: { status: 'not_reproduced' },
  });
  const { status, out } = run({ VERDICT: 'fixed_on_trunk' }, tree);
  assert.equal(status, 0);
  assert.match(out, /<strong>v6\.6\.0\.0<\/strong> — 🔴 reproduced/);
  assert.match(out, /<strong>trunk<\/strong> — 🟢 not reproduced/);
  assert.ok(!out.includes('Both versions'));
});

test('verdict render: needs_human_review with FIX fills the with-fix headline + callout, and edits warn', () => {
  const tree = layout({
    plan: { executor: 'http', layer: 'store-api', version: '6.6.0.0', confidence: 0.4 },
    reported: { status: 'inconclusive', version: '6.6.0.0' },
    extras: { 'workspace-edits.txt': 'shop/src/Core/Foo.php\n' },
  });
  const { status, out } = run({
    VERDICT: 'needs_human_review',
    FIX: 'src/Core/Checkout/Cart/Cart.php',
    UNSURE: 'confidence 0.4 below threshold',
  }, tree);
  assert.equal(status, 0);
  assert.match(out, /Reported precondition not found — needs human review/);
  assert.match(out, /inspect src\/Core\/Checkout\/Cart\/Cart\.php as a possible fix candidate/);
  assert.match(out, /\| \*\*Likely fix\*\* \| src\/Core\/Checkout\/Cart\/Cart\.php \|/);
  assert.match(out, /\| \*\*Not trusted\*\* \| confidence 0\.4 below threshold \|/);
  // The out-of-bundle edits block renders the changed paths.
  assert.match(out, /changed files \*\*outside its reproduction bundle\*\*/);
  assert.match(out, /shop\/src\/Core\/Foo\.php/);
});

// --- INCOMPLETE comment ---------------------------------------------------

test('incomplete render: giveup.txt reason wins over the REASON env, bundle still shown', () => {
  const tree = layout({
    plan: {
      executor: 'direct',
      layer: 'service',
      scenario: ['Given a service', 'Then it throws'],
    },
    extras: { 'giveup.txt': 'The precondition entity never rendered.' },
    spec: { name: 'ReproTest.php', content: '<?php class ReproTest {}' },
    fixtures: '{"seed":true}',
  });
  const { status, out } = run({
    MODE: 'incomplete',
    REASON: 'a generic env reason',
    RUN_URL: 'https://ci/run/9',
  }, tree);
  assert.equal(status, 0);
  assert.match(out, /## Reproduction: incomplete/);
  assert.match(out, /\*\*Why:\*\* The precondition entity never rendered\./);
  assert.ok(!out.includes('a generic env reason'), 'giveup.txt must take precedence over REASON');
  // Direct executor → PHP test case, tagged as PHPUnit.
  assert.match(out, /Reproduction test \(PHPUnit\)/);
  assert.match(out, /```php\n<\?php class ReproTest \{\}/);
  assert.match(out, /```json\n\{"seed":true\}/);
  assert.match(out, /- \*\*Given\*\* a service/);
  assert.match(out, /🔁 \[Reproduce run\]\(https:\/\/ci\/run\/9\)/);
});

test('incomplete render: falls back to the REASON env when no giveup.txt exists', () => {
  const tree = layout({ plan: { executor: 'playwright' } });
  const { status, out } = run({ MODE: 'incomplete', REASON: 'run was cancelled' }, tree);
  assert.equal(status, 0);
  assert.match(out, /\*\*Why:\*\* run was cancelled/);
});

test('incomplete render: falls back to the default reason when neither giveup.txt nor REASON is set', () => {
  const tree = layout({ plan: { executor: 'playwright' } });
  const { status, out } = run({ MODE: 'incomplete' }, tree);
  assert.equal(status, 0);
  // The default sentence lives in verdicts.json (incomplete_reason_default).
  assert.match(out, /\*\*Why:\*\* The agent did not produce a verified reproduction bundle\./);
});

// --- redact(): all supported secret token shapes --------------------------

test('redact: every token shape is scrubbed at the write boundary', () => {
  const secrets = [
    'anthropic key sk-ant-api03-abcdefgh12345678 here',
    'gh token ghp_ABCDEFGHIJKLMNOPQRSTUVWX here',
    'pat github_pat_11ABCDEFGH0123456789 here',
    'aws AKIAIOSFODNN7EXAMPLE here',
    'auth Bearer 0123456789abcdefABCDEF here',
  ].join('\n');
  const tree = layout({
    plan: { executor: 'playwright', layer: 'admin-ui', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0' },
    extras: { 'agent-summary.md': secrets },
  });
  const { status, out } = run({ VERDICT: 'live_bug' }, tree);
  assert.equal(status, 0);
  // Redacted markers present.
  assert.match(out, /\[REDACTED_KEY\]/);
  assert.match(out, /\[REDACTED_AWS_KEY\]/);
  assert.match(out, /Bearer \[REDACTED\]/);
  assert.equal((out.match(/\[REDACTED_TOKEN\]/g) || []).length, 2, 'ghp_ and github_pat_ both redact to _TOKEN');
  // Raw secrets gone.
  assert.ok(!out.includes('sk-ant-api03'));
  assert.ok(!out.includes('ghp_ABCDEF'));
  assert.ok(!out.includes('github_pat_11'));
  assert.ok(!out.includes('AKIAIOSFODNN7EXAMPLE'));
  assert.ok(!out.includes('0123456789abcdefABCDEF'));
});

// --- defang(): structure-breaking tokens in agent content ------------------

test('defang: <details>/<summary> tags and ``` fences in the authored test case are neutralized', () => {
  const injected = [
    '</summary></details>',
    '<details><summary>spoofed verdict</summary>',
    '```',
    'and text',
  ].join('\n');
  const tree = layout({
    plan: { executor: 'playwright', layer: 'admin-ui', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0' },
    spec: { name: 'repro.spec.ts', content: injected },
  });
  const { status, out } = run({ VERDICT: 'live_bug' }, tree);
  assert.equal(status, 0);
  // Spoiler tags are entity-escaped so they can't close the comment's real <details>.
  assert.match(out, /&lt;\/summary&gt;&lt;\/details&gt;/);
  assert.match(out, /&lt;details&gt;&lt;summary&gt;spoofed verdict&lt;\/summary&gt;/);
  assert.ok(!injected.split('\n').some((l) => out.includes(`${l}\n`) && l.startsWith('<details>')));
  // The triple-backtick run is broken up with a zero-width space so it can't terminate the fence.
  assert.ok(out.includes(`\`${ZWSP}\`${ZWSP}\``), 'code fence should be defanged with ZWSPs');
});

test('defang: a leg reporter_output cannot break out of its fence + spoiler', () => {
  // reporter_output is the agent-authored test's own output (PHPUnit failure block / exception text).
  const injected = ['```', '</details>', '## Spoofed: safe to close'].join('\n');
  const tree = layout({
    plan: { executor: 'direct', layer: 'core', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0', evidence: { reporter_output: injected, script_lang: 'php' } },
  });
  const { status, out } = run({ VERDICT: 'live_bug' }, tree);
  assert.equal(status, 0);
  assert.ok(out.includes(`\`${ZWSP}\`${ZWSP}\``), 'reporter fence must be ZWSP-defanged');
  assert.match(out, /&lt;\/details&gt;/);
  assert.ok(!out.includes('\n</details>\n## Spoofed'), 'reporter must not close the spoiler and inject markdown');
});

test('defang: a multi-line HTTP `actual` value cannot break out of the pseudo-code block', () => {
  const injected = ['line one */', '```', '</details>', '## Spoofed'].join('\n');
  const tree = layout({
    plan: { executor: 'http', layer: 'store-api', version: '6.6.0.0', confidence: 1 },
    reported: {
      status: 'reproduced',
      version: '6.6.0.0',
      evidence: { script: 'curl -s https://shop/store-api/x', script_lang: 'sh' },
      assertion: { checks: [{ role: 'assert', op: 'equals', subject: 'response.body', expected: 'ok', actual: injected, ok: false, label: 'body' }] },
    },
  });
  const { status, out } = run({ VERDICT: 'live_bug' }, tree);
  assert.equal(status, 0);
  assert.ok(out.includes('* /'), 'blockSafe must neutralize the */ block-comment terminator');
  assert.ok(out.includes(`\`${ZWSP}\`${ZWSP}\``), 'the fence in a multi-line actual must be ZWSP-defanged');
  assert.match(out, /&lt;\/details&gt;/);
});

test('defang: workspace-edits.txt (EDITS) cannot break out of its fence + spoiler', () => {
  const injected = ['shop/src/Core/Foo.php', '```', '</details>', '## Spoofed'].join('\n');
  const tree = layout({
    plan: { executor: 'playwright', layer: 'admin-ui', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0' },
    extras: { 'workspace-edits.txt': injected },
  });
  const { status, out } = run({ VERDICT: 'needs_human_review' }, tree);
  assert.equal(status, 0);
  assert.ok(out.includes(`\`${ZWSP}\`${ZWSP}\``), 'EDITS fence must be ZWSP-defanged');
  assert.match(out, /&lt;\/details&gt;/);
});

// --- resultChecks(): the jqError line for structured HTTP legs -------------

test('resultChecks: a failed HTTP check renders the pseudo-assert plus its jq error, skipping passed/not-run', () => {
  const tree = layout({
    plan: { executor: 'http', layer: 'store-api', version: '6.6.0.0', confidence: 1 },
    reported: {
      status: 'reproduced',
      version: '6.6.0.0',
      evidence: { script: 'curl -s https://shop/store-api/checkout', script_lang: 'sh' },
      assertion: {
        checks: [
          { role: 'precondition', op: 'present', subject: 'response.body', ok: true },
          { role: 'assert', op: 'equals', subject: 'response.status', expected: '200', actual: '500', ok: false, label: 'status must be 200', jqError: 'jq: error: null (null) cannot be matched' },
          { role: 'assert', op: 'contains', subject: 'response.body', expected: 'ok', actual: 'nope', ok: null },
        ],
      },
    },
  });
  const { status, out } = run({ VERDICT: 'live_bug' }, tree);
  assert.equal(status, 0);
  // Failed check → labelled comment + pseudo-assert with the got value.
  assert.match(out, /\/\/ failed: "status must be 200"/);
  assert.match(out, /assertEquals\(response\.status, 200\) \/\/ ❌ got 500/);
  // The jqError line is emitted right under its check.
  assert.match(out, /\/\/ {3}jq: jq: error: null \(null\) cannot be matched/);
  // Passed (ok:true) and not-run (ok:null) checks are excluded from the result body.
  assert.ok(!out.includes("❌ got 'nope'"), 'ok:null checks must not appear as failed');
  // The full assertion list (Checks section) still lists every expectation beside the curl.
  assert.match(out, /\*\*Checks\*\*/);
  assert.match(out, /requirePresent\(response\.body\)/);
});

// --- evidence manifest wiring ---------------------------------------------

test('legSpoiler: screenshot and recording links come from evidence.json per leg', () => {
  const tree = layout({
    plan: { executor: 'playwright', layer: 'storefront-ui', version: '6.6.0.0', confidence: 1 },
    reported: { status: 'reproduced', version: '6.6.0.0' },
    trunk: { status: 'not_reproduced' },
    evidence: {
      legs: [
        { name: 'reported', png: 'https://cdn/shot-a.png', webm: 'https://cdn/rec-a.webm' },
        { name: 'trunk', png: 'https://cdn/shot-b.png' },
      ],
    },
  });
  const { status, out } = run({ VERDICT: 'fixed_on_trunk' }, tree);
  assert.equal(status, 0);
  assert.match(out, /▶ \[Watch the recording\]\(https:\/\/cdn\/rec-a\.webm\)/);
  assert.match(out, /!\[screenshot\]\(https:\/\/cdn\/shot-a\.png\)/);
  assert.match(out, /!\[screenshot\]\(https:\/\/cdn\/shot-b\.png\)/);
});

// --- degradation: missing artifacts don't crash the renderer ---------------

test('verdict render tolerates a completely empty artifact tree (no plan, no legs)', () => {
  const tree = layout({});
  const { status, out } = run({ VERDICT: 'needs_human_review' }, tree);
  assert.equal(status, 0);
  assert.match(out, /## AI Report \(Reproduction\)/);
  // Missing plan.version → the '?' fallback; unknown surface/executor glosses.
  assert.match(out, /`\?`/);
  assert.match(out, /unknown · unknown/);
});
