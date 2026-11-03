/**
 * @sw-package framework
 *
 * Everything sw-admin needs from outside its mini-module.
 */
/** @private */
export type { Toast } from '@shopware-ag/meteor-component-library/dist/esm/MtToast';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { feature as featureService, loginService, userActivityService } from 'src/app/service-names';

/** @private */
export const createId = (...args: Parameters<typeof Shopware.Utils.createId>) => Shopware.Utils.createId(...args);

/** @private */
export const handle = (...args: Parameters<typeof Shopware.ExtensionAPI.handle>) => Shopware.ExtensionAPI.handle(...args);

/** @private */
export const hasOwnProperty = (...args: Parameters<typeof Shopware.Utils.object.hasOwnProperty>) =>
    Shopware.Utils.object.hasOwnProperty(...args);
