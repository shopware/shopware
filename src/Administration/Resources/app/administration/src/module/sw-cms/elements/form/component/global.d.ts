import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-cms-el-form': typeof Component;
    }
}
