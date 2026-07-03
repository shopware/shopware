// `repro validate` — thin, structural contract check. Trust in the verdict comes from the
// deterministic two-leg re-run, NOT from this file, so it enforces only what is structural or
// genuinely trust/correctness-critical and leaves quality choices to the prompt guides. It also
// inspects the Playwright spec so shape defects are caught WITHOUT executing anything.
//
// Refusals print `REFUSED — <reason>` and exit 1; a clean bundle prints `ok`.
import fs from 'node:fs';
import { FILES, EXECUTORS, LAYERS, readJson } from './lib.mjs';
import { stripNarration, hasLeftoverNarration } from './strip-narration.mjs';

const LOCAL_HOSTS = ['localhost', '127.0.0.1', 'host.docker.internal'];

export function validate() {
  const errors = [];
  const add = (cond, message) => { if (cond) errors.push(message); };

  if (!fs.existsSync(FILES.plan)) return refuse([`${FILES.plan} not found — author the bundle first`]);
  let plan;
  try { plan = readJson(FILES.plan); } catch { return refuse([`${FILES.plan} is not valid JSON`]); }

  add(!EXECUTORS.includes(plan.executor), `executor must be one of ${EXECUTORS.join('/')}, got ${JSON.stringify(plan.executor)}`);
  add(!LAYERS.includes(plan.layer), `layer must be one of ${LAYERS.join('/')}, got ${JSON.stringify(plan.layer)}`);
  add(typeof plan.issue !== 'number', 'issue must be the issue number');
  add(!plan.version, 'version is required');

  // The one kept domain gate: a visual issue must use playwright — an http/direct run can't observe
  // a rendering bug and would post a false not_reproduced. Classification is written by compose-prompt.
  const issueClass = fs.existsSync('issue-class.txt') ? fs.readFileSync('issue-class.txt', 'utf8').trim() : '';
  add(issueClass === 'visual' && plan.executor !== 'playwright', 'this issue is classified visual — it must use the playwright executor so the rendering symptom is actually observed');

  if (plan.executor === 'http') {
    add(!plan.request && !plan.requests, 'http plan needs a `request` or `requests`');
    add(!plan.assertion && !plan.assertions, 'http plan needs an `assertion` or `assertions`');
    errors.push(...validateHttpIds(plan));
  }
  if (plan.executor === 'direct') add(!fs.existsSync(plan.script_path || FILES.testPhp), `direct plan needs ${plan.script_path || FILES.testPhp}`);
  if (plan.executor === 'playwright') errors.push(...validateSpec(plan));

  errors.push(...validateReadiness(plan));
  if (fs.existsSync(FILES.fixtures)) { try { readJson(FILES.fixtures); } catch { errors.push('fixtures.json is not valid JSON'); } }

  return errors.length ? refuse(errors) : ok();
}

