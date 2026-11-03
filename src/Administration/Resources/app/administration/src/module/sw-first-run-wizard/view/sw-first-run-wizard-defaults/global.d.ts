import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-first-run-wizard-defaults': typeof Component;
    }
}
