/**
 * @sw-package framework
 *
 * Writes the `shopware:*` module registry and its ambient declarations.
 *
 *     composer admin:generate-shopware-modules
 *
 * Both outputs are checked in, so adding a utility, DAL class, mixin, or store shows the new specifier in
 * the pull request diff. `meta.spec.js` fails when they drift from the sources.
 */

import fs from 'node:fs';
import path from 'node:path';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations } from './render-declarations';

const administrationRoot = path.resolve(__dirname, '../..');

export const REGISTRY_FILE = path.join(administrationRoot, 'shopware-modules.json');
export const DECLARATIONS_FILE = path.join(administrationRoot, 'src/shopware-virtual-modules.d.ts');

/** Serialises the registry the way the checked-in file stores it. */
export function renderRegistry(administrationRoot: string): string {
    return `${JSON.stringify(extractModuleRegistry(administrationRoot), null, 4)}\n`;
}

function main(): void {
    fs.writeFileSync(REGISTRY_FILE, renderRegistry(administrationRoot));
    fs.writeFileSync(DECLARATIONS_FILE, renderDeclarations(extractModuleRegistry(administrationRoot)));

    // eslint-disable-next-line no-console
    console.log(
        `Wrote ${path.relative(administrationRoot, REGISTRY_FILE)} and ${path.relative(administrationRoot, DECLARATIONS_FILE)}.`,
    );
}

if (require.main === module) {
    main();
}
