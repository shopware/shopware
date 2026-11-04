/**
 * @sw-package framework
 */
import * as PositionMixin from './index';

Shopware.Mixin.register('position', PositionMixin.default);
Shopware._private.publish('mixins', 'position', PositionMixin);
