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
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;

/** @private */
export const handle = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.handle as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.ExtensionAPI.handle;

/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;
