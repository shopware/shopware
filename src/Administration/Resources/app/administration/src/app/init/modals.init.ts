/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeModal(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiModalOpen', async (modalConfig, { _event_ }) => {
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        Shopware.Store.get('modals').openModal({
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: extension.baseUrl,
            ...modalConfig,
        });
    });

    Shopware.ExtensionAPI.handle('uiModalClose', ({ locationId }) => {
        Shopware.Store.get('modals').closeModal(locationId);
    });
}
