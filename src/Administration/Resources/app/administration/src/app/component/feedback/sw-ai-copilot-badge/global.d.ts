import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-ai-copilot-badge': typeof Component;
    }
}
