// Deterministic admin login → Playwright storageState.
//
// Generated specs kept re-inventing the login preamble and intermittently fumbling it
// (getByLabel(/password/i) strict-mode violations, un-waited isVisible, ...). Login is
// HARNESS work, not repro work: do it once here with proven locators, save the session
// (cookies + localStorage, where the admin keeps its bearer token), and let every
// generated admin spec start authenticated.
//
// Usage: node login-state.mjs <APP_URL> <out-state.json>   (exit 0 = state saved)
import { chromium } from '@playwright/test';

const [appUrl, out] = process.argv.slice(2);
if (!appUrl || !out) { console.error('usage: login-state.mjs <APP_URL> <out.json>'); process.exit(2); }

const browser = await chromium.launch();
try {
  const page = await browser.newPage();
  await page.goto(`${appUrl}/admin`, { waitUntil: 'domcontentloaded' });

  // Proven locators (live-verified on 6.6.x–trunk): role-pinned, anchored names.
  const user = page.getByRole('textbox', { name: /^username$/i });
  await user.waitFor({ state: 'visible', timeout: 60_000 });
  await user.fill(process.env.SW_ADMIN_USER ?? 'admin');
  await page.getByRole('textbox', { name: /^password$/i }).fill(process.env.SW_ADMIN_PASS ?? 'shopware');
  await page.getByRole('button', { name: /log in|sign in/i }).click();

  // Authenticated shell is up when the global searchbox renders (stable across versions).
  await page.getByRole('searchbox').waitFor({ state: 'visible', timeout: 90_000 });

  await page.context().storageState({ path: out });
  console.log(`admin storageState saved to ${out}`);
  process.exit(0);
} catch (e) {
  console.error(`admin login failed: ${e.message?.split('\n')[0]}`);
  process.exit(1);
} finally {
  await browser.close();
}
