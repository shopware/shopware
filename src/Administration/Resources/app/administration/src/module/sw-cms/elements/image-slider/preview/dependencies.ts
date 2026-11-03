/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-preview-image-slider needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
