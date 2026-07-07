/**
 * Structural bundle validation command for `repro validate`.
 *
 * Trust in the verdict comes from the deterministic two-leg replay, not from this command. Validation
 * therefore focuses on structural and trust-critical defects: unsafe Playwright specs, missing
 * assertions, and install-specific ids that would make the trunk leg block.
 */
import fs from 'node:fs';
import { FILES, EXECUTORS, LAYERS, readJson } from '../../bundle.mjs';
import { stripNarration, hasLeftoverNarration } from '../../executors/playwright/strip-narration.mjs';

const LOCAL_HOSTS = ['localhost', '127.0.0.1', 'host.docker.internal'];

/**
 * Validates the authored reproduction bundle without executing it.
 *
 * Use this before `try` or `verify` to catch structural defects that would make the deterministic
 * two-leg run untrustworthy, such as unsafe Playwright setup or install-specific HTTP ids.
 */
export function validate() {
  const errors = [];
  const add = (cond, message) => {
    if (cond) {
      errors.push(message);
    }
  };

  if (!fs.existsSync(FILES.plan)) {
    return refuse([`${FILES.plan} not found — author the bundle first`]);
  }
  let plan;
  try {
    plan = readJson(FILES.plan);
  } catch {
    return refuse([`${FILES.plan} is not valid JSON`]);
  }

  add(!EXECUTORS.includes(plan.executor), `executor must be one of ${EXECUTORS.join('/')}, got ${JSON.stringify(plan.executor)}`);
  add(!LAYERS.includes(plan.layer), `layer must be one of ${LAYERS.join('/')}, got ${JSON.stringify(plan.layer)}`);
  add(typeof plan.issue !== 'number', 'issue must be the issue number');
  add(!plan.version, 'version is required');

  // Visual issues need Playwright because HTTP/direct cannot observe rendering symptoms.
  const issueClass = fs.existsSync('issue-class.txt') ? fs.readFileSync('issue-class.txt', 'utf8').trim() : '';
  add(
    issueClass === 'visual' && plan.executor !== 'playwright',
    'this issue is classified visual — it must use the playwright executor so the rendering symptom is actually observed',
  );

  if (plan.executor === 'http') {
    // Gate on the effective request list so `requests: []` cannot yield a bogus not_reproduced.
    const httpRequests = plan.requests || (plan.request ? [plan.request] : []);
    add(!httpRequests.length, 'http plan needs a `request` or non-empty `requests`');
    add(!plan.assertion && !plan.assertions, 'http plan needs an `assertion` or `assertions`');
    errors.push(...validateHttpIds(plan));
  }
  if (plan.executor === 'direct') {
    add(!fs.existsSync(plan.script_path || FILES.testPhp), `direct plan needs ${plan.script_path || FILES.testPhp}`);
  }
  if (plan.executor === 'playwright') {
    errors.push(...validateSpec(plan));
  }

  errors.push(...validateReadiness(plan));
  if (fs.existsSync(FILES.fixtures)) {
    try {
      readJson(FILES.fixtures);
    } catch {
      errors.push('fixtures.json is not valid JSON');
    }
  }

  return errors.length ? refuse(errors) : ok();
}

/**
 * Checks the Playwright spec shape that the verdict run will execute.
 *
 * The narrated video helpers are stripped before validation so the reviewed spec and the executed
 * spec have the same action/assertion logic.
 *
 * @example
 * const errors = validateSpec({ executor: 'playwright', script_path: 'repro.spec.ts' });
 * if (errors.length > 0) {
 *   return refuse(errors);
 * }
 */
