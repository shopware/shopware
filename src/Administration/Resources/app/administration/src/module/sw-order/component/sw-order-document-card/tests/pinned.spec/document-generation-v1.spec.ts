import {
    MODALS,
    chooseDocumentAction,
    chooseLegacyDocumentType,
    clickCreateDocument,
    confirmDeletion,
    createDocument,
    emitFromModal,
    getModalProps,
    getNotifications,
    getOpenModals,
    getOpenedFiles,
    getServices,
    getShownDocumentNumbers,
    getStoredDocuments,
    grantPrivileges,
    mountDocumentCard,
    reportLegacyDocumentCreation,
    setUpDocuments,
    unmountDocumentCards,
} from '../document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    describe('document generation v1', () => {
        const params = { documentNumber: '1000', documentDate: '2026-07-06', documentComment: '' };

        async function openLegacySettingsModal(technicalName = 'invoice') {
            grantPrivileges('document.viewer');
            const wrapper = await mountDocumentCard();
            await chooseLegacyDocumentType(wrapper, technicalName);

            return wrapper;
        }

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK').each([
            ['invoice', MODALS.legacyInvoiceSettings],
            ['zugferd_embedded_invoice', MODALS.legacyInvoiceSettings],
            ['delivery_note', MODALS.legacyDocumentSettings],
        ])('opens the type selection, then the settings modal for %s', async (technicalName, modal) => {
            // ARRANGE
            grantPrivileges('document.viewer');
            const wrapper = await mountDocumentCard();
            await clickCreateDocument(wrapper);
            const selection = getOpenModals(wrapper);
            await emitFromModal(wrapper, MODALS.selectDocumentType, 'update:value', { technicalName });

            // ACT
            await emitFromModal(wrapper, MODALS.selectDocumentType, 'modal-close', true);

            // ASSERT
            expect(selection).toEqual([MODALS.selectDocumentType]);
            expect(getOpenModals(wrapper)).toEqual([modal]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('closes the type selection without a choice', async () => {
            // ARRANGE
            grantPrivileges('document.viewer');
            const wrapper = await mountDocumentCard();
            await clickCreateDocument(wrapper);

            // ACT
            await emitFromModal(wrapper, MODALS.selectDocumentType, 'modal-close', false);

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('closes the settings modal when it is left', async () => {
            // ARRANGE
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'page-leave');

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('shows the settings modal as loading when it asks to', async () => {
            // ARRANGE
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'loading-document');
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'loading-preview');

            // ASSERT
            expect(getModalProps(wrapper, MODALS.legacyInvoiceSettings)).toEqual(
                expect.objectContaining({ 'is-loading-document': true, 'is-loading-preview': true }),
            );
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK').each([
            ['invoice', 'pdf'],
            ['zugferd_invoice', 'xml'],
        ])('creates a %s and downloads it as %s', async (technicalName, fileType) => {
            // ARRANGE
            const wrapper = await openLegacySettingsModal(technicalName);

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'document-create', params, 'download');

            // ASSERT
            expect(getServices().documentService.createDocument).toHaveBeenCalledWith(
                'orderId',
                technicalName,
                params,
                null,
                {},
                {},
                null,
            );
            expect(getServices().documentService.getDocument).toHaveBeenCalledWith(
                'createdDocumentId',
                'deepLink',
                expect.anything(),
                true,
                fileType,
            );
            expect(getOpenedFiles()).toEqual([{ url: 'blob:legacy-file', fileName: 'legacy.pdf', inNewTab: false }]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK').each([
            [
                'opens the send modal',
                [createDocument('createdDocumentId')],
                [MODALS.sendDocument, MODALS.legacyInvoiceSettings],
            ],
            ['opens no send modal when it cannot find', [], [MODALS.legacyInvoiceSettings]],
        ])('%s for a created document', async (scenario, documents, modals) => {
            // ARRANGE
            setUpDocuments(documents);
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'document-create', params, 'send');

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual(modals);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('does nothing more when the creation returns no response', async () => {
            // ARRANGE
            getServices().documentService.createDocument.mockResolvedValueOnce(undefined as never);
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'document-create', params, 'download');

            // ASSERT
            expect(getServices().documentService.getDocument).not.toHaveBeenCalled();
            expect(getModalProps(wrapper, MODALS.legacyInvoiceSettings)['is-loading-document']).toBe(false);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('attaches the chosen media file to a created document', async () => {
            // ARRANGE
            setUpDocuments([createDocument('createdDocumentId')]);
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'document-create', {
                ...params,
                documentMediaFileId: 'mediaId',
            });

            // ASSERT
            expect(getStoredDocuments()[0].documentMediaFileId).toBe('mediaId');
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('closes the modals and reloads once the creation finished', async () => {
            // ARRANGE
            const wrapper = await openLegacySettingsModal();
            setUpDocuments([createDocument('createdDocumentId')]);

            // ACT
            await reportLegacyDocumentCreation();

            // ASSERT
            expect(getOpenModals(wrapper)).toEqual([]);
            expect(getShownDocumentNumbers(wrapper)).toEqual(['createdDocumentId-number']);
            expect(wrapper.emitted('document-save')).toHaveLength(1);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK').each([
            [
                'a taken number',
                { number: '1001', documentType: 'invoice' },
                'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS {"number":"1001","documentType":"invoice--label"}',
            ],
            ['a taken number without details', {}, 'server message'],
        ])('reports a creation that failed with %s', async (scenario, parameters, message) => {
            // ARRANGE
            await openLegacySettingsModal();

            // ACT
            await reportLegacyDocumentCreation({
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'server message',
                meta: { parameters },
            });

            // ASSERT
            expect(getNotifications()).toEqual([{ variant: 'error', message }]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('opens a preview in a new tab', async () => {
            // ARRANGE
            const wrapper = await openLegacySettingsModal();

            // ACT
            await emitFromModal(wrapper, MODALS.legacyInvoiceSettings, 'preview-show', params, 'pdf');

            // ASSERT
            expect(getServices().documentService.getDocumentPreview).toHaveBeenCalledWith(
                'orderId',
                'orderDeepLink',
                'invoice',
                params,
                { fileType: 'pdf' },
            );
            expect(getOpenedFiles()).toEqual([{ url: 'blob:legacy-preview', fileName: '', inNewTab: true }]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('opens and downloads through the legacy API', async () => {
            // ARRANGE
            grantPrivileges('document.viewer');
            setUpDocuments([
                createDocument('documentA', { documentFiles: [], documentMediaFile: { fileExtension: 'pdf' } }),
            ]);
            const wrapper = await mountDocumentCard();

            // ACT
            await chooseDocumentAction(wrapper, 0, 'open');
            await chooseDocumentAction(wrapper, 0, 'download');

            // ASSERT
            expect(getServices().documentService.getDocument.mock.calls).toEqual([
                [
                    'documentA',
                    'documentA-deepLink',
                    expect.anything(),
                    true,
                    'pdf',
                ],
                [
                    'documentA',
                    'documentA-deepLink',
                    expect.anything(),
                    true,
                    'pdf',
                ],
            ]);
            expect(getOpenedFiles()).toEqual([
                { url: 'blob:legacy-file', fileName: '', inNewTab: true },
                { url: 'blob:legacy-file', fileName: 'legacy.pdf', inNewTab: false },
            ]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('sends and marks a document from the context menu', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'api_send_email');
            setUpDocuments([createDocument('documentA'), createDocument('documentB', { sent: true })]);
            const wrapper = await mountDocumentCard();

            // ACT
            await chooseDocumentAction(wrapper, 1, 'mark-unsent');
            await chooseDocumentAction(wrapper, 0, 'send');

            // ASSERT
            expect(getStoredDocuments().map((document) => document.sent)).toEqual([false, false]);
            expect(getOpenModals(wrapper)).toEqual([MODALS.sendDocument]);
        });

        /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
        it.deprecated('DOCUMENT_GENERATION_REWORK')('deletes a document through the listing and reloads', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'document.deleter');
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();
            await chooseDocumentAction(wrapper, 0, 'delete');

            // ACT
            await confirmDeletion(wrapper);

            // ASSERT
            expect(getStoredDocuments()).toEqual([]);
            expect(wrapper.find('.sw-order-document-card__empty-state').exists()).toBe(true);
        });
    });
});
