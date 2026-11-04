/**
 * @sw-package framework
 */
import * as ValidationMixin from './index';

Shopware.Mixin.register('validation', ValidationMixin.default);
Shopware._private.publish('mixins', 'validation', ValidationMixin);
