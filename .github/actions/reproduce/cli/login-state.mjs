// Deterministic Admin login → Playwright storageState. Login is HARNESS work, not repro work:
// generated admin specs kept fumbling the login preamble (strict-mode label matches, un-waited
// visibility). We do it once here with proven, version-tolerant locators and hand every admin spec
// an authenticated session, so specs navigate straight to the route under test.
//
// Usage: node login-state.mjs <APP_URL> <out-state.json>   (exit 0 = state saved)
import { chromium } from '@playwright/test';

const [appUrl, out] = process.argv.slice(2);
if (!appUrl || !out) { console.error('usage: login-state.mjs <APP_URL> <out.json>'); process.exit(2); }
const user = process.env.SW_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin';
const pass = process.env.SW_ADMIN_PASS ?? process.env.ADMIN_PASS ?? 'shopware';

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

  // The authenticated shell is up once the global search renders (stable across Admin versions).
  await page.getByRole('searchbox')
    .or(page.locator('.sw-search-bar, .sw-admin-menu, .sw-desktop').first()).first()
    .waitFor({ state: 'visible', timeout: 90_000 });

  await page.context().storageState({ path: out });
  console.log(`admin storageState saved to ${out}`);
  process.exit(0);
} catch (err) {
  console.error(`admin login failed: ${err.message?.split('\n')[0]}`);
  process.exit(1);
} finally {
  await browser.close();
}
