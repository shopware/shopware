/**
 * Creates Storefront cookie-consent Playwright storage state for ordinary Storefront repros.
 *
 * Bugs about the consent flow opt out with `browser_state.auto_cookie_consent=false`; otherwise the
 * harness pre-accepts technically required cookies so the banner does not block the reported surface.
 *
 * Usage: `node consent-state.mjs <APP_URL> [out-state.json]`
 */
import fs from 'node:fs';

const [appUrlArg, out = 'storefront-state.json'] = process.argv.slice(2);
if (!appUrlArg) {
  console.error('usage: consent-state.mjs <APP_URL> [out.json]');
  process.exit(2);
}

const appUrl = new URL(appUrlArg);

/**
 * Storefront JS skips the modal once this hash matches the current cookie-group configuration.
 */
let cookieConfigHash = '{}';
try {
  const res = await fetch(new URL('/cookie/groups', appUrl), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    signal: AbortSignal.timeout(3000),
  });
  if (res.ok) {
    const data = await res.json();
    if (data?.languageId && data?.hash) {
      cookieConfigHash = JSON.stringify({ [data.languageId]: data.hash });
    }
  }
} catch { /* leave the empty hash; the banner may reappear but the run still proceeds */ }

const expires = Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 30;
/**
 * Builds a Playwright storageState cookie for the current storefront host.
 */
const cookie = (name, value) => ({
  name,
  value,
  domain: appUrl.hostname,
  path: '/',
  expires,
  httpOnly: false,
  secure: appUrl.protocol === 'https:',
  sameSite: 'Lax',
});

const storageState = {
  cookies: [
    cookie('cookie-preference', '1'),
    cookie('cookie-config-hash', cookieConfigHash),
  ],
  origins: [],
};

fs.writeFileSync(out, `${JSON.stringify(storageState, null, 2)}\n`);
fs.chmodSync(out, 0o600);
