/**
 * @sw-package framework
 *
 * Everything sw-simple-search-field needs from outside its mini-module.
 */
/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
