/**
 * @sw-package discovery
 *
 * Everything sw-cms-product-box-preview needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
