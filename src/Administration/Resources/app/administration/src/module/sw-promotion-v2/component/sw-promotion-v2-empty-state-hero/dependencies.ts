/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-empty-state-hero needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
