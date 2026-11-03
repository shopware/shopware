import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-media-add-thumbnail-form': typeof Component;
    }
}
