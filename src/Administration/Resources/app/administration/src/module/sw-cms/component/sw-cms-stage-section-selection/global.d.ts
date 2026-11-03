import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-cms-stage-section-selection': typeof Component;
    }
}
