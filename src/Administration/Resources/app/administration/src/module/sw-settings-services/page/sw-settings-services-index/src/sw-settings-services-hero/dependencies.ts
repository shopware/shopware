/**
 * @sw-package framework
 *
 * Everything sw-settings-services-hero needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineAsyncComponent, defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
