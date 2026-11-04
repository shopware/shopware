/**
 * @sw-package framework
 */
import type SwSettingsListMixin from './private';

declare global {
    interface MixinContainer {
        'sw-settings-list': typeof SwSettingsListMixin;
    }
}
