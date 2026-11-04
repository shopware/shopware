/**
 * @sw-package framework
 */
import * as SalutationMixin from './index';

Shopware.Mixin.register('salutation', SalutationMixin.default);
Shopware._private.publish('mixins', 'salutation', SalutationMixin);
