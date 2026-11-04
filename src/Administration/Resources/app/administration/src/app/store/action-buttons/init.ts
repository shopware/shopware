/**
 * @sw-package framework
 */
import { useActionButtonsStore } from './src/action-buttons.store';

/**
 * @private
 */
export default function initializeActionButtons(): void {
    Shopware.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        useActionButtonsStore().add(configuration);
    });
}
