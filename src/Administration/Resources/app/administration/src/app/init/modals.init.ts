import useExtensionsStore from 'shopware:stores/extensions';
import useModalsStore from 'shopware:stores/modals';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeModal(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiModalOpen', async (modalConfig, { _event_ }) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        useModalsStore().openModal({
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: extension.baseUrl,
            ...modalConfig,
        });
    });

    Shopware.ExtensionAPI.handle('uiModalUpdate', (modalConfig, { _event_ }) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // Update the modal with the new configuration
        const currentModal = useModalsStore().modals.findIndex((modal) => {
            return modal.locationId === modalConfig.locationId;
        });

        if (currentModal !== -1) {
            // Index is used to maintain Vue reactivity
            useModalsStore().modals[currentModal] = {
                ...useModalsStore().modals[currentModal],
                ...modalConfig,
                // Buttons explizit überschreiben, falls im modalConfig enthalten
                ...(modalConfig.buttons ? { buttons: modalConfig.buttons } : {}),
            };
        } else {
            console.error(`Modal with locationId "${modalConfig.locationId}" not found.`);
        }
    });

    Shopware.ExtensionAPI.handle('uiModalClose', ({ locationId }) => {
        if (!locationId) {
            useModalsStore().closeLastModalWithoutLocationId();
        } else {
            useModalsStore().closeModal(locationId);
        }
    });
}
