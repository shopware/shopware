import type Component from './index';

/**
 * @sw-package innovation
 */

declare global {
    interface PublicComponents {
        'sw-vector-field': typeof Component;
    }
}
