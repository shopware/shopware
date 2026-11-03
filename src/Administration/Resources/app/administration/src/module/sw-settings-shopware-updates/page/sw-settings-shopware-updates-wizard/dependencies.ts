/**
 * @sw-package framework
 *
 * Everything sw-settings-shopware-updates-wizard needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { dom } from 'src/core/service/util.service';
/** @private */
export { default as useSession } from 'src/app/composables/use-session';
/** @private */
export { default as useSnackbar } from 'src/app/composables/use-snackbar';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { updateService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
