/**
 * @sw-package framework
 *
 * Everything sw-avatar needs from outside its mini-module.
 */
/** @private */
export const cloneDeep = (...args: Parameters<typeof Shopware.Utils.object.cloneDeep>) =>
    Shopware.Utils.object.cloneDeep(...args);
