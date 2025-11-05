/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeMediaModal(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig) => {
        Shopware.Store.get('mediaModal').openModal(modalConfig);
    });

    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        Shopware.Store.get('mediaModal').openSaveModal(saveModalConfig);
    });
}
