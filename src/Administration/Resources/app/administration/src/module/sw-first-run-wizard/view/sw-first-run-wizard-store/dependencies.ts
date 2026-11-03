/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-first-run-wizard-store needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { extensionHelperService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
