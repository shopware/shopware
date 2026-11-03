import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-cms-section-actions': typeof Component;
    }
}
