/**
 * @sw-package inventory
 *
 * Everything sw-product-measurement-form needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const convert = ((...args: unknown[]) =>
    (Shopware.Utils.unitConversion.convert as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.unitConversion.convert;
