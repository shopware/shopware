import {
    BUTTON_CREATE_DOCUMENT,
    MODALS,
    clickCreateDocument,
    clickUploadDocument,
    emitFromModal,
    getNotifications,
    getOpenModals,
    getServices,
    grantPrivileges,
    mountDocumentCard,
    startEditingOrder,
    unmountDocumentCards,
} from '../document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    describe('the create button', () => {
        it.each([
            [
                'without privileges',
                [],
                false,
                'sw-privileges.tooltip.warning',
                true,
            ],
            [
                'while the order is edited',
                ['document.viewer'],
                true,
                'sw-order.documentTab.tooltipSaveBeforeCreateDocument',
                true,
            ],
            [
                'with the privilege to view documents',
                ['document.viewer'],
                false,
                'sw-order.documentTab.tooltipSaveBeforeCreateDocument',
                false,
            ],
        ])('explains or enables the button %s', async (scenario, privileges, editing, tooltip, disabled) => {
            // ARRANGE
            grantPrivileges(...privileges);
            if (editing) {
                startEditingOrder();
            }

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            const button = wrapper.find(BUTTON_CREATE_DOCUMENT);
            expect(button.attributes('data-tooltip')).toBe(tooltip);
            expect(button.attributes('disabled') !== undefined).toBe(disabled);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('closes the create and upload modals', async () => {
            // ARRANGE
            grantPrivileges('document.viewer');
            const wrapper = await mountDocumentCard();
            await clickCreateDocument(wrapper);
            await emitFromModal(wrapper, MODALS.createDocument, 'page-leave');
            await clickUploadDocument(wrapper);

            // ACT
            await emitFromModal(wrapper, MODALS.uploadDocument, 'page-leave');

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports document types that fail to load', async () => {
            // ARRANGE
            getServices().documentV2Service.getAvailableDocumentTypes.mockRejectedValue(new Error('failed'));

            // ACT
            await mountDocumentCard();

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'sw-order.documentCard.error.loadDocumentTypes' },
            ]);
        });
    });
});
