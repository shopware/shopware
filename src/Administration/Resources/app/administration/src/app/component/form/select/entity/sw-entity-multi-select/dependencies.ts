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
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);

/** @private */
export const get = (...args: Parameters<typeof Shopware.Utils.get>) => Shopware.Utils.get(...args);
