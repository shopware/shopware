/**
 * @sw-package discovery
 *
 * Everything sw-cms-preview-video needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
