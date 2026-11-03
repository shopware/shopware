/**
 * @sw-package discovery
 *
 * Everything sw-cms-list-item needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
