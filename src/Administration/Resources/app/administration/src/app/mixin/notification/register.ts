/**
 * @sw-package framework
 */
import * as NotificationMixin from './index';

Shopware.Mixin.register('notification', NotificationMixin.default);
Shopware._private.publish('mixins', 'notification', NotificationMixin);
