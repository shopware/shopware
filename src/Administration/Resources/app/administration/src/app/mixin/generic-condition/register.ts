/**
 * @sw-package framework
 */
import * as GenericConditionMixin from './index';

Shopware.Mixin.register('generic-condition', GenericConditionMixin.default);
Shopware._private.publish('mixins', 'generic-condition', GenericConditionMixin);
