import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-custom-field-set-detail-base': typeof Component;
    }
}
