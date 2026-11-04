import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-settings-logging-list': typeof Component;
    }
}
