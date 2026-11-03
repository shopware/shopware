/**
 * @sw-package framework
 *
 * Everything sw-avatar needs from outside its mini-module.
 */
/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
