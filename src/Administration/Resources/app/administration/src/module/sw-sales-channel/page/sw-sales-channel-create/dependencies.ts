/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { systemConfigApiService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
