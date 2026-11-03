/**
 * @sw-package framework
 *
 * Everything sw-grid needs from outside its mini-module.
 */
/** @private */
export { computed } from 'vue';

/** @private */
export const error = (...args: Parameters<typeof Shopware.Utils.debug.error>) => Shopware.Utils.debug.error(...args);

/** @private */
export const getScrollbarWidth = (...args: Parameters<typeof Shopware.Utils.dom.getScrollbarWidth>) =>
    Shopware.Utils.dom.getScrollbarWidth(...args);
