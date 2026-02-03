/**
 * @sw-package framework
 *
 * @private
 * @description Registers the extension order tracker store for tracking extension entry ordering.
 */
import '../store/extension-order-tracker.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeExtensionOrderTracker(): void {
    // Store is registered on import, no additional initialization needed
}
