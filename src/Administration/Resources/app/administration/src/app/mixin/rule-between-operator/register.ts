/**
 * @sw-package fundamentals@after-sales
 */
import * as RuleBetweenOperatorMixin from './index';

Shopware.Mixin.register('rule-between-operator', RuleBetweenOperatorMixin.default);
Shopware._private.publish('mixins', 'rule-between-operator', RuleBetweenOperatorMixin);
