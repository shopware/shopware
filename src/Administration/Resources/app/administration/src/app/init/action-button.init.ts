/**
 * @sw-package framework
 *
 * @private
 */
import '../store/action-buttons.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActionButtons(): void {
    Shopware.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        Shopware.Store.get('actionButtons').add(configuration);
    });
}
