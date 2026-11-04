/**
 * @sw-package framework
 */
import * as RemoveApiErrorMixin from './index';

Shopware.Mixin.register('remove-api-error', RemoveApiErrorMixin.default);
Shopware._private.publish('mixins', 'remove-api-error', RemoveApiErrorMixin);
