/**
 * @sw-package framework
 *
 * Everything sw-login-login needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as getErrorCode } from 'src/core/data/error-codes/login.error-codes';
/** @private */
export { licenseViolationService, loginService, userService } from 'src/app/service-names';
/** @private */
export type { LoginConfig } from '../../../../core/service/login.service';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
