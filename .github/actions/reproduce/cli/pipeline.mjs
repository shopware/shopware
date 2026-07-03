// The one place the reproduction sequence is defined: (reset) → demodata → seed → readiness → run.
// `verify` (trusted, records the official result) and `try` (the agent's optional preview) both call
// this so both legs execute identical steps. Any setup failure writes a `blocked` result and stops —
// a leg never silently runs on broken setup.
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import { FILES, appUrl, readJson, shopDir, writeJson, blockedResult } from './lib.mjs';
import { reset } from './reset.mjs';
import { seed } from './seed.mjs';
import { runBundle } from './run-bundle.mjs';
// check.mjs pulls in @playwright/test, which is only installed for playwright bundles — import it
// lazily so an http/direct verify never needs Playwright.

/**
 * Runs the deterministic reproduction sequence for one target leg.
 *
 * This is the shared sequence for trusted `verify` and preview `try`: reset when requested,
 * optionally generate demodata, seed fixtures, prove readiness, then run the selected executor.
 *
 * @example
 * const result = await pipeline({ target: 'reported', out: FILES.result, reset: true });
 * if (result.status === 'blocked') {
 *   return;
 * }
 */
export async function pipeline({ target, out, reset: doReset }) {
  if (!appUrl()) {
    return fail(target, out, 'APP_URL is not set — the live shop coordinates were not exported');
  }
  if (!fs.existsSync(FILES.plan)) {
    return fail(target, out, 'reproduction-plan.json not found — author the bundle before verifying');
  }
  const plan = readJson(FILES.plan);

  if (doReset) {
    reset();
  }
  if (plan.fixtures?.demodata) {
    generateDemodata();
  }

  if (fs.existsSync(FILES.fixtures)) {
    try {
      await seed();
    } catch (err) {
      return fail(target, out, `seeding fixtures.json failed: ${err.message}`);
    }

    if (plan.executor === 'playwright') {
      const { check } = await import('./check.mjs');
      const readiness = await check({ plan });
      if (!readiness.ok && !readiness.skipped) {
        return fail(target, out, `seeded readiness precondition failed: ${(readiness.failures || []).join('; ').slice(0, 500)}`);
      }
    }
  }

  clearStaleArtifacts();
  const result = await runBundle({ target, out });
  if (plan.executor === 'playwright') {
    hintScreenshot();
  }
  return result;
}

/**
 * Writes a blocked result and stops the current leg after setup failure.
 */
const fail = (target, out, reason) => {
  const plan = readJson(FILES.plan, {});
  writeJson(out, blockedResult(plan, target, reason));
  console.error(`::error::${reason}`);
  return { status: 'blocked' };
};

/**
 * Generates bounded demo catalog data for repros that need realistic storefront volume.
 *
 * The same generator settings are used for the trunk provision path, keeping catalog-dependent
 * scenarios comparable between legs without allowing unbounded fixture setup.
 */
function generateDemodata() {
  const run = (args) => spawnSync('php', args, { cwd: shopDir(), stdio: 'inherit', env: { ...process.env, APP_ENV: 'prod' } });
  const demodataArgs = [
    'bin/console',
    'framework:demodata',
    '--no-interaction',
    '--multiplier=0.1',
    '--products=80',
    '--orders=0',
    '--reviews=0',
    '--promotions=0',
  ];

  if (run(demodataArgs).status !== 0) {
    console.warn('::warning::framework:demodata failed — continuing without demo data');
  }
  run(['bin/console', 'dal:refresh:index', '--no-interaction']);
}

/**
 * Removes stale executor artifacts before the official leg runs.
 *
 * Readiness screenshots are kept because they were just created for this setup; executor screenshots,
 * traces, and PHPUnit output must be fresh so reports cannot reuse evidence from an earlier attempt.
 *
 * @example
 * clearStaleArtifacts();
 * const result = await runBundle({ target, out });
 */
function clearStaleArtifacts() {
  fs.rmSync('playwright-report', { recursive: true, force: true });
  fs.rmSync('phpunit-output.txt', { force: true });
  if (fs.existsSync('test-results')) {
    for (const entry of fs.readdirSync('test-results')) {
      if (!/^seeded-readiness-.*\.png$/.test(entry)) {
        fs.rmSync(`test-results/${entry}`, { recursive: true, force: true });
      }
    }
  }
}

/**
 * Prints the newest screenshot hint after a Playwright leg for human review.
 */
function hintScreenshot() {
  const dir = 'test-results';
  const shot = fs.existsSync(dir) ? fs.readdirSync(dir).find((f) => f.endsWith('.png')) : null;
  console.log(
    shot
      ? `REVIEW THE SCREENSHOT before trusting the status — Read test-results/${shot}`
      : 'no screenshot captured — do not trust a status without visual evidence',
  );
}
