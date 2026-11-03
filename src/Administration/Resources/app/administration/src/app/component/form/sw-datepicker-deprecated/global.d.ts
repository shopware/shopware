import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-datepicker-deprecated': typeof Component;
    }
}
