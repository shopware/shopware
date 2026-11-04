/**
 * @sw-package checkout
 *
 * Everything sw-customer-convert-guest-modal needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { guestCustomerConvertService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
