/**
 * Browser readiness command for proving seeded setup before a Playwright symptom run.
 *
 * For each `plan.seeded_readiness` entry it loads the route, asserts the marker, and writes a
 * screenshot. It proves setup only; the reported broken control belongs in the executor spec.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import type { Page } from '@playwright/test';
import { FILES, appUrl, readJson } from '../../bundle.ts';
import { ensureAdminState } from './boilerplate/login-state.mjs';
import type { Plan, ReadinessCheck } from '../../types.ts';

const OUT = 'seeded-readiness.json';
const here = path.dirname(fileURLToPath(import.meta.url));

/** The agent-authored plan, plus the optional browser-state hint the executors read. */
type BundledPlan = Partial<Plan> & { browser_state?: { auto_cookie_consent?: boolean } };

/** A recorded observation about one readiness check's target element. */
interface Observation {
  name: string;
  url: string;
  selector: string;
  rect: { x: number; y: number; width: number; height: number } | null;
}

/**
 * Converts a readiness route/path into a local URL safe for the provisioned shop.
 */
const localUrl = (raw: unknown): string | null => {
  const target = String(raw ?? '');
  if (!target) {
    return null;
  }
  if (/^https?:\/\//i.test(target)) {
    try {
      return ['localhost', '127.0.0.1', 'host.docker.internal'].includes(new URL(target).hostname)
        ? target
        : null;
    } catch {
      return null;
    }
  }
  return `${appUrl()}/${target.replace(/^\/+/, '')}`;
};

/**
 * Reads readiness checks from the current and legacy plan field names.
 */
const readinessChecks = (plan: BundledPlan): ReadinessCheck[] => plan.seeded_readiness || plan.readiness_checks || [];

/**
 * Verifies browser-visible seeded setup before the symptom executor runs.
 *
 * Each readiness check loads a route and records a screenshot while asserting only setup markers,
 * never the reported broken control, so failures block the leg as setup drift.
 */
export async function check({ plan = readJson<BundledPlan>(FILES.plan, {}) }: { plan?: BundledPlan } = {}) {
  if (!appUrl() || plan.executor !== 'playwright') {
    return { ok: true, skipped: true };
  }
  const checks = readinessChecks(plan).filter((c) => c && (c.kind ?? 'browser') === 'browser');
  if (checks.length === 0) {
    writeReport({ ok: true, skipped: true, reason: 'no browser readiness checks' });
    return { ok: true, skipped: true };
  }

  const failures: string[] = [];
  const observations: Observation[] = [];
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ ...(await storageStateOptions(plan)), viewport: { width: 1280, height: 840 } });
    const page = await context.newPage();
    for (const [index, c] of checks.entries()) {
      await runCheck(page, c, index, failures, observations);
    }
    await context.close();
  } finally {
    await browser.close();
  }

  writeReport({ ok: failures.length === 0, failures, observations });
  if (failures.length) {
    console.error('seeded readiness FAILED:');
    for (const f of failures) {
      console.error(`- ${f}`);
    }
  } else {
    console.log(`seeded readiness passed (${observations.length} check(s))`);
  }
  return { ok: failures.length === 0, failures };
}

/**
 * Runs one browser readiness check and records failures without throwing.
 *
 * The caller aggregates all failures so the blocked result can explain every missing seeded marker
 * instead of stopping at the first one.
 */
async function runCheck(page: Page, c: ReadinessCheck, index: number, failures: string[], observations: Observation[]): Promise<void> {
  const name = String(c.name || `readiness check ${index + 1}`);
  const target = localUrl(c.path ?? c.url ?? c.route);
  if (!target) {
    failures.push(`${name}: missing or non-local path/url`);
    return;
  }

  const url = `${target}${target.includes('?') ? '&' : '?'}seededReadiness=${Date.now()}-${index}`;
  const elementTimeout = Number(c.timeout_ms || 15000);
  const navTimeout = Number(c.timeout_ms || 30000);
  // A nav timeout / net::ERR_* must be recorded as a readiness failure, not thrown: runCheck's
  // contract is "records failures without throwing", and check() wraps the loop in try/finally with
  // no catch — an escaping rejection would kill the leg with no blocked result.json.
  try {
    await page.goto(url, { waitUntil: c.waitUntil || 'load', timeout: navTimeout });
  } catch (err) {
    failures.push(`${name}: navigation to ${url} failed (${String((err as Error)?.message || err).split('\n')[0]})`);
    return;
  }

  const consent = page.getByRole('button', { name: 'Only technically required' });
  if (await consent.count() === 1) {
    await consent.click().catch(() => {});
  }

  const selector = String(c.selector || 'body');
  await page.locator(selector).first().waitFor({ state: 'visible', timeout: elementTimeout })
    .catch(() => failures.push(`${name}: selector ${JSON.stringify(selector)} not visible on ${url}`));

  if (typeof c.text === 'string') {
    const textSelector = String(c.text_selector || selector);
    const actual = await page.locator(textSelector).first().textContent({ timeout: elementTimeout }).catch(() => '');
    if (!(actual ?? '').replace(/\s+/g, ' ').includes(c.text)) {
      failures.push(`${name}: ${JSON.stringify(textSelector)} does not include ${JSON.stringify(c.text)} on ${url}`);
    }
  }

  const rect = await page.locator(selector).first().boundingBox().catch(() => null);
  observations.push({ name, url, selector, rect });
  if ((rect?.width ?? 0) < Number(c.min_width ?? 1)) {
    failures.push(`${name}: ${selector} too narrow (${Math.round(rect?.width ?? 0)}px)`);
  }
  if ((rect?.height ?? 0) < Number(c.min_height ?? 1)) {
    failures.push(`${name}: ${selector} too short (${Math.round(rect?.height ?? 0)}px)`);
  }

  const shot = path.join('test-results', `seeded-readiness-${index + 1}.png`);
  fs.mkdirSync(path.dirname(shot), { recursive: true });
  await page.screenshot({ path: shot, fullPage: false }).catch(() => {});
}

/**
 * Builds Playwright storage options for readiness checks.
 *
 * Admin readiness provisions the harness login state through the same shared path the executor uses,
 * so an admin-route check never runs unauthenticated against the login screen on a fresh runner
 * (e.g. the trunk leg, or the reported leg when the agent never ran `repro try`). Storefront
 * readiness can pre-accept consent unless the reproduction explicitly needs the consent flow.
 */
async function storageStateOptions(plan: BundledPlan): Promise<{ storageState?: string }> {
  if (plan.layer === 'admin-ui') {
    if (ensureAdminState(appUrl())) {
      return { storageState: 'admin-state.json' };
    }
    return {};
  }
  if (fs.existsSync('admin-state.json')) {
    return { storageState: 'admin-state.json' };
  }
  if (plan.layer === 'storefront-ui' && plan.browser_state?.auto_cookie_consent !== false) {
    const state = '.repro-storefront-readiness-state.json';
    const res = spawnSync(
      process.execPath,
      [path.join(here, 'boilerplate/consent-state.mjs'), appUrl(), state],
      { stdio: 'ignore' },
    );
    if (res.status === 0 && fs.existsSync(state)) {
      return { storageState: state };
    }
  }
  return {};
}

/**
 * Writes the readiness report consumed by the pipeline and uploaded artifacts.
 */
const writeReport = (report: unknown) => fs.writeFileSync(OUT, `${JSON.stringify(report, null, 2)}\n`);
