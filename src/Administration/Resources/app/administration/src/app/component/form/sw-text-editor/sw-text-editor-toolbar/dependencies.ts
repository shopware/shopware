/**
 * @sw-package framework
 *
 * Everything sw-text-editor-toolbar needs from outside its mini-module.
 */
/** @private */
export const throttle = (...args: Parameters<typeof Shopware.Utils.throttle>) => Shopware.Utils.throttle(...args);
