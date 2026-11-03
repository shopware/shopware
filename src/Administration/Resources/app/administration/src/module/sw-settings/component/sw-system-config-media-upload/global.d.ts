import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-system-config-media-upload': typeof Component;
    }
}
