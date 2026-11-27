/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeMediaModal(): void {
    Shopware.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig, { _event_ }) => {
        // The additional tabs render locations of the app that opened the modal.
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        Shopware.Store.get('mediaModal').openModal({
            ...modalConfig,
            baseUrl: extension?.baseUrl,
        });
    });

    Shopware.ExtensionAPI.handle('uiMediaModalSelect', ({ selection }, { _event_ }) => {
        const mediaModalStore = Shopware.Store.get('mediaModal');
        const mediaModal = mediaModalStore.mediaModal;

        // Only the app that opened the modal, through the location of one of its tabs, may select;
        // a selection without an open modal or from another app is ignored.
        if (!mediaModal?.baseUrl || !isOriginOf(mediaModal.baseUrl, _event_.origin)) {
            return;
        }

        if (typeof mediaModal.callback === 'function') {
            const callbackFn = mediaModal.callback as (selection: unknown[]) => void;
            callbackFn(selection);
        }

        mediaModalStore.closeModal();
    });

    Shopware.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        Shopware.Store.get('mediaModal').openSaveModal(saveModalConfig);
    });
}

/**
 * Whether the message origin is exactly the origin of the app's base URL (no prefix match, so
 * `https://app.example.evil` is not `https://app.example`).
 */
function isOriginOf(baseUrl: string, origin: string): boolean {
    try {
        return new URL(baseUrl).origin === origin;
    } catch {
        return false;
    }
}