function validateSpec(plan) {
  const specPath = plan.script_path || FILES.specTs;
  if (!fs.existsSync(specPath)) {
    return [`playwright plan needs ${specPath}`];
  }
  const errors = [];
  // Validate the stripped spec because that is what the verdict executes and the comment shows.
  const spec = stripNarration(fs.readFileSync(specPath, 'utf8'));
  if (hasLeftoverNarration(spec)) {
    errors.push('narrate()/mark() must each be a standalone one-line `await …(…);` statement (so they strip cleanly for the verdict run)');
  }

  // Catch every module-loading form so generated specs cannot escape the browser sandbox.
  const imports = [
    ...spec.matchAll(/\bfrom\s+['"]([^'"]+)['"]/g),
    ...spec.matchAll(/\brequire\s*\(\s*['"]([^'"]+)['"]\s*\)/g),
    ...spec.matchAll(/\bimport\s*\(\s*['"]([^'"]+)['"]\s*\)/g),
  ].map((m) => m[1]);
  const badImport = imports.find((m) => m !== '@playwright/test');
  if (badImport) {
    errors.push(`spec may only import @playwright/test (video narration from ./video-helpers.js is allowed and stripped), found ${JSON.stringify(badImport)}`);
  }
  // A computed dynamic import evades the allowlist above.
  if (/\bimport\s*\(/.test(spec)) {
    errors.push('spec must not use dynamic import() — generated specs are static Playwright tests with no runtime module loading');
  }
  if (/\b(?:node:)?child_process\b/.test(spec)) {
    errors.push('spec must not reference child_process — generated specs must not execute shell commands');
  }
  if (/\b(?:eval|Function)\s*\(/.test(spec)) {
    errors.push('spec must not use eval()/new Function() — generated specs must not execute generated code');
  }
  if (/\bprocess\.env\b/.test(spec)) {
    errors.push('spec must not read process.env — generated specs use the harness baseURL and fixture placeholders only');
  }
  if (/\b(?:writeFile(?:Sync)?|appendFile(?:Sync)?|createWriteStream|mkdir(?:Sync)?|rm(?:Sync)?|unlink(?:Sync)?|rmdir(?:Sync)?|openSync)\s*\(/.test(spec)) {
    errors.push('spec must not write through node:fs APIs — generated specs may only produce files via browser/filechooser flows or Playwright screenshots');
  }

  const awaitedExpects = (spec.match(/await\s+expect\s*\(/g) || []).length;
  if (awaitedExpects !== 1) {
    errors.push(
      `spec must contain exactly one awaited expect() — the final healthy assertion; found ${awaitedExpects}. `
      + 'Use locator.waitFor({state:\'visible\'}).catch(...) + PRECONDITION_NOT_FOUND for setup gates',
    );
  }

  if (/\bpage\.request\.|\bfetch\s*\(|page\.evaluate\([^)]*fetch/.test(spec)) {
    errors.push(
      'spec must not create setup state via Admin API / fetch / page.request — '
      + 'put static state in fixtures.json so both legs seed identically',
    );
  }

  const remoteUrl = [...spec.matchAll(/https?:\/\/([^/"'`\s)]+)/g)]
    .map((m) => m[1])
    .find((host) => !LOCAL_HOSTS.some((localHost) => host.startsWith(localHost)));
  if (remoteUrl) {
    errors.push(`spec references a non-local URL (${remoteUrl}); navigate relative to baseURL`);
  }

  const vp = plan.viewport;
  if (vp !== undefined && (typeof vp !== 'object' || !(vp.width > 0) || !(vp.height > 0))) {
    errors.push('viewport must be {"width":<px>,"height":<px>} with positive numbers (it sizes the run and the recording)');
  }
  // The plan viewport is applied at context creation, which also sizes video recording.
  if (/\bpage\.setViewportSize\s*\(/.test(spec)) {
    errors.push(
      'do not call page.setViewportSize() — declare "viewport" in reproduction-plan.json '
      + 'so the context (and the video frame) start at the right size',
    );
  }

  return errors;
}

/**
 * Core ids that can safely appear as literals in HTTP plans.
 *
 * The plan is replayed verbatim on both legs, but most Shopware ids are generated per install. Bare
 * 32-hex ids usually point at reported-leg rows that do not exist on trunk, so they are rejected
 * unless they are stable core constants or ids created by fixtures/request setup.
 */
const STABLE_IDS = new Set([
  '2fbb5fe2e29a4d70aa5854ce7ce3e20b', // Defaults::LANGUAGE_SYSTEM
  'b7d2554b0ce847cd82f3ac9bd1c0dfca', // Defaults::LIVE_VERSION
]);
const HEX32 = /(?<![0-9a-f])[0-9a-f]{32}(?![0-9a-f])/g;

/**
 * Finds literal install ids that would not survive replay on the trunk leg.
 *
 * IDs created by the request body or seeded through fixtures are allowed; references to existing
 * install entities must use placeholders resolved separately for each Shopware instance.
 *
 * @example
 * errors.push(...validateHttpIds(plan));
 */
function validateHttpIds(plan) {
  const allowed = new Set(STABLE_IDS);
  if (fs.existsSync(FILES.fixtures)) {
    try {
      for (const match of fs.readFileSync(FILES.fixtures, 'utf8').matchAll(HEX32)) {
        allowed.add(match[0]);
      }
    } catch {
      // Invalid fixtures are reported by the JSON validation pass.
    }
  }
  const blob = JSON.stringify([plan.request, plan.requests, plan.assertion, plan.assertions]);
  // Client-assigned ids created by the request body are portable across both legs.
  for (const match of blob.matchAll(/"id":"([0-9a-f]{32})"/g)) {
    allowed.add(match[1]);
  }

  const literals = [...new Set([...blob.matchAll(HEX32)].map((match) => match[0]))]
    .filter((id) => !allowed.has(id));
  if (!literals.length) {
    return [];
  }

  const sampleIds = literals.slice(0, 5).join(', ');
  const placeholders = [
    '{{COUNTRY}}',
    '{{SALUTATION}}',
    '{{SALUTATION2}}',
    '{{TAX}}',
    '{{CURRENCY}}',
    '{{PAYMENT_METHOD}}',
    '{{SHIPPING_METHOD}}',
    '{{CUSTOMER_GROUP}}',
    '{{LANGUAGE}}',
  ].join(', ');

  return [
    `http plan hardcodes install-specific id(s) ${sampleIds} — `
    + 'Shopware generates these per install, so they won\'t exist on the trunk leg '
    + 'and its request will fail (leg → blocked). '
    + `Use per-leg placeholders (${placeholders}, … — full list in prompt/guides/fixtures.md), `
    + 'which the executor resolves against each leg\'s live DB, or seed the entity '
    + 'in fixtures.json with a stable id and reference that.',
  ];
}

/**
 * Validates browser readiness checks that prove seeded setup before the symptom runs.
 *
 * These checks are intentionally limited to browser-visible markers; HTTP and direct preconditions
 * belong in executor assertions where they can affect the leg outcome.
 *
 * @example
 * const errors = validateReadiness({ seeded_readiness: [{ path: '/', selector: 'body' }] });
 * if (errors.length) {
 *   return refuse(errors);
 * }
 */
function validateReadiness(plan) {
  const checks = plan.seeded_readiness || plan.readiness_checks || [];
  if (!Array.isArray(checks)) {
    return ['seeded_readiness must be an array'];
  }
  return checks.flatMap((c, i) => {
    if ((c.kind ?? 'browser') !== 'browser') {
      return [`seeded_readiness[${i}].kind must be "browser" (HTTP/direct setup belongs in assertions)`];
    }
    if (!c.path && !c.url && !c.route) {
      return [`seeded_readiness[${i}] needs a path/url/route`];
    }
    if (!c.selector) {
      return [`seeded_readiness[${i}] needs a selector`];
    }
    return [];
  });
}

/**
 * Prints validation refusals and exits with failure.
 */
function refuse(errors) {
  for (const e of errors) {
    console.error(`REFUSED — ${e}`);
  }
  process.exit(1);
}
/**
 * Prints the successful validation marker consumed by workflow logs.
 */
function ok() {
  console.log('ok');
  return true;
}
