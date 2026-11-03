import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-tabs-deprecated': typeof Component;
    }
}
