import fs from 'node:fs';
import { FILES } from '../../bundle.ts';
import type { Plan } from '../../types.ts';

/**
 * Resolves the generated PHP test and Shopware checkout location for the direct executor.
 *
 * The runner can then focus on PHPUnit mechanics while the executor still records the authored
 * source as evidence even when the file later turns out to be missing.
 */
export function prepareDirectSpec({ plan }: { plan: Partial<Plan> }): { specPath: string; shop: string; spec: string } {
  const specPath = plan.script_path || FILES.testPhp;
  const shop = process.env.SHOP_DIR || (fs.existsSync('vendor/bin/phpunit') ? '.' : 'shop');
  const spec = fs.existsSync(specPath) ? fs.readFileSync(specPath, 'utf8') : '';

  return { specPath, shop, spec };
}
