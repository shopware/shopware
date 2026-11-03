import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-gtc-checkbox': typeof Component;
    }
}
