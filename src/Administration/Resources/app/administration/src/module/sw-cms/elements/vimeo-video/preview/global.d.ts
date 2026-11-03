import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-cms-el-preview-vimeo-video': typeof Component;
    }
}
