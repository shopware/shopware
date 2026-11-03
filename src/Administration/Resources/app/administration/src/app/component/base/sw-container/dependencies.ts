/**
 * @sw-package framework
 *
 * Everything sw-container needs from outside its mini-module.
 */
/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
