/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-location-renderer needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';
/** @private */
export type { ElementDataProp } from '../index';

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
