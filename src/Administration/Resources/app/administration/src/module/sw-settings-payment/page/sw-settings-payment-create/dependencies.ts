/**
 * @sw-package checkout
 *
 * Everything sw-settings-payment-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
