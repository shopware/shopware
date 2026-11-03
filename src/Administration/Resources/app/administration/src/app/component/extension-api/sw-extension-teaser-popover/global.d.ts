import type Component from './private';

/**
 * @sw-package innovation
 */

declare global {
    interface PrivateComponents {
        'sw-extension-teaser-popover': typeof Component;
    }
}
