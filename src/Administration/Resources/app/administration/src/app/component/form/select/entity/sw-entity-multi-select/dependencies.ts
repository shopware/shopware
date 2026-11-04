/**
 * @sw-package framework
 *
 * Everything sw-entity-multi-select needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;
