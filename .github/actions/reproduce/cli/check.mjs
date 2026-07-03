// Verify the seeded state renders: for each plan.seeded_readiness entry, load the route, assert the
// marker (selector visible, optional text, optional minimum size), and write a screenshot.
// It proves SETUP, not the symptom — keep the reported broken control out of here.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { FILES, appUrl, readJson } from './lib.mjs';

const OUT = 'seeded-readiness.json';
const here = path.dirname(fileURLToPath(import.meta.url));

const localUrl = (raw) => {
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

const readinessChecks = (plan) => plan.seeded_readiness || plan.readiness_checks || [];

export async function check({ plan = readJson(FILES.plan, {}) } = {}) {
  if (!appUrl() || plan.executor !== 'playwright') {
    return { ok: true, skipped: true };
  }
  const checks = readinessChecks(plan).filter((c) => c && (c.kind ?? 'browser') === 'browser');
  if (checks.length === 0) {
    writeReport({ ok: true, skipped: true, reason: 'no browser readiness checks' });
    return { ok: true, skipped: true };
  }

  const failures = [];
  const observations = [];
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

async function runCheck(page, c, index, failures, observations) {
  const name = String(c.name || `readiness check ${index + 1}`);
  const target = localUrl(c.path ?? c.url ?? c.route);
  if (!target) {
    failures.push(`${name}: missing or non-local path/url`);
    return;
  }

  const url = `${target}${target.includes('?') ? '&' : '?'}seededReadiness=${Date.now()}-${index}`;
  const elementTimeout = Number(c.timeout_ms || 15000);
  const navTimeout = Number(c.timeout_ms || 30000);
  await page.goto(url, { waitUntil: c.waitUntil || 'load', timeout: navTimeout });

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

async function storageStateOptions(plan) {
  if (fs.existsSync('admin-state.json')) {
    return { storageState: 'admin-state.json' };
  }
  if (plan.layer === 'storefront-ui' && plan.browser_state?.auto_cookie_consent !== false) {
    const state = '.repro-storefront-readiness-state.json';
    const res = spawnSync(process.execPath, [path.join(here, 'consent-state.mjs'), appUrl(), state], { stdio: 'ignore' });
    if (res.status === 0 && fs.existsSync(state)) {
      return { storageState: state };
    }
  }
  return {};
}

const writeReport = (report) => fs.writeFileSync(OUT, `${JSON.stringify(report, null, 2)}\n`);
