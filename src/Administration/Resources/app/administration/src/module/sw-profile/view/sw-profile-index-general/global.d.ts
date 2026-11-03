import type Component from './index';

/**
 * @sw-package fundamentals@framework
 */

declare global {
    interface PublicComponents {
        'sw-profile-index-general': typeof Component;
    }
}
