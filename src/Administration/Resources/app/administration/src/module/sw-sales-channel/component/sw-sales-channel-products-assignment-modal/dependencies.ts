/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-products-assignment-modal needs from outside its mini-module.
 */
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const uniqBy = ((...args: unknown[]) =>
    (Shopware.Utils.array.uniqBy as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.uniqBy;
