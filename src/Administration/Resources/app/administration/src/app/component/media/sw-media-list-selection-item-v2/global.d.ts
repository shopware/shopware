import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-media-list-selection-item-v2': typeof Component;
    }
}
