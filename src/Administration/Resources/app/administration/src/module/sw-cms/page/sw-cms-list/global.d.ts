import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-cms-list': typeof Component;
    }
}
