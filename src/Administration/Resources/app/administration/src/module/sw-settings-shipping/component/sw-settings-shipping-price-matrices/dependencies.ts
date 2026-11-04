/**
 * @sw-package checkout
 *
 * Everything the price matrices need from outside their mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { repositoryFactory as repositoryFactoryService, ruleConditionDataProviderService } from 'src/app/service-names';

/** The shipping method being edited and its price groups, shared with the shipping detail page. @private */
export { useSwShippingDetailStore as useShippingDetailStore } from '../../page/sw-settings-shipping-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;
