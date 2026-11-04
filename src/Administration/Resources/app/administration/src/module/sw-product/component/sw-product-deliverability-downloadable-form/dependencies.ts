/**
 * @sw-package inventory
 *
 * Everything sw-product-deliverability-downloadable-form needs from outside its mini-module.
 */

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const pick = ((...args: unknown[]) =>
    (Shopware.Utils.object.pick as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.pick;
