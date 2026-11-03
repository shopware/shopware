import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-model-editor-collapse': typeof Component;
    }
}
