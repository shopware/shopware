/**
 * Shared full reproduction sequence for preview and trusted verification legs.
 *
 * This CLI helper owns ordering: optional reset, optional demodata, fixture seeding, readiness
 * checks, then executor dispatch. Setup failures write a blocked result instead of letting a leg
 * silently run on broken state.
 */
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import { FILES, appUrl, readJson, shopDir, writeJson, blockedResult } from '../bundle.ts';
import type { Plan } from '../types.ts';
import { reset } from './commands/reset.ts';
import { seed } from './commands/seed.ts';
import { executeBundle } from './execute-bundle.ts';

/**
 * Runs the deterministic reproduction sequence for one target leg.
 *
 * This is the shared sequence for trusted `verify` and preview `try`: reset when requested,
 * optionally generate demodata, seed fixtures, prove readiness, then run the selected executor.
 * Browser readiness checks are imported lazily so HTTP/direct bundles do not require Playwright.
 *
 * @returns The executor's canonical leg result, or a blocked result when reset, seeding, or readiness
 * fails before the reproduction can be judged.
 */
export async function fullRun({ target, out, reset: doReset }: { target: string; out: string; reset?: boolean }) {
  if (!appUrl()) {
    return fail(target, out, 'APP_URL is not set — the live shop coordinates were not exported');
  }
  if (!fs.existsSync(FILES.plan)) {
    return fail(target, out, 'reproduction-plan.json not found — author the bundle before verifying');
  }
  // A malformed plan blocks the leg with a reason instead of throwing an unhandled rejection that
  // would leave no result.json (fail() reads the plan with a {} fallback, so it cannot re-throw).
  let plan: Plan;
  try {
    plan = readJson<Plan>(FILES.plan);
  } catch (err) {
    return fail(target, out, `reproduction-plan.json is not valid JSON: ${(err as Error)?.message || err}`);
  }

  if (doReset) {
    // A failed restore of an EXISTING snapshot blocks the leg — never judge a symptom on un-restored,
    // possibly agent-polluted state (that would post a real-looking but untrustworthy verdict).
    const r = reset();
    if (r && r.ok === false) {
      return fail(target, out, r.reason!);
    }
  }
  if (plan.fixtures?.demodata) {
    generateDemodata();
  }

  if (fs.existsSync(FILES.fixtures)) {
    try {
      await seed();
    } catch (err) {
      return fail(target, out, `seeding fixtures.json failed: ${(err as Error).message}`);
    }

    if (plan.executor === 'playwright') {
      const { check } = await import('../executors/playwright/readiness-check.ts');
      const readiness = await check({ plan });
      if (!readiness.ok && !readiness.skipped) {
        return fail(target, out, `seeded readiness precondition failed: ${(readiness.failures || []).join('; ').slice(0, 500)}`);
      }
    }
  }

  clearStaleArtifacts();
  const result = await executeBundle({ target, out });
  if (plan.executor === 'playwright') {
    hintScreenshot();
  }
  return result;
}

/**
 * Writes a blocked result and stops the current leg after setup failure.
 */
const fail = (target: string, out: string, reason: string) => {
  const plan = readJson<Partial<Plan>>(FILES.plan, {});
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
  const run = (args: string[]) => spawnSync('php', args, { cwd: shopDir(), stdio: 'inherit', env: { ...process.env, APP_ENV: 'prod' } });
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
 * const result = await executeBundle({ target, out });
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
