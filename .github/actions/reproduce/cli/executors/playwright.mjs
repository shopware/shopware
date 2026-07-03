// `playwright` executor: run the spec ONCE against the leg's shop. Spec fails (healthy assertion)
// ⇒ reproduced; passes ⇒ not_reproduced. A failure that is NOT a value assertion — missing element,
// navigation error, ambiguous locator, explicit PRECONDITION_NOT_FOUND throw — is cross-version
// drift, not the symptom ⇒ inconclusive. The harness owns admin auth and storefront consent.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { FILES, appUrl, makeResult, readJson } from '../lib.mjs';
import { stripNarration } from '../strip-narration.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
/**
 * Removes ANSI color codes from Playwright reporter errors before classification.
 */
const stripAnsi = (s) => s.replace(/\[[0-9;]*m/g, '');

/**
 * Runs a generated Playwright spec for one leg and returns the shared result contract.
 *
 * The verdict run strips narration before execution; optional video capture runs separately and
 * cannot influence the official status.
 *
 * @example
 * const result = await run({ plan, target: 'trunk' });
 * if (result.status === 'reproduced') {
 *   console.log(result.evidence.reporter_output);
 * }
 */
export async function run({ plan, target }) {
  const specPath = plan.script_path || FILES.specTs;
  if (!fs.existsSync(specPath)) {
    const reason = `generated spec '${specPath}' not found`;

    return makeResult({
      plan,
      target,
      status: 'blocked',
      assertion: nullAssertion(),
      evidence: { script_lang: 'ts', reporter_output: reason },
      blockedReason: reason,
    });
  }

  const storage = prepareAuth(plan, target);
  if (storage.blocked) {
    return storage.blocked;
  }

  const authored = fs.readFileSync(specPath, 'utf8');
  const cleanSpec = stripNarration(authored); // the verdict runs — and the comment shows — exactly this
  // A declared viewport is applied at context creation (both runs) so a mobile repro is exercised —
  // and recorded — at the right size, instead of a desktop frame the spec shrinks mid-test.
  const viewport = viewportEnv(plan.viewport);
  const report = runSpec(cleanSpec, storage.state, { video: false, viewport });

  // Opt-in evidence: when the plan asks for it, a separate narrated pass records a followable video
  // on each official leg (reported + trunk) — so whichever leg reproduces is captured — but never on
  // the agent's fast `try`. Its result is ignored, so it can never affect the verdict. Best-effort.
  if (plan.record_video === true && target !== 'builder') {
    try {
      runSpec(authored, storage.state, { video: true, viewport });
    } catch {
      // Video capture is optional evidence; the non-video verdict run remains authoritative.
    }
  }
  return classify(plan, target, cleanSpec, report);
}

/**
 * Builds the empty assertion payload used when Playwright cannot produce a verdict assertion.
 */
const nullAssertion = () => ({ expect: null, actual: null, matched: null });

/**
 * Converts a valid plan viewport into the JSON environment value read by Playwright config.
 *
 * Invalid or missing dimensions return null so the config falls back to Playwright's desktop
 * default instead of resizing after video capture has fixed the frame.
 */
function viewportEnv(v) {
  if (!v || !Number.isFinite(v.width) || !Number.isFinite(v.height) || v.width <= 0 || v.height <= 0) {
    return null;
  }
  return JSON.stringify({ width: Math.round(v.width), height: Math.round(v.height) });
}

/**
 * Prepares browser storage state owned by the harness rather than the generated spec.
 *
 * Admin login failure is treated as blocked environment setup, while storefront consent is best
 * effort and can be disabled by consent-specific repros through the plan.
 *
 * @example
 * const storage = prepareAuth({ layer: 'admin-ui' }, 'reported');
 * if (storage.blocked) {
 *   return storage.blocked;
 * }
 */
function prepareAuth(plan, target) {
  if (plan.layer === 'admin-ui') {
    const loginStateScript = path.join(here, '..', 'login-state.mjs');
    const login = spawnSync(process.execPath, [loginStateScript, appUrl(), 'admin-state.json'], {
      stdio: 'inherit',
    });
    const ok = login.status === 0;

    if (!ok) {
      const reason = 'the harness could not log in to the admin (env problem, not a reproduction result)';

      return {
        blocked: makeResult({
          plan,
          target,
          status: 'blocked',
          assertion: nullAssertion(),
          evidence: { script_lang: 'ts', reporter_output: 'harness admin login failed' },
          blockedReason: reason,
        }),
      };
    }
    return { state: 'admin-state.json' };
  }
  if (plan.layer === 'storefront-ui' && plan.browser_state?.auto_cookie_consent !== false) {
    const state = '.repro-storefront-state.json';
    const consentStateScript = path.join(here, '..', 'consent-state.mjs');
    const consentState = spawnSync(process.execPath, [consentStateScript, appUrl(), state], {
      stdio: 'ignore',
    });
    const consentStateCreated = consentState.status === 0 && fs.existsSync(state);

    if (consentStateCreated) {
      return { state };
    }
  }
  return { state: '' };
}

/**
 * Runs Playwright in an isolated temporary project for either verdict or optional video evidence.
 *
 * The non-video run returns the JSON report used for classification; the video run writes only
 * evidence and is isolated so it cannot overwrite verdict screenshots or traces.
 *
 * @example
 * const report = runSpec(cleanSpec, storage.state, { video: false, viewport });
 * if (!report) {
 *   return classify(plan, target, cleanSpec, report);
 * }
 */
function runSpec(spec, storageState, { video, viewport }) {
  const suffix = video ? '-video' : '';
  const hasRunnerTemp = process.env.RUNNER_TEMP && fs.existsSync(process.env.RUNNER_TEMP);
  const runDir = hasRunnerTemp
    ? path.join(process.env.RUNNER_TEMP, `repro-playwright${suffix}`)
    : `.repro-playwright${suffix}`;

  fs.rmSync(runDir, { recursive: true, force: true });
  fs.mkdirSync(runDir, { recursive: true });
  const runNodeModules = path.join(runDir, 'node_modules');
  if (fs.existsSync('node_modules') && !fs.existsSync(runNodeModules)) {
    fs.symlinkSync(path.resolve('node_modules'), runNodeModules);
  }
  fs.copyFileSync(path.join(here, '..', 'playwright.config.ts'), path.join(runDir, 'playwright.config.ts'));
  if (video) {
    fs.copyFileSync(path.join(here, '..', 'video-helpers.js'), path.join(runDir, 'video-helpers.js'));
  }
  fs.writeFileSync(path.join(runDir, FILES.specTs), spec);

  const reportPath = path.resolve(`pw-report${suffix}.json`);
  const outputDir = path.resolve(`test-results${suffix}`);
  const env = {
    ...process.env,
    APP_URL: appUrl(),
    PW_STORAGE: storageState,
    PW_JSON_REPORT: reportPath,
    PW_OUTPUT_DIR: outputDir,
    PW_VIDEO: video ? 'on' : 'off',
    ...(viewport ? { PW_VIEWPORT: viewport } : {}),
  };

  spawnSync('npx', ['playwright', 'test', '--config', path.join(runDir, 'playwright.config.ts')], {
    stdio: ['ignore', fs.openSync(`pw-stdout${suffix}.txt`, 'w'), fs.openSync(`pw-stderr${suffix}.txt`, 'w')],
    env,
  });

  if (video) {
    const webm = findWebm(outputDir);
    if (webm) {
      fs.copyFileSync(webm, 'video.webm');
    }
    return null;
  }
  return readJson(reportPath, null);
}

/**
 * Finds the first recorded Playwright video in a nested test-results directory.
 *
 * The video run can place recordings under generated test subdirectories, so this walks recursively
 * before copying the evidence to the stable `video.webm` artifact name.
 */
function findWebm(dir) {
  if (!fs.existsSync(dir)) {
    return null;
  }
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      const hit = findWebm(full);
      if (hit) {
        return hit;
      }
    } else if (entry.name.endsWith('.webm')) {
      return full;
    }
  }
  return null;
}

