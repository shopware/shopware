import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-generic-cms-page-assignment': typeof Component;
    }
}
