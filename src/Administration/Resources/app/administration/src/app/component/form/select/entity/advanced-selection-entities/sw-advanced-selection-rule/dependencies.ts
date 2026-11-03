/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-advanced-selection-rule needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { feature as featureService, ruleConditionDataProviderService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
