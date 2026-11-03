/**
 * @sw-package checkout
 *
 * Everything sw-customer-imitate-customer-modal needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ApiService } from 'src/core/service/api.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { contextStoreService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
