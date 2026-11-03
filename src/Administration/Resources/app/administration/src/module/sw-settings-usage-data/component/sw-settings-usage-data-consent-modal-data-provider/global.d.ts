import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-settings-usage-data-consent-modal-data-provider': typeof Component;
    }
}
