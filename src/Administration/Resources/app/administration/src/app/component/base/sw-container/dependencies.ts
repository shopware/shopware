/**
 * @sw-package framework
 *
 * Everything sw-container needs from outside its mini-module.
 */
/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
