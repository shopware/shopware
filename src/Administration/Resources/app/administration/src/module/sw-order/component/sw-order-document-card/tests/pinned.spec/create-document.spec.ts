import {
    MODALS,
    clickCreateDocument,
    createDocument,
    createDocumentInModal,
    emitFromModal,
    getNotifications,
    getOpenModals,
    getOpenedFiles,
    getServices,
    grantPrivileges,
    mountDocumentCard,
    setUpDocuments,
    unmountDocumentCards,
} from '../document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    describe('creating a document with document generation v2', () => {
        beforeEach(() => grantPrivileges('document.viewer'));

        const params = {
            documentNumber: '',
            documentNumberPreview: '1000',
            documentDate: '2026-07-06',
            documentComment: 'comment',
            deliveryDate: '2026-07-07',
            requestedFileFormats: ['pdf'],
        };

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('sends every parameter of the modal to the API', async () => {
            // ARRANGE
            const wrapper = await mountDocumentCard();

            // ACT
            await createDocumentInModal(wrapper, 'invoice', params);

            // ASSERT
            expect(getServices().documentV2ApiService.createDocument).toHaveBeenCalledWith(
                'orderId',
                'invoice',
                ['pdf'],
                '',
                '2026-07-06',
                'comment',
                '2026-07-07',
                null,
            );
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            [
                'a changed automatic number',
                '',
                '1001',
                true,
            ],
            [
                'an unchanged automatic number',
                '',
                '1000',
                false,
            ],
            [
                'a manual number',
                'manual',
                'manual',
                false,
            ],
        ])(
            'tells the merchant about %s only when the number changed',
            async (scenario, documentNumber, assigned, notifies) => {
                // ARRANGE
                getServices().documentV2ApiService.createDocument.mockResolvedValueOnce({
                    documentId: 'createdDocumentId',
                    documentNumber: assigned,
                    formats: ['pdf'],
                });
                const wrapper = await mountDocumentCard();

                // ACT
                await createDocumentInModal(wrapper, 'invoice', { ...params, documentNumber });

                // ASSERT
                expect(getNotifications()).toEqual(
                    notifies
                        ? [{ variant: 'info', message: 'sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED' }]
                        : [],
                );
            },
        );

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports a failed creation', async () => {
            // ARRANGE
            getServices()
                .documentV2ApiService.createDocument.mockRejectedValueOnce({
                    response: { data: { errors: [{ code: 'KNOWN', meta: { parameters: { a: 1 } } }] } },
                })
                .mockRejectedValueOnce(new Error('failed'));
            getServices().documentV2Service.getErrorTranslation.mockImplementation((code: string) =>
                code === 'KNOWN' ? 'translated error' : null,
            );
            const wrapper = await mountDocumentCard();

            // ACT
            await createDocumentInModal(wrapper, 'invoice', params);
            await emitFromModal(wrapper, MODALS.createDocument, 'document-create', params, '');

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'translated error' },
                { variant: 'error', message: 'sw-order.documentCard.error.createDocument' },
            ]);
            expect(getOpenModals(wrapper)).toEqual([MODALS.createDocument]);
            expect(wrapper.emitted('document-save')).toBeUndefined();
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            [['html'], 'getDocument', ['createdDocumentId', 'html']],
            [['pdf', 'html'], 'getDocumentArchive', [['createdDocumentId']]],
        ] as const)('downloads a document created in %j through %s', async (formats, endpoint, args) => {
            // ARRANGE
            const wrapper = await mountDocumentCard();

            // ACT
            await createDocumentInModal(wrapper, 'invoice', { ...params, requestedFileFormats: formats }, 'download');

            // ASSERT
            expect(getServices().documentV2ApiService[endpoint].mock.calls).toEqual([args]);
            expect(getOpenedFiles()).toHaveLength(1);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            ['opens the send modal', [createDocument('createdDocumentId')], [MODALS.sendDocument]],
            ['opens no send modal when it cannot find', [], []],
        ])('%s for a created document', async (scenario, documents, modals) => {
            // ARRANGE
            setUpDocuments(documents);
            const wrapper = await mountDocumentCard();

            // ACT
            await createDocumentInModal(wrapper, 'invoice', params, 'send');

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual(modals);
            expect(wrapper.emitted('document-save')).toHaveLength(1);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports a created document it cannot send', async () => {
            // ARRANGE
            getServices().documentRepository.get.mockRejectedValueOnce(new Error('failed'));
            const wrapper = await mountDocumentCard();

            // ACT
            await createDocumentInModal(wrapper, 'invoice', params, 'send');

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'sw-order.documentCard.error.loadSendingDocument' },
            ]);
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('opens a preview in a new tab', async () => {
            // ARRANGE
            const wrapper = await mountDocumentCard();
            await clickCreateDocument(wrapper);
            await emitFromModal(wrapper, MODALS.createDocument, 'update:documentType', { technicalName: 'invoice' });

            // ACT
            await emitFromModal(wrapper, MODALS.createDocument, 'preview-show', params, 'html');

            // ASSERT
            expect(getServices().documentV2ApiService.previewDocument).toHaveBeenCalledWith(
                'orderId',
                'invoice',
                'html',
                '',
                '2026-07-06',
                'comment',
            );
            expect(getOpenedFiles()).toEqual([{ url: 'blob:preview-file', fileName: '', inNewTab: true }]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            [
                'a translated error',
                { text: () => Promise.resolve('{"errors":[{"code":"KNOWN","meta":{"parameters":{}}}]}') },
                'translated error',
            ],
            [
                'an unknown error',
                { text: () => Promise.resolve('{"errors":[]}') },
                'sw-order.documentCard.error.loadDocumentPreview',
            ],
            ['an unreadable error', undefined, 'sw-order.documentCard.error.loadDocumentPreview'],
        ])('reports a preview that failed with %s', async (scenario, data, message) => {
            // ARRANGE
            getServices().documentV2ApiService.previewDocument.mockRejectedValueOnce({ response: { data } });
            getServices().documentV2Service.getErrorTranslation.mockImplementation((code: string) =>
                code === 'KNOWN' ? 'translated error' : null,
            );
            const wrapper = await mountDocumentCard();
            await clickCreateDocument(wrapper);
            await emitFromModal(wrapper, MODALS.createDocument, 'update:documentType', { technicalName: 'invoice' });

            // ACT
            await emitFromModal(wrapper, MODALS.createDocument, 'preview-show', params, 'pdf');

            // ASSERT
            expect(getNotifications()).toEqual([{ variant: 'error', message }]);
        });
    });
});
