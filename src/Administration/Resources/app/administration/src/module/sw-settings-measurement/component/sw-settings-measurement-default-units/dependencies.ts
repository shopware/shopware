/**
 * @sw-package inventory
 *
 * Everything sw-settings-measurement-default-units needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
