import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-settings-custom-field-set-list': typeof Component;
    }
}
