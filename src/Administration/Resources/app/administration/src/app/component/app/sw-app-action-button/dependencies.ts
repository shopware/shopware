/**
 * @sw-package framework
 *
 * Everything sw-app-action-button needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
