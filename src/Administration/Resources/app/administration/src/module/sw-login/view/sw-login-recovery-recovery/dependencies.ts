/**
 * @sw-package framework
 *
 * Everything sw-login-recovery-recovery needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { userRecoveryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
