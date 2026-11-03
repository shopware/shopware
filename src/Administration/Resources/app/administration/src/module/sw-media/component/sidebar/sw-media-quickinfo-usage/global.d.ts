import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-media-quickinfo-usage': typeof Component;
    }
}
