/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeModal(): void {
    Shopware.ExtensionAPI.handle('uiModalOpen', (modalConfig, { _event_ }) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(_event_.origin));

        if (!extension) {
            return;
        }

        Shopware.State.commit('modals/openModal', {
            closable: true,
            showHeader: true,
            variant: 'default',
            baseUrl: extension.baseUrl,
            ...modalConfig,
        });
    });

    Shopware.ExtensionAPI.handle('uiModalClose', ({ locationId }) => {
        Shopware.State.commit('modals/closeModal', locationId);
    });
}
