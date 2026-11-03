/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-config-product-listing needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

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
export const set = ((...args: unknown[]) =>
    (Shopware.Utils.object.set as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.set;

/** @private */
export const unset = ((...args: unknown[]) =>
    (Shopware.Utils.object.unset as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.unset;
