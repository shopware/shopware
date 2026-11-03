import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-agentic-commerce-tracking-config': typeof Component;
    }
}
