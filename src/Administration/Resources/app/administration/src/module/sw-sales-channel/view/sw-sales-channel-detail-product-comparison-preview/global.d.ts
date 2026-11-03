import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-sales-channel-detail-product-comparison-preview': typeof Component;
    }
}
