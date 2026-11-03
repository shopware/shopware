/**
 * @sw-package checkout
 *
 * Everything sw-order-promotion-tag-field needs from outside its mini-module.
 */
/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;
