/**
 * @sw-package framework
 *
 * Everything sw-snippet-field needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { repositoryFactory as repositoryFactoryService, snippetSetService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
