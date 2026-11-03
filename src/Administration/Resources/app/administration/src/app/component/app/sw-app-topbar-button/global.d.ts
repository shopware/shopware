import type Component from './private';

/**
 * @sw-package innovation
 */

declare global {
    interface PrivateComponents {
        'sw-app-topbar-button': typeof Component;
    }
}
