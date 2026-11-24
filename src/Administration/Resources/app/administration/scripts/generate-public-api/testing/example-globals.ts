/**
 * @sw-package framework
 *
 * Small source trees for the specs about the `Shopware` object: `src/core/shopware.ts` with the class the
 * object is an instance of, given by the members of that class.
 */
import type { SourceTree } from '../source/source-tree';
import { treeOf } from './example-components';

/** @private */
export const SHOPWARE_FILE = 'src/core/shopware.ts';

/** @private */
export const GLOBAL_REPORT = 'core/shopware.api.md';

/**
 * The file that builds the `Shopware` object, with these members of its class and what stands before the class.
 */
function shopwareFile(members: string, before = ''): string {
    return [
        before,
        `class ShopwareClass {\n${members}\n}`,
        'const ShopwareInstance = new ShopwareClass();',
        'export { ShopwareClass, ShopwareInstance };',
    ].join('\n');
}

/**
 * @private
 *
 * A tree whose `Shopware` object has these members. `files` adds to the tree.
 */
export function shopwareTree(members: string, files: Record<string, string> = {}, before = ''): SourceTree {
    return treeOf({ [SHOPWARE_FILE]: shopwareFile(members, before), ...files });
}
