/**
 * @sw-package framework
 * @private
 */

import type { UseRewrite } from '../model';
import { isPath, replaceNode } from './helpers';

/**
 * `Shopware.Feature.isActive(flag)` becomes a function in dependencies.ts, because the flags are set at runtime.
 *
 * @example
 * // if (Shopware.Feature.isActive('V6_8_0_0')) { … }
 * // → if (isFeatureActive('V6_8_0_0')) { … }
 * // dependencies.ts: export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
 */
export const rewriteFeature: UseRewrite = (use, context) => {
    if (use.call?.arguments.length !== 1 || !isPath(use, 'Feature', 'isActive') || !context.isFree('isFeatureActive')) {
        return null;
    }

    return replaceNode(use.node, context, 'isFeatureActive', [
        {
            kind: 'accessor',
            name: 'isFeatureActive',
            order: 4,
            body: '(flag: string) => Shopware.Feature.isActive(flag)',
            imports: [],
        },
    ]);
};
