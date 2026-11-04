/**
 * @sw-package framework
 */
import * as PlaceholderMixin from './index';

Shopware.Mixin.register('placeholder', PlaceholderMixin.default);
Shopware._private.publish('mixins', 'placeholder', PlaceholderMixin);
