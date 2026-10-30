/**
 * @sw-package checkout
 *
 * Everything the price matrix needs from outside its mini-module.
 */
import { swShippingDetailStoreId } from '../../page/sw-settings-shipping-detail/store';

export { default as Criteria } from 'src/core/data/criteria.data';
export { cloneDeep } from 'src/core/service/utils/object.utils';
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** The shipping method being edited and its currencies, shared with the shipping detail page. */
export const useShippingDetailStore = () => Shopware.Store.get(swShippingDetailStoreId);

export const getApiContext = () => Shopware.Context.api;

export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
