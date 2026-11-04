/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-first-run-wizard-mailer-selection needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { systemConfigApiService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
