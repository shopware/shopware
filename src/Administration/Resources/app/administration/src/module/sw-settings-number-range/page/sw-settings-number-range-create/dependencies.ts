/**
 * @sw-package inventory
 *
 * Everything sw-settings-number-range-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as Utils } from 'src/core/service/util.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
