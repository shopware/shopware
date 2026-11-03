/**
 * @sw-package framework
 *
 * Everything sw-inactivity-login needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { feature as featureService, loginService } from 'src/app/service-names';

/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;
