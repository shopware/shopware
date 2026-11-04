/**
 * @sw-package inventory
 *
 * Everything advanced-mode needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const cloneDeep = <Value>(value: Value): Value => Shopware.Utils.object.cloneDeep(value);
