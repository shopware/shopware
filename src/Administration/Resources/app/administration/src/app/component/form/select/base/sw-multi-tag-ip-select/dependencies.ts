/**
 * @sw-package framework
 *
 * Everything sw-multi-tag-ip-select needs from outside its mini-module.
 */
/** @private */
export const isValidIp = ((...args: unknown[]) =>
    (Shopware.Utils.string.isValidIp as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.isValidIp;
