/**
 * @sw-package checkout
 *
 * Everything sw-extension-my-extensions-index needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
