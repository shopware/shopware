import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-multi-tag-ip-select': typeof Component;
    }
}
