/**
 * @sw-package framework
 */
import * as SwFormFieldMixin from './index';

Shopware.Mixin.register('sw-form-field', SwFormFieldMixin.default);
Shopware._private.publish('mixins', 'sw-form-field', SwFormFieldMixin);
