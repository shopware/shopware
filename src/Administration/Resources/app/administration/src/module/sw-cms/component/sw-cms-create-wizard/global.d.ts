import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-cms-create-wizard': typeof Component;
    }
}
