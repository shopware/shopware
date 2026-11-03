/**
 * @sw-package framework
 *
 * Everything sw-verify-user-modal needs from outside its mini-module.
 */
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { loginService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
