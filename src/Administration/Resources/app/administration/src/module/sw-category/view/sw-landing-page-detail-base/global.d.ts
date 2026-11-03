import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-landing-page-detail-base': typeof Component;
    }
}
