/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeMediaModal(): void {
    Shopware.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig) => {
        Shopware.Store.get('mediaModal').openModal(modalConfig);
    });

    Shopware.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        Shopware.Store.get('mediaModal').openSaveModal(saveModalConfig);
    });
}
