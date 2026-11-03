/**
 * @sw-package framework
 *
 * Everything sw-popover needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
