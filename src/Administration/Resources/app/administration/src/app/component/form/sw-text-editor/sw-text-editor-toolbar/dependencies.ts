/**
 * @sw-package framework
 *
 * Everything sw-text-editor-toolbar needs from outside its mini-module.
 */
/** @private */
export const throttle = ((...args: unknown[]) =>
    (Shopware.Utils.throttle as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.throttle;
