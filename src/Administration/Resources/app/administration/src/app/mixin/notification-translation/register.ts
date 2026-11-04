/**
 * @sw-package framework
 */
import * as NotificationTranslationMixin from './index';

Shopware.Mixin.register('notification-translation', NotificationTranslationMixin.default);
Shopware._private.publish('mixins', 'notification-translation', NotificationTranslationMixin);
