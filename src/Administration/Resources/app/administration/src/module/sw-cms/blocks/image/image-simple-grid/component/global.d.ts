import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-cms-block-image-simple-grid': typeof Component;
    }
}
