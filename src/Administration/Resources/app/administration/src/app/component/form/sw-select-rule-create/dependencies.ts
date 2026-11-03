/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-select-rule-create needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export {
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
} from 'src/app/service-names';
