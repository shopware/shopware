/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-generic needs from outside its mini-module.
 */
/** @private */
export { default as genericConditionMixin } from 'src/app/mixin/generic-condition.mixin';

/** @private */
export const getPlaceholderSnippet = ((...args: unknown[]) =>
    (Shopware.Utils.genericRuleCondition.getPlaceholderSnippet as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.genericRuleCondition.getPlaceholderSnippet;
