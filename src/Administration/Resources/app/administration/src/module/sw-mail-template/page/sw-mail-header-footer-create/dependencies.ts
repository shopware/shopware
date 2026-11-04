/**
 * @sw-package after-sales
 *
 * Everything sw-mail-header-footer-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