/**
 * Converts the Playwright JSON report into the two-leg reproduction verdict contract.
 *
 * A failed final value assertion is the reported symptom (`reproduced`); setup, navigation,
 * missing-element, and cross-version locator failures stay `inconclusive`.
 *
 * @example
 * const result = classify(plan, 'reported', cleanSpec, report);
 * const trusted = ['reproduced', 'not_reproduced'].includes(result.status);
 */
function classify(plan, target, spec, report) {
  const build = (status, actual, reporter, reason = null) => makeResult({
    plan,
    target,
    status,
    assertion: {
      expect: 'spec passes (healthy)',
      actual,
      matched: status === 'not_reproduced' ? true : status === 'reproduced' ? false : null,
    },
    evidence: {
      script: spec,
      script_lang: 'ts',
      reporter_output: reporter,
      artifacts: [{ kind: 'playwright-results', name: 'test-results/', run_artifact: `repro-${target}` }],
    },
    blockedReason: reason,
  });

  if (!report) {
    const stderr = fs.readFileSync('pw-stderr.txt', 'utf8').trim();
    const lastErrorLine = stderr.split('\n').pop() || 'unknown';

    return build(
      'blocked',
      null,
      `runner error: ${lastErrorLine}`,
      'playwright produced no parseable report (env not ready?)',
    );
  }

  const expected = report.stats?.expected ?? 0;
  const unexpected = report.stats?.unexpected ?? 0;
  const skipped = report.stats?.skipped ?? 0;
  const errs = stripAnsi(collectErrors(report));
  const short = errs.replace(/\s+/g, ' ').slice(0, 300);
  const pretty = errs.slice(0, 1200);

  if (!expected && !unexpected && !skipped) {
    return build('inconclusive', 'no tests ran', 'no tests executed', 'playwright ran no tests');
  }
  if (unexpected > 0) {
    if (/PRECONDITION_NOT_FOUND/.test(errs)) {
      return build(
        'inconclusive',
        `precondition missing on ${plan.version}`,
        `precondition absent on this version (UI differs) — ${short}`,
        `a precondition element the spec depends on is absent on ${plan.version} (likely cross-version UI drift); the symptom could not be exercised`,
      );
    }
    if (/net::ERR|ERR_CONNECTION|page\.goto|waiting for navigation|Navigation to .* failed/i.test(errs)) {
      return build(
        'inconclusive',
        `could not load the page on ${plan.version}`,
        `navigation/connection failure — ${short}`,
        `the spec could not load the target page on ${plan.version}; the symptom cannot be judged`,
      );
    }
    if (/strict mode violation/i.test(errs)) {
      return build(
        'inconclusive',
        `${unexpected} failing (ambiguous locator)`,
        `ambiguous locator failure — ${short}`,
        'the failure was a strict-mode locator error, not an assertion on one issue-specific state',
      );
    }
    const valueAssertionPattern = /Error: expect\(locator\)|expect\(locator\)\.|Expected:|Expected (pattern|string|value)|Received (string|value)|toBe|toHave|toContain|toEqual/;
    const valueAssertionFailed = valueAssertionPattern.test(errs);
    if (valueAssertionFailed) {
      return build('reproduced', `${unexpected} failing`, pretty || 'assertion failed');
    }
    if (/element\(s\) not found|waiting for .*locator/i.test(errs)) {
      return build(
        'inconclusive',
        `${unexpected} failing (locator/precondition)`,
        `locator/precondition failure — ${short}`,
        'the failure was a locator or missing-element error before a value assertion',
      );
    }
    return build(
      'inconclusive',
      `${unexpected} failing (non-assertion)`,
      `failure was not a value assertion (likely a missing/changed element) — ${short}`,
      'the failure was a locator/timeout error, not an assertion on a found element',
    );
  }
  if (skipped > 0 && !expected) {
    return build(
      'inconclusive',
      `${skipped} skipped`,
      'spec skipped (precondition not met on this version)',
      `the spec skipped itself (test.skip): the repro's precondition is not met on ${plan.version}`,
    );
  }
  return build('not_reproduced', `${expected} passing`, `all ${expected} test(s) passed (healthy)`);
}

/**
 * Collects failed and timed-out Playwright error messages from the JSON report tree.
 *
 * Classification needs the complete nested error text because assertion failures, navigation
 * failures, and locator setup drift can appear at different depths in the report.
 */
function collectErrors(report) {
  const messages = [];
  const walk = (node) => {
    if (Array.isArray(node)) {
      return node.forEach(walk);
    }
    if (node && typeof node === 'object') {
      if ((node.status === 'failed' || node.status === 'timedOut') && node.error?.message) {
        messages.push(node.error.message);
      }
      Object.values(node).forEach(walk);
    }
  };
  walk(report);
  return messages.join('\n\n---\n\n');
}
