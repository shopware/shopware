/**
 * @sw-package framework
 */
import { useMediaModalStore } from './src/media-modal.store';

/**
 * @private
 */
export default function initializeMediaModal(): void {
    Shopware.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig) => {
        useMediaModalStore().openModal(modalConfig);
    });

    Shopware.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        useMediaModalStore().openSaveModal(saveModalConfig);
    });
}
