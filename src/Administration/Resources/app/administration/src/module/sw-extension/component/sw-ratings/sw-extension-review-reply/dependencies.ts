/**
 * @sw-package checkout
 *
 * Everything sw-extension-review-reply needs from outside its mini-module.
 */
/** @private */
export const date = ((...args: unknown[]) =>
    (Shopware.Utils.format.date as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.date;
