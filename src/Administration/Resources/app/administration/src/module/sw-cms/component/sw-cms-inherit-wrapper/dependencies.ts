/**
 * @sw-package discovery
 *
 * Everything sw-cms-inherit-wrapper needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { cmsService } from 'src/app/service-names';
/** @private */
export { default as cmsStateMixin } from '../../mixin/sw-cms-state.mixin';
/** @private */
export type { CmsSlotConfig, RuntimeSlot } from '../../service/cms.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.object.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.get;

/** @private */
export const has = ((...args: unknown[]) =>
    (Shopware.Utils.object.has as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.has;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const isUndefined = ((...args: unknown[]) =>
    (Shopware.Utils.types.isUndefined as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.types.isUndefined;

/** @private */
export const set = ((...args: unknown[]) =>
    (Shopware.Utils.object.set as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.set;

/** @private */
export const unset = ((...args: unknown[]) =>
    (Shopware.Utils.object.unset as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.unset;
