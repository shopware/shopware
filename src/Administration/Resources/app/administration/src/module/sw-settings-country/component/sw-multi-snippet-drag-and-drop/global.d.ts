import type Component from './private';

/**
 * @sw-package fundamentals@discovery
 */

declare global {
    interface PrivateComponents {
        'sw-multi-snippet-drag-and-drop': typeof Component;
    }
}
