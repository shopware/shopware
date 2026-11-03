/**
 * @sw-package framework
 *
 * Everything sw-login-recovery needs from outside its mini-module.
 */
import { loginService, userRecoveryService } from 'src/app/service-names';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { validationApiService } from 'src/app/service-names';

/** @private */
export const getLoginService = () => Shopware.Service(loginService);

/** @private */
export const getUserRecoveryService = () => Shopware.Service(userRecoveryService);

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
