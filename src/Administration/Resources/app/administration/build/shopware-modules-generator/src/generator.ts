/**
 * @sw-package framework
 *
 * Renders the checked-in `shopware:*` registry and declarations from the Administration sources, and writes them.
 */

import { fs, path, REGISTRY_FILE } from '../dependencies';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations } from './render-declarations';

/** @private The declarations file, relative to the Administration root. */
export const DECLARATIONS_FILE = 'src/shopware-virtual-modules.d.ts';

/** @public The content of each generated file, keyed by its path relative to the Administration root. */
export type GeneratedFiles = Record<string, string>;

/**
 * @public
 *
 * Renders `shopware-modules.json` and `src/shopware-virtual-modules.d.ts` from the sources below `administrationRoot`.
 * It only reads, so a spec can compare the result with the checked-in files.
 *
 * @example
 * renderShopwareModules(administrationRoot)['shopware-modules.json'] // → '{\n    "shopware:utils": …'
 */
export function renderShopwareModules(administrationRoot: string): GeneratedFiles {
    const registry = extractModuleRegistry(administrationRoot);

    return {
        [REGISTRY_FILE]: `${JSON.stringify(registry, null, 4)}\n`,
        [DECLARATIONS_FILE]: renderDeclarations(registry),
    };
}

/** @private Rewrites the generated files of the Administration this module belongs to, for `generate.ts`. */
export function writeShopwareModules(): void {
    const administrationRoot = path.resolve(__dirname, '../../..');
    const files = renderShopwareModules(administrationRoot);

    Object.entries(files).forEach(([file, content]) => fs.writeFileSync(path.join(administrationRoot, file), content));

    // eslint-disable-next-line no-console -- the command line reports what it wrote
    console.log(`Wrote ${Object.keys(files).join(' and ')}.`);
}
