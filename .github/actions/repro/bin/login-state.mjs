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

  // Locate STRUCTURALLY, not by exact accessible name — login-form field names and the
  // post-login chrome drift across releases (a strict /^username$/ + post-login `searchbox`
  // wait failed live on the old 6.6.10.15 admin, blocking #29). The login form's first
  // textbox is the username; the password is the type=password input.
  const user = page.getByRole('textbox', { name: /user(name)?|e-?mail/i })
    .or(page.getByRole('textbox').first());
  await user.first().waitFor({ state: 'visible', timeout: 60_000 });
  await user.first().fill(process.env.SW_ADMIN_USER ?? 'admin');
  await page.getByRole('textbox', { name: /password/i })
    .or(page.locator('input[type="password"]'))
    .first().fill(process.env.SW_ADMIN_PASS ?? 'shopware');
  await page.getByRole('button', { name: /log ?in|sign ?in|anmelden/i }).first().click();

  // Login succeeded once the username field DETACHES (the SPA leaves the login route) —
  // robust across versions, unlike waiting for a specific post-login control.
  await user.first().waitFor({ state: 'detached', timeout: 90_000 });

  await page.context().storageState({ path: out });
  console.log(`admin storageState saved to ${out}`);
  process.exit(0);
} catch (e) {
  console.error(`admin login failed: ${e.message?.split('\n')[0]}`);
  process.exit(1);
} finally {
  await browser.close();
}
