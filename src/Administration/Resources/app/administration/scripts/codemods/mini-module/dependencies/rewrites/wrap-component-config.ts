/**
 * @sw-package framework
 * @private
 */

import { isWritten } from '../global-uses';
import type { UseRewrite } from '../model';
import { isPath, replaceNode } from './helpers';

/**
 * `Shopware.Component.wrapComponentConfig` is Vue's `defineComponent`, so a TypeScript component takes it from there.
 *
 * @example
 * // export default Shopware.Component.wrapComponentConfig({ … });
 * // → export default wrapComponentConfig({ … });
 * // dependencies.ts: export { defineComponent as wrapComponentConfig } from 'vue';
 */
export const rewriteWrapComponentConfig: UseRewrite = (use, context) => {
    if (!isPath(use, 'Component', 'wrapComponentConfig') || isWritten(use.node) || !context.isFree('wrapComponentConfig')) {
        return null;
    }

    return replaceNode(use.node, context, 'wrapComponentConfig', [
        { kind: 'reexport', module: 'vue', imported: 'defineComponent', exported: 'wrapComponentConfig' },
    ]);
};
