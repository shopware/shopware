/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-settings-rule-detail-base needs from outside its mini-module.
 */
/** @private */
export { acl as aclService, customFieldDataProviderService, ruleConditionDataProviderService } from 'src/app/service-names';
/** @private */
export { PRODUCT_STREAM_CONDITIONS } from '../../constant/sw-settings-rule.constant';

/** @private */
export const getAppContext = () => Shopware.Context.app;
