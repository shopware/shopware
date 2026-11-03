/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-not-found needs from outside its mini-module.
 */
/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
