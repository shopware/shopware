import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-save-changes-beforehand-modal': typeof Component;
    }
}
