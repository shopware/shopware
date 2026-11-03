import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-cms-block-form': typeof Component;
    }
}
