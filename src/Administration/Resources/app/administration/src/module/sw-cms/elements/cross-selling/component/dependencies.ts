/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-cross-selling needs from outside its mini-module.
 */
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
