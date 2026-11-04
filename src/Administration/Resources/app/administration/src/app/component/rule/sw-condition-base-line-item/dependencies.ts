/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-base-line-item needs from outside its mini-module.
 */
import { ruleConditionsConfigStoreId } from 'src/app/store/rule-conditions-config';

/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useRuleConditionsConfigStore = () => Shopware.Store.get(ruleConditionsConfigStoreId);
