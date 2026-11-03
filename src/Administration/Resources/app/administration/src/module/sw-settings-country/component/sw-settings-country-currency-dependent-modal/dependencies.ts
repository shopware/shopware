/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-country-currency-dependent-modal needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
