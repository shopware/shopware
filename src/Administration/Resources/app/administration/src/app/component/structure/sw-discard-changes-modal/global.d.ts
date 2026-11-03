import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-discard-changes-modal': typeof Component;
    }
}
