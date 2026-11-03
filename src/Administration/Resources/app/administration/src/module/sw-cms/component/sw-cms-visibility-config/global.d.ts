import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-cms-visibility-config': typeof Component;
    }
}
