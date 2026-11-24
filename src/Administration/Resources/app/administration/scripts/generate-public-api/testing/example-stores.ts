/**
 * @sw-package framework
 *
 * Small source trees for the specs about stores: the store `example`, which `src/app/store/example.store.ts`
 * registers, given by what the registration gets.
 */
import type { SourceTree } from '../source/source-tree';
import { treeOf } from './example-components';

/** @private */
export const STORE_FILE = 'src/app/store/example.store.ts';

/** @private */
export const STORE_REPORT = 'app/store/example.store.api.md';

/**
 * @private
 *
 * The file of the example store: what stands before the registration, and the arguments of `register`.
 */
export function storeFile(registerArguments: string, before = ''): string {
    return [
        before,
        `const exampleStore = Shopware.Store.register(${registerArguments});`,
        '',
        'export default exampleStore;',
    ].join('\n');
}

/**
 * @private
 *
 * A tree that registers the store `example` with these arguments of `register`. `files` adds to the tree.
 */
export function storeTree(registerArguments: string, files: Record<string, string> = {}, before = ''): SourceTree {
    return treeOf({ [STORE_FILE]: storeFile(registerArguments, before), ...files });
}

/**
 * @private
 *
 * The same for an options store with these options besides its id.
 */
export function optionsTree(options: string, before = ''): SourceTree {
    return storeTree(`{ id: 'example', ${options} }`, {}, before);
}
