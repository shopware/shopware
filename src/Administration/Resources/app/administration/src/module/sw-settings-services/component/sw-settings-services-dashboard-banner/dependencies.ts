/**
 * @sw-package framework
 *
 * Everything sw-settings-services-dashboard-banner needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { userConfigService } from 'src/app/service-names';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