function validateSpec(plan) {
  const specPath = plan.script_path || FILES.specTs;
  if (!fs.existsSync(specPath)) return [`playwright plan needs ${specPath}`];
  const errors = [];
  // Validate what the verdict actually runs + what the comment shows: the spec with narration stripped.
  const spec = stripNarration(fs.readFileSync(specPath, 'utf8'));
  if (hasLeftoverNarration(spec)) errors.push('narrate()/mark() must each be a standalone one-line `await …(…);` statement (so they strip cleanly for the verdict run)');

  const imports = [...spec.matchAll(/\bfrom\s+['"]([^'"]+)['"]/g)].map((m) => m[1]);
  const badImport = imports.find((m) => m !== '@playwright/test');
  if (badImport) errors.push(`spec may only import @playwright/test (video narration from ./video-helpers.js is allowed and stripped), found ${JSON.stringify(badImport)}`);

  const awaitedExpects = (spec.match(/await\s+expect\s*\(/g) || []).length;
  if (awaitedExpects !== 1) errors.push(`spec must contain exactly one awaited expect() — the final healthy assertion; found ${awaitedExpects}. Use locator.waitFor({state:'visible'}).catch(...) + PRECONDITION_NOT_FOUND for setup gates`);

  if (/\bpage\.request\.|\bfetch\s*\(|page\.evaluate\([^)]*fetch/.test(spec)) errors.push('spec must not create setup state via Admin API / fetch / page.request — put static state in fixtures.json so both legs seed identically');

  const remoteUrl = [...spec.matchAll(/https?:\/\/([^/"'`\s)]+)/g)].map((m) => m[1]).find((host) => !LOCAL_HOSTS.some((h) => host.startsWith(h)));
  if (remoteUrl) errors.push(`spec references a non-local URL (${remoteUrl}); navigate relative to baseURL`);

  const vp = plan.viewport;
  if (vp !== undefined && (typeof vp !== 'object' || !(vp.width > 0) || !(vp.height > 0))) {
    errors.push('viewport must be {"width":<px>,"height":<px>} with positive numbers (it sizes the run and the recording)');
  }
  // The plan viewport is authoritative and applied at context creation; an in-spec resize records at
  // the wrong size, so steer it to the plan field instead.
  if (/\bpage\.setViewportSize\s*\(/.test(spec)) {
    errors.push('do not call page.setViewportSize() — declare "viewport" in reproduction-plan.json so the context (and the video frame) start at the right size');
  }

  return errors;
}

// The plan is replayed verbatim on BOTH legs, but every install generates its own UUIDs for
// countries/salutations/payment methods/etc. A literal install id resolved on the reported shop
// won't exist on trunk — the request 400s and the leg comes back `blocked` instead of a verdict
// (see issue #2). Reject bare 32-hex ids in an http plan and steer to per-leg placeholders. Two
// exemptions: the handful of Shopware core constants that are identical on every install, and any id
// the agent seeds through fixtures.json (that row is created with the same id on both legs).
const STABLE_IDS = new Set([
  '2fbb5fe2e29a4d70aa5854ce7ce3e20b', // Defaults::LANGUAGE_SYSTEM
  'b7d2554b0ce847cd82f3ac9bd1c0dfca', // Defaults::LIVE_VERSION
]);
const HEX32 = /(?<![0-9a-f])[0-9a-f]{32}(?![0-9a-f])/g;

function validateHttpIds(plan) {
  const allowed = new Set(STABLE_IDS);
  if (fs.existsSync(FILES.fixtures)) {
    try { for (const m of fs.readFileSync(FILES.fixtures, 'utf8').matchAll(HEX32)) allowed.add(m[0]); } catch { /* invalid fixtures reported elsewhere */ }
  }
  const blob = JSON.stringify([plan.request, plan.requests, plan.assertion, plan.assertions]);
  const literals = [...new Set([...blob.matchAll(HEX32)].map((m) => m[0]))].filter((id) => !allowed.has(id));
  if (!literals.length) return [];
  return [`http plan hardcodes install-specific id(s) ${literals.slice(0, 5).join(', ')} — Shopware generates these per install, so they won't exist on the trunk leg and its request will fail (leg → blocked). Use per-leg placeholders ({{COUNTRY}}, {{SALUTATION}}, {{SALUTATION2}}, {{TAX}}, {{CURRENCY}}, {{PAYMENT_METHOD}}, {{SHIPPING_METHOD}}, {{CUSTOMER_GROUP}}, {{LANGUAGE}}, … — full list in prompt/guides/fixtures.md), which the executor resolves against each leg's live DB, or seed the entity in fixtures.json with a stable id and reference that.`];
}

function validateReadiness(plan) {
  const checks = plan.seeded_readiness || plan.readiness_checks || [];
  if (!Array.isArray(checks)) return ['seeded_readiness must be an array'];
  return checks.flatMap((c, i) => {
    if ((c.kind ?? 'browser') !== 'browser') return [`seeded_readiness[${i}].kind must be "browser" (HTTP/direct setup belongs in assertions)`];
    if (!c.path && !c.url && !c.route) return [`seeded_readiness[${i}] needs a path/url/route`];
    if (!c.selector) return [`seeded_readiness[${i}] needs a selector`];
    return [];
  });
}

function refuse(errors) {
  for (const e of errors) console.error(`REFUSED — ${e}`);
  process.exit(1);
}
function ok() { console.log('ok'); return true; }
