/**
 * @sw-package checkout
 *
 * Everything the price matrix needs from outside its mini-module.
 */
import { swShippingDetailStoreId } from '../../page/sw-settings-shipping-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** The shipping method being edited and its currencies, shared with the shipping detail page. @private */
export const useShippingDetailStore = () => Shopware.Store.get(swShippingDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
