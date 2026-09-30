import useMediaModalStore from 'shopware:stores/mediaModal';

/**
 * @sw-package framework
 *
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
