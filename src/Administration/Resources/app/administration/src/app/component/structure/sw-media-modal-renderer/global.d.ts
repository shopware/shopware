import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-media-modal-renderer': typeof Component;
    }
}
