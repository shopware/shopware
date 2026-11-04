/**
 * @sw-package checkout
 *
 * Everything sw-settings-payment-overview needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { paymentOverviewCardStoreId } from '../../store/overview-cards.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { PaymentOverviewCard } from '../../store/overview-cards.store';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const usePaymentOverviewCardStore = () => Shopware.Store.get(paymentOverviewCardStoreId);
