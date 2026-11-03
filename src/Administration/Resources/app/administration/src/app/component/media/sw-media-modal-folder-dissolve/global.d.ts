import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-media-modal-folder-dissolve': typeof Component;
    }
}
