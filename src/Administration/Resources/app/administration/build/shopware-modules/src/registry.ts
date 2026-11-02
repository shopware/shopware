/**
 * @sw-package framework
 */

import { fs, path } from '../dependencies';
import type { ModuleRegistry } from './definitions';

/** @private The registry file, relative to the Administration root. */
export const REGISTRY_FILE = 'shopware-modules.json';

/**
 * @private
 *
 * The checked-in registry of every `shopware:*` specifier.
 *
 * The build reads this rather than parsing the Administration sources, so adding a utility, mixin or
 * store shows up as a diff in a pull request instead of silently changing what resolves.
 * `composer admin:generate-shopware-modules` rewrites it.
 */
export function readRegistry(administrationRoot: string): ModuleRegistry {
    const file = path.join(administrationRoot, REGISTRY_FILE);

    return JSON.parse(fs.readFileSync(file, 'utf8')) as ModuleRegistry;
}
