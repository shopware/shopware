import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-seo-main-category': typeof Component;
    }
}
