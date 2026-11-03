/**
 * @sw-package framework
 *
 * Everything sw-simple-search-field needs from outside its mini-module.
 */
/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);
