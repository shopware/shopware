/**
 * Creates deterministic Admin Playwright storage state for generated Admin UI specs.
 *
 * Login is harness work, not repro work. Generated specs start authenticated so they can navigate
 * straight to the route under test instead of reimplementing brittle login preambles.
 *
 * Usage: `node login-state.mjs <APP_URL> <out-state.json>` (exit 0 means state saved).
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';
import { adminPass, adminUser } from '../../../bundle.ts';

const here = path.dirname(fileURLToPath(import.meta.url));
const ADMIN_STATE = 'admin-state.json';

/**
 * Performs the admin login flow once and writes the authenticated Playwright storageState.
 *
 * @example
 * await loginToState('http://localhost:8000', 'admin-state.json'); // resolves on success, throws on failure
 */
export async function loginToState(appUrl, out) {
  const user = adminUser();
  const pass = adminPass();

  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.goto(`${appUrl}/admin`, { waitUntil: 'domcontentloaded' });

    const userField = page.getByRole('textbox', { name: /username|email/i })
      .or(page.locator('input[name="username"], input[name="email"], input[type="text"]').first()).first();
    await userField.waitFor({ state: 'visible', timeout: 60_000 });
    await userField.fill(user);

    const passField = page.getByRole('textbox', { name: /password/i })
      .or(page.locator('input[type="password"], input[name="password"]').first()).first();
    await passField.fill(pass);

    await page.getByRole('button', { name: /log in|sign in/i })
      .or(page.locator('button[type="submit"]').first()).first().click();

    // The authenticated shell is up once the global search renders.
    await page.getByRole('searchbox')
      .or(page.locator('.sw-search-bar, .sw-admin-menu, .sw-desktop').first()).first()
      .waitFor({ state: 'visible', timeout: 90_000 });

    await page.context().storageState({ path: out });
    console.log(`admin storageState saved to ${out}`);
  } finally {
    await browser.close();
  }
}

/**
 * Ensures an admin-login storageState file exists, spawning a fresh login only when needed.
 *
 * Both the seeded-readiness check and the playwright executor call this so admin auth is provisioned
 * by one path. An existing state file is reused unless `force` is set (the trusted verdict leg forces
 * a fresh login so a stale session cannot mask a real symptom). Returns whether the file is present.
 *
 * @example
 * const ok = ensureAdminState(appUrl()); // reuses admin-state.json if present, else logs in
 */
export function ensureAdminState(appUrl, { out = ADMIN_STATE, force = false } = {}) {
  if (!appUrl) {
    return false;
  }
  if (!force && fs.existsSync(out)) {
    return true;
  }
  const login = spawnSync(process.execPath, [path.join(here, 'login-state.mjs'), appUrl, out], {
    stdio: 'inherit',
  });
  return login.status === 0 && fs.existsSync(out);
}

/**
 * Runs the login helper only when the file is invoked as a script.
 *
 * Imports from readiness checks or the Playwright executor use `ensureAdminState()` without running
 * the process entrypoint.
 */
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const [appUrl, out] = process.argv.slice(2);
  if (!appUrl || !out) {
    console.error('usage: login-state.mjs <APP_URL> <out.json>');
    process.exit(2);
  }
  try {
    await loginToState(appUrl, out);
    process.exit(0);
  } catch (err) {
    console.error(`admin login failed: ${err.message?.split('\n')[0]}`);
    process.exit(1);
  }
}
