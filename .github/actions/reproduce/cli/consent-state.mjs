// Pre-accept the Storefront "technically required" cookie consent → Playwright storageState, so an
// ordinary Storefront repro isn't blocked by the consent banner. Bugs that ARE about the consent
// flow set browser_state.auto_cookie_consent=false and get no pre-seeded state.
//
// Usage: node consent-state.mjs <APP_URL> [out-state.json]
import fs from 'node:fs';

const [appUrlArg, out = 'storefront-state.json'] = process.argv.slice(2);
if (!appUrlArg) { console.error('usage: consent-state.mjs <APP_URL> [out.json]'); process.exit(2); }

const appUrl = new URL(appUrlArg);

// The banner is considered answered when cookie-config-hash matches the shop's current groups hash.
let cookieConfigHash = '{}';
try {
  const res = await fetch(new URL('/cookie/groups', appUrl), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: AbortSignal.timeout(3000) });
  if (res.ok) {
    const data = await res.json();
    if (data?.languageId && data?.hash) cookieConfigHash = JSON.stringify({ [data.languageId]: data.hash });
  }
} catch { /* leave the empty hash; the banner may reappear but the run still proceeds */ }

const expires = Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 30;
const cookie = (name, value) => ({ name, value, domain: appUrl.hostname, path: '/', expires, httpOnly: false, secure: appUrl.protocol === 'https:', sameSite: 'Lax' });

fs.writeFileSync(out, `${JSON.stringify({ cookies: [cookie('cookie-preference', '1'), cookie('cookie-config-hash', cookieConfigHash)], origins: [] }, null, 2)}\n`);
fs.chmodSync(out, 0o600);
