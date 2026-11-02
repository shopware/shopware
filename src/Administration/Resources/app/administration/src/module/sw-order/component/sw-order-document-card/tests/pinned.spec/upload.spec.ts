import {
    MODALS,
    createDocument,
    getNotifications,
    getOpenModals,
    getServices,
    getStoredDocuments,
    grantPrivileges,
    mountDocumentCard,
    setUpDocuments,
    unmountDocumentCards,
    uploadDocumentInModal,
} from '../document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    describe('uploading a document', () => {
        async function uploadDocument(params: Record<string, unknown>, additionalAction = '') {
            grantPrivileges('document.viewer');
            const wrapper = await mountDocumentCard();
            await uploadDocumentInModal(wrapper, 'invoice', params, { additionalAction });

            return wrapper;
        }

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('uploads the first requested format', async () => {
            // ACT
            await uploadDocument({ documentNumber: '1000', requestedFileFormats: ['html', 'pdf'] });

            // ASSERT
            expect(getServices().documentV2ApiService.uploadDocument.mock.calls[0][3]).toBe('html');
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('attaches the chosen media file', async () => {
            // ARRANGE
            setUpDocuments([createDocument('uploadedDocumentId')]);

            // ACT
            await uploadDocument({ documentNumber: '1000', documentMediaFileId: 'mediaId' });

            // ASSERT
            expect(getStoredDocuments()[0].documentMediaFileId).toBe('mediaId');
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports a media file it cannot attach', async () => {
            // ARRANGE
            getServices().documentRepository.get.mockRejectedValueOnce(new Error('failed'));

            // ACT
            const wrapper = await uploadDocument({ documentNumber: '1000', documentMediaFileId: 'mediaId' });

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'sw-order.documentCard.error.attachMediaToDocumentUpload' },
            ]);
            expect(wrapper.emitted('document-save')).toBeUndefined();
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports a failed upload', async () => {
            // ARRANGE
            getServices().documentV2ApiService.uploadDocument.mockRejectedValueOnce(new Error('failed'));

            // ACT
            const wrapper = await uploadDocument({ documentNumber: '1000' });

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'sw-order.documentCard.error.uploadDocument' },
            ]);
            expect(getOpenModals(wrapper)).toEqual([MODALS.uploadDocument]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('opens the send modal for an uploaded document', async () => {
            // ARRANGE
            setUpDocuments([createDocument('uploadedDocumentId')]);

            // ACT
            const wrapper = await uploadDocument({ documentNumber: '1000' }, 'send');

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual([MODALS.sendDocument]);
        });
    });
});
