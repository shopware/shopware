/**
 * @sw-package framework
 *
 * Everything sw-grid needs from outside its mini-module.
 */
/** @private */
export { computed } from 'vue';

/** @private */
export const error = ((...args: unknown[]) =>
    (Shopware.Utils.debug.error as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.error;

/** @private */
export const getScrollbarWidth = ((...args: unknown[]) =>
    (Shopware.Utils.dom.getScrollbarWidth as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.getScrollbarWidth;
