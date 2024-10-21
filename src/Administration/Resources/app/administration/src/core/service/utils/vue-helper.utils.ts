/**
 * @private
 *
 * These helper methods shouldn't be used.
 * They are only needed used for internal purposes.
 */
import type { VNode } from 'vue';
import { getCurrentInstance } from 'vue';
import type { ComponentPublicInstance } from '@vue/runtime-core';

function getCompatChildren() {
    const instance = getCurrentInstance();

    const root = instance?.subTree;
    const children: ComponentPublicInstance[] = [];
    if (root) {
        walk(root, children);
    }
    return children;
}

function walk(vnode: VNode, children: ComponentPublicInstance[]) {
    if (vnode.component) {
        children.push(vnode.component.proxy!);
        // eslint-disable-next-line no-bitwise
    } else if (vnode.shapeFlag & 16) {
        const vnodes = vnode.children as VNode[];

        // eslint-disable-next-line no-plusplus
        for (let i = 0; i < vnodes.length; i++) {
            walk(vnodes[i], children);
        }
    }
}

/**
 * @private
 */
export default {
    getCompatChildren,
};
