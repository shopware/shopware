import type Component from './private';

/**
 * @sw-package inventory
 */

declare global {
    interface PrivateComponents {
        'sw-generic-seo-general-card': typeof Component;
    }
}
