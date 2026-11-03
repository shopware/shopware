/**
 * @sw-package checkout
 *
 * Everything sw-settings-shipping-tax-cost needs from outside its mini-module.
 */
import { swShippingDetailStoreId } from '../../page/sw-settings-shipping-detail/store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useShippingDetailStore = () => Shopware.Store.get(swShippingDetailStoreId);
