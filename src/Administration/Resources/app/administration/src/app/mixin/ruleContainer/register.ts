/**
 * @sw-package framework
 */
import * as RuleContainerMixin from './index';

Shopware.Mixin.register('ruleContainer', RuleContainerMixin.default);
Shopware._private.publish('mixins', 'ruleContainer', RuleContainerMixin);
