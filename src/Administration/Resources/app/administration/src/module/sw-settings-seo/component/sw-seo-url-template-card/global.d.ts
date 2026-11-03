import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-seo-url-template-card': typeof Component;
    }
}
