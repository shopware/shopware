/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-modal-grid needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
