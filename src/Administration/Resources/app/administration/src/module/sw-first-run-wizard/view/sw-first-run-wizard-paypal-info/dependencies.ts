/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-first-run-wizard-paypal-info needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { extensionStoreActionService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
