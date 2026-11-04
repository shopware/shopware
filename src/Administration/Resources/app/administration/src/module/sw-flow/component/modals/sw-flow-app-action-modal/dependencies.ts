/**
 * @sw-package after-sales
 *
 * Everything sw-flow-app-action-modal needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
