/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-line-item-custom-field needs from outside its mini-module.
 */
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { RULE_BETWEEN_OPERATOR_MIXIN_NAME } from 'src/app/mixin/rule-between-operator';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet';

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
