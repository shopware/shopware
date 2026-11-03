import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-category-entry-point-overwrite-modal': typeof Component;
    }
}
