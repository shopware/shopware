import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-settings-shopware-updates-extensions': typeof Component;
    }
}
