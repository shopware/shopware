import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-oauth-authorize-index': typeof Component;
    }
}
