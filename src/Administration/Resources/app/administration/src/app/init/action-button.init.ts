/**
 * @sw-package framework
 *
 * @private
 */
import '../store/action-buttons.store';
import useActionButtonsStore from 'shopware:stores/actionButtons';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActionButtons(): void {
    Shopware.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        useActionButtonsStore().add(configuration);
    });
}
