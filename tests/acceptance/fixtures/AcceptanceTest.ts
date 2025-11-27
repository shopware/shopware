import { mergeTests, test as ShopwareTestSuite } from '@shopware-ag/acceptance-test-suite';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as HomeProducts } from './HomeProducts';
import { test as CurrentsCoverage } from './CurrentsCoverage';
import fs from 'fs';
import path from 'path';
import { test as baseTest } from '@playwright/test';

export * from '@shopware-ag/acceptance-test-suite';

export const test = mergeTests(
  ShopwareTestSuite,
  shopCustomerTasks,
  shopAdminTasks,
  HomeProducts,
  CurrentsCoverage, // may or may not carry fixtures depending on merge implementation & name collisions
);

const OUT = path.resolve(process.cwd(), 'coverage-snapshots');
if (!fs.existsSync(OUT)) fs.mkdirSync(OUT, { recursive: true });

baseTest.afterEach(async ({ context }, testInfo) => {
  try {
    const pages = context.pages();
    const all: Record<string, any> = {};

    for (let i = 0; i < pages.length; i++) {
      const p = pages[i];
      // top-level page coverage
      const pageCov = await p.evaluate(() => (globalThis as any).__coverage__ ?? null).catch(() => null);
      if (pageCov) {
        all[`page-${i}-${encodeURIComponent(p.url())}`] = pageCov;
      }

      // frames inside the page
      for (const f of p.frames()) {
        const fCov = await f.evaluate(() => (globalThis as any).__coverage__ ?? null).catch(() => null);
        if (fCov) {
          all[`page-${i}-frame-${f.name() || 'noframe'}-${encodeURIComponent(f.url())}`] = fCov;
        }
      }
    }

    const outFile = path.join(OUT, `${testInfo.title.replace(/\s+/g, '_')}_${Date.now()}.raw-coverage.json`);
    fs.writeFileSync(outFile, JSON.stringify(all, null, 2));
    console.log(`WROTE coverage snapshot: ${outFile}`);
    // Optional: attach to Playwright test info
    try {
      // testInfo.attachments exists but typing differs; best-effort
      (testInfo as any).attachments = (testInfo as any).attachments || [];
      (testInfo as any).attachments.push({ name: 'coverage-snapshot', path: outFile, contentType: 'application/json' });
    } catch (e) { /* ignore attachment failure */ }
  } catch (err) {
    console.warn('coverage collector failed', err);
  }
});