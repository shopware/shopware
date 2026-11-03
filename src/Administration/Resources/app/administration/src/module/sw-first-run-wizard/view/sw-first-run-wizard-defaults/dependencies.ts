/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-first-run-wizard-defaults needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
