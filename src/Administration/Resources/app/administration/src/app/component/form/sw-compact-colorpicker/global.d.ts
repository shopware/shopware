import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-compact-colorpicker': typeof Component;
    }
}
