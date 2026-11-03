import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-media-compact-upload-v2': typeof Component;
    }
}
