/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-product-box needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
