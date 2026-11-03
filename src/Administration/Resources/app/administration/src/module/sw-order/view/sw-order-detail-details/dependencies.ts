/**
 * @sw-package checkout
 *
 * Everything sw-order-detail-details needs from outside its mini-module.
 */
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
