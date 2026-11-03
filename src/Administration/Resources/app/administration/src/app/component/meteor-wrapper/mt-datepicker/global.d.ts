import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'mt-datepicker': typeof Component;
    }
}
