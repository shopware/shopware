/**
 * @sw-package framework
 *
 * Everything sw-login needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { LoginConfig } from '../../../../core/service/login.service';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
