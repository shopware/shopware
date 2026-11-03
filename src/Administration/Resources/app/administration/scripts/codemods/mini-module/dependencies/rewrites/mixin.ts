/**
 * @sw-package framework
 * @private
 */

import type { UseRewrite } from '../model';
import { camelCase } from '../model';
import { isPath, literalArgument, replaceNode } from './helpers';

/**
 * `Mixin.getByName('x')` becomes the default export of the file that registers the mixin, in the same place,
 * so the order of `mixins` stays. A mixin cannot be registered twice, so both are the same object.
 *
 * @example
 * // mixins: [Mixin.getByName('notification')]
 * // → mixins: [notificationMixin]
 * // dependencies.ts: export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
 */
export const rewriteMixin: UseRewrite = (use, context) => {
    const name = literalArgument(use.call);
    const module = name === null || !isPath(use, 'Mixin', 'getByName') ? null : context.index.findMixin(name);
    if (!use.call || !name || !module) {
        return null;
    }

    const exported = `${camelCase(name)}Mixin`;
    if (!context.isFree(exported)) {
        return null;
    }

    return replaceNode(use.call, context, exported, [
        { kind: 'reexport', module, imported: 'default', exported },
    ]);
};
