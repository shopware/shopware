/**
 * @sw-package discovery
 *
 * Everything sw-cms-reset-inheritance needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as cmsStateMixin } from '../../mixin/sw-cms-state.mixin';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const merge = ((...args: unknown[]) =>
    (Shopware.Utils.object.merge as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.merge;

/** @private */
export const set = ((...args: unknown[]) =>
    (Shopware.Utils.object.set as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.set;
