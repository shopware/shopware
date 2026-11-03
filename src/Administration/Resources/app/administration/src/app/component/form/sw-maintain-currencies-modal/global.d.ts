import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-maintain-currencies-modal': typeof Component;
    }
}
