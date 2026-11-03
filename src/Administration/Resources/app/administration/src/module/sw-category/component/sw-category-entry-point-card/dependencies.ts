/**
 * @sw-package discovery
 *
 * Everything sw-category-entry-point-card needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
