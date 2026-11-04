/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-base needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
