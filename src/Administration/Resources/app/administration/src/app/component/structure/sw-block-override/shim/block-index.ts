/**
 * @sw-package framework
 * @private
 *
 * Thin re-export of the core block-index so that files inside the shim folder
 * (including the spec) can import from `./block-index` without depending
 * directly on the core layer path.
 *
 * Also provides `__resetShimStateForTesting`, which clears ALL module-level
 * shim state (core block index + app-layer slot caches). Call it in
 * `afterEach` inside tests — never in production code.
 */

import { resetBlockIndex } from 'src/core/factory/twig-block-index';
import { resetShimSlotState } from './create-shim-slot';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export {
    indexTwigBlocksFromTemplate,
    getBlockEntries,
    hasBlockEntries,
    resetBlockIndex,
} from 'src/core/factory/twig-block-index';
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { BlockEntry } from 'src/core/factory/twig-block-index';

/**
 * @private
 */
export function __resetShimStateForTesting(): void {
    resetBlockIndex();
    resetShimSlotState();
}
