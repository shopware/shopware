/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-order-custom-field needs from outside its mini-module.
 */
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { RULE_BETWEEN_OPERATOR_MIXIN_NAME } from 'src/app/mixin/rule-between-operator.mixin';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
