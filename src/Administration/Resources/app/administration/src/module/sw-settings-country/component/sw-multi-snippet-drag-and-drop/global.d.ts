import type Component from './index';

/**
 * @sw-package fundamentals@discovery
 */

declare global {
    interface PublicComponents {
        'sw-multi-snippet-drag-and-drop': typeof Component;
    }
}
