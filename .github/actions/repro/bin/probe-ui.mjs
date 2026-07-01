#!/usr/bin/env node
// probe-ui — inspect a LIVE admin/storefront route so the analyze agent authors selectors it
// can actually see, instead of guessing (the blind-selector failure class, e.g. #31's
// getByTitle('Settings') that never matched the real cog). Prints the accessible tree
// (role + name for every interactive control) and saves a screenshot; the agent then anchors
// on names/roles that actually exist.
//
// Usage: node probe-ui.mjs <route> [viewport]
//   <route>    relative admin/storefront path, e.g. '/admin#/sw/cms/detail/<id>' or '/'
//   [viewport] optional 'WIDTHxHEIGHT' (default 1280x800)
// Env: SHOPWARE_BASE_URL (default http://127.0.0.1:8000), PW_STORAGE (admin storageState json).
import { chromium } from '@playwright/test';

const [route = '/', viewport] = process.argv.slice(2);
const base = (process.env.SHOPWARE_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const storageState = process.env.PW_STORAGE || undefined;
const [w, h] = (viewport || '1280x800').split('x').map((n) => Number(n) || 0);

// Roles worth reporting for authoring — the controls specs target.
const INTERESTING = new Set(['button', 'link', 'textbox', 'checkbox', 'radio', 'combobox', 'menuitem', 'tab', 'switch', 'searchbox', 'option', 'listbox', 'menu', 'dialog', 'region', 'heading']);

function collect(node, out, depth = 0) {
  if (!node) return;
  const role = node.role;
  const name = (node.name || '').trim();
  if (INTERESTING.has(role) && (name || role === 'dialog' || role === 'region')) {
    out.push(`${'  '.repeat(Math.min(depth, 6))}${role}${name ? ` "${name}"` : ' (no accessible name)'}`);
  }
  for (const child of node.children || []) collect(child, out, depth + (INTERESTING.has(role) ? 1 : 0));
}

const browser = await chromium.launch();
try {
  const context = await browser.newContext({
    baseURL: base,
    viewport: w && h ? { width: w, height: h } : undefined,
    ...(storageState ? { storageState } : {}),
  });
  const page = await context.newPage();
  await page.goto(route, { waitUntil: 'domcontentloaded' });

  // The admin SPA bootstraps async; wait for SOME interactive control to exist rather than a
  // fixed sleep or networkidle (the admin long-polls). Fall back to a bounded settle.
  await page.getByRole('button').first().waitFor({ state: 'visible', timeout: 30_000 }).catch(() => {});
  await page.waitForTimeout(1500);

  const snapshot = await page.accessibility.snapshot({ interestingOnly: false }).catch(() => null);
  const tree = [];
  if (snapshot) collect(snapshot, tree);

  // Also enumerate buttons explicitly with their accessible names (what specs most often target).
  const buttons = await page.getByRole('button').all();
  const buttonNames = [];
  for (const b of buttons.slice(0, 60)) {
    const n = (await b.getAttribute('aria-label')) || (await b.getAttribute('title')) || (await b.textContent()) || '';
    const vis = await b.isVisible().catch(() => false);
    if (vis) buttonNames.push((n.trim() || '(no accessible name — not reachable by getByRole name)').replace(/\s+/g, ' ').slice(0, 80));
  }

  // Icon/tooltip controls: admin toolbar actions are often <img title="…"> or non-button
  // elements that getByRole('button') MISSES (e.g. the CMS editor Settings cog is
  // <img title="Settings">). Enumerate anything with a title/aria-label so the agent can target
  // it with getByTitle(...)/getByLabel(...) — the reachable locator for these.
  const titled = await page.locator('[title], [aria-label]').all();
  const iconNames = [];
  for (const el of titled.slice(0, 80)) {
    if (!(await el.isVisible().catch(() => false))) continue;
    const title = ((await el.getAttribute('title')) || '').trim();
    const label = ((await el.getAttribute('aria-label')) || '').trim();
    const tag = await el.evaluate((n) => n.tagName.toLowerCase()).catch(() => '?');
    if (title) iconNames.push(`getByTitle('${title.slice(0, 50)}')   <${tag}>`);
    else if (label) iconNames.push(`getByLabel('${label.slice(0, 50)}')   <${tag}>`);
  }

  const shot = process.env.PROBE_SHOT || '/tmp/probe-ui.png';
  await page.screenshot({ path: shot, fullPage: false }).catch(() => {});

  console.log(`# probe-ui ${base}${route}`);
  console.log(`# title: ${await page.title().catch(() => '?')}`);
  console.log(`\n## visible buttons (accessible name — target these with getByRole('button',{name:/…/i})):`);
  console.log([...new Set(buttonNames)].map((n) => ` - ${n}`).join('\n') || ' (none found — route may not have loaded)');
  console.log(`\n## icon/tooltip controls (target with getByTitle/getByLabel — admin toolbar icons are NOT role=button):`);
  console.log([...new Set(iconNames)].map((n) => ` - ${n}`).join('\n') || ' (none)');
  console.log(`\n## accessible tree (interactive roles):`);
  console.log(tree.slice(0, 200).join('\n') || ' (empty)');
  console.log(`\n## screenshot: ${shot}`);
  process.exit(0);
} catch (e) {
  console.error(`probe-ui failed: ${e.message?.split('\n')[0]}`);
  process.exit(1);
} finally {
  await browser.close();
}
