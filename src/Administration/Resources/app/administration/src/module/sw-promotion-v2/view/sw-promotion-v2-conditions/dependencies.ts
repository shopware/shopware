/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-conditions needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
} from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
