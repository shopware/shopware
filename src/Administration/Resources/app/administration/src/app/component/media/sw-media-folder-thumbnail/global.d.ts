import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-media-folder-thumbnail': typeof Component;
    }
}
