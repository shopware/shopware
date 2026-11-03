/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-settings-rule-detail-assignments needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { get, set } from 'src/core/service/utils/object.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
} from 'src/app/service-names';
/** @private */
export { default as RuleAssignmentConfigurationService } from 'src/module/sw-settings-rule/service/rule-assignment-configuration.service';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
