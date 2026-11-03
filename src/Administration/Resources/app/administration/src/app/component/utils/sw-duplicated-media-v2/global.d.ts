import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-duplicated-media-v2': typeof Component;
    }
}
