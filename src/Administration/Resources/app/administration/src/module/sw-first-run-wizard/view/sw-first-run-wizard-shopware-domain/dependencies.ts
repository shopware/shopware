/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-first-run-wizard-shopware-domain needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { firstRunWizardService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
