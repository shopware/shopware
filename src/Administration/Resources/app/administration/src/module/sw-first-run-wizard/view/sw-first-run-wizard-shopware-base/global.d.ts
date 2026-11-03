import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-first-run-wizard-shopware-base': typeof Component;
    }
}
