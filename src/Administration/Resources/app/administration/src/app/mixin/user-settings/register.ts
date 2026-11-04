/**
 * @sw-package framework
 */
import * as UserSettingsMixin from './index';

Shopware.Mixin.register('user-settings', UserSettingsMixin.default);
Shopware._private.publish('mixins', 'user-settings', UserSettingsMixin);
