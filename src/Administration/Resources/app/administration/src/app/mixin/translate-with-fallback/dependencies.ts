/**
 * @sw-package framework
 *
 * Everything translate-with-fallback mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';

/** @private */
export const getAppContext = () => Shopware.Context.app;
