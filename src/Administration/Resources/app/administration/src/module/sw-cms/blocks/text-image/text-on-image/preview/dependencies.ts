/**
 * @sw-package discovery
 *
 * Everything sw-cms-preview-text-on-image needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
