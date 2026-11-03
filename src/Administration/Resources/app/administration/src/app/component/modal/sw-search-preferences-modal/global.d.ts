import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-search-preferences-modal': typeof Component;
    }
}
