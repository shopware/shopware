import fs from 'node:fs';
import { FILES } from '../../bundle.ts';
import type { Plan } from '../../types.ts';

/**
 * Resolves the generated PHP test and Shopware checkout location for the direct executor.
 *
 * The runner can then focus on PHPUnit mechanics while the executor still records the authored
 * source as evidence even when the file later turns out to be missing.
 */
export function prepareDirectSpec({ plan: _plan }: { plan: Partial<Plan> }): { specPath: string; shop: string; spec: string } {
  // Pin to the per-executor default file; deliberately ignore plan.script_path. The value is read off
  // the host FS here (host-side, before any container) and its bytes land in evidence.script, so an
  // agent-injected path (e.g. an env/secret file) would be read and could surface in the public
  // comment. validate.ts already rejects a non-default script_path, but that gate is advisory — this
  // makes the arbitrary read impossible by construction rather than relying on the gate upstream.
  const specPath = FILES.testPhp;
  const shop = process.env.SHOP_DIR || (fs.existsSync('vendor/bin/phpunit') ? '.' : 'shop');
  const spec = fs.existsSync(specPath) ? fs.readFileSync(specPath, 'utf8') : '';

  return { specPath, shop, spec };
}
