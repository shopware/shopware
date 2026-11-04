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
 * A private mixin module is imported through its `private.ts`, on a line that says why the boundary rule allows it.
 *
 * @example
 * // mixins: [Mixin.getByName('notification')]
 * // → mixins: [notificationMixin]
 * // dependencies.ts: export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
 */
const PRIVATE_MIXIN_REASON = 'the mixin is private, extensions reach it with Mixin.getByName()';

export const rewriteMixin: UseRewrite = (use, context) => {
    const name = literalArgument(use.call);
    const source = name === null || !isPath(use, 'Mixin', 'getByName') ? null : context.index.findMixin(name);
    if (!use.call || !name || !source) {
        return null;
    }

    const exported = `${camelCase(name)}Mixin`;
    if (!context.isFree(exported)) {
        return null;
    }

    return replaceNode(use.call, context, exported, [
        {
            kind: 'reexport',
            module: source.module,
            imported: 'default',
            exported,
            ...(source.isPrivate ? { privateReason: PRIVATE_MIXIN_REASON } : {}),
        },
    ]);
};
