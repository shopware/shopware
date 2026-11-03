/**
 * @sw-package framework
 *
 * Everything sw-dynamic-url-field needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export type { default as RepositoryType } from 'src/core/data/repository.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
