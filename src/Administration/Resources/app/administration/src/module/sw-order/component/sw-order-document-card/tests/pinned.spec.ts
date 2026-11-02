import {
    BUTTON_CREATE_DOCUMENT,
    MODALS,
    cancelDeletion,
    chooseDocumentAction,
    chooseLegacyDocumentType,
    clickCreateDocument,
    clickUploadDocument,
    confirmDeletion,
    createDocument,
    createDocumentInModal,
    emitFromModal,
    findDocumentAction,
    getColumnLabels,
    getDocumentRows,
    getDocumentSearches,
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
    searchDocuments,
    setUpDocuments,
    startEditingOrder,
    unmountDocumentCards,
    uploadDocumentInModal,
} from './document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    describe('the list', () => {
        it('loads the newest documents of the order first, with their type and files', async () => {
            // ACT
            await mountDocumentCard();

            // ASSERT
            expect(getDocumentSearches()).toEqual([
                {
                    term: null,
                    queries: [],
                    page: 1,
                    filters: [{ type: 'equals', field: 'order.id', value: 'orderId' }],
                },
            ]);
        });

        it('searches the document date and number with a high ranking', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();

            // ACT
            await searchDocuments(wrapper, '2026');

            // ASSERT
            expect(getDocumentSearches().at(-1)?.queries).toEqual([
                { score: 500, query: { type: 'contains', field: 'config.documentDate', value: '2026' } },
                { score: 500, query: { type: 'equals', field: 'config.documentNumber', value: '2026' } },
            ]);
        });

        it.each([
            [
                'sw.order.detail.documents',
                [
                    'labelDate',
                    'labelNumber',
                    'labelType',
                    'labelAvailableFormats',
                    'labelSent',
                ],
            ],
            [
                'sw.order.detail.general',
                [
                    'labelDate',
                    'labelNumber',
                    'labelType',
                    'labelSent',
                ],
            ],
        ])('shows the columns of the route %s', async (routeName, labels) => {
            // ARRANGE
            setUpDocuments([createDocument('documentA')]);

            // ACT
            const wrapper = await mountDocumentCard({}, { routeName });

            // ASSERT
            expect(getColumnLabels(wrapper)).toEqual(labels.map((label) => `sw-order.documentCard.${label}`));
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('adds an unlabeled actions column', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA')]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(getColumnLabels(wrapper).at(-1)).toBe('');
            expect(getColumnLabels(wrapper)).toHaveLength(6);
        });

        it('lists every file format of a document once, including the legacy media files', async () => {
            // ARRANGE
            setUpDocuments([
                createDocument('documentA', {
                    documentFiles: [{ documentFormat: 'pdf' }, { documentFormat: 'zugferd_xml' }],
                    documentMediaFile: { fileExtension: 'pdf' },
                    documentA11yMediaFile: { fileExtension: 'html' },
                }),
            ]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(getDocumentRows(wrapper)[0].find('.sw-data-grid__cell--fileTypes').text()).toBe(
                'pdf--snippet, zugferd_xml--snippet, html--snippet',
            );
        });

        it('labels a document by its type name, or by its document type without one', async () => {
            // ARRANGE
            setUpDocuments([
                createDocument('documentA'),
                createDocument('documentB', {
                    typeName: null,
                    documentType: { name: 'Delivery note', translated: { name: null } },
                }),
            ]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            const types = getDocumentRows(wrapper).map((row) => row.find('.sw-data-grid__cell--documentType-name').text());
            expect(types).toEqual(['invoice--label', 'Delivery note']);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('labels a document by its supported type', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA')]);
            getServices().documentV2Service.getAvailableDocumentTypes.mockResolvedValue({
                invoice: { label: { 'en-GB': 'Supported invoice' } },
            });

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(getDocumentRows(wrapper)[0].find('.sw-data-grid__cell--typeName').text()).toBe('Supported invoice');
        });

        it('tells the parent while it loads', async () => {
            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(wrapper.emitted('update-loading')).toEqual([[true], [false]]);
        });

        it('shows the empty state with a create button for an order without documents', async () => {
            // ARRANGE
            grantPrivileges('document.viewer');

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            const emptyState = wrapper.find('.sw-order-document-card__empty-state');
            expect(emptyState.text()).toContain('sw-order.documentCard.messageEmptyTitle');
            expect(emptyState.find(BUTTON_CREATE_DOCUMENT).exists()).toBe(true);
            expect(wrapper.find('.sw-card-filter').exists()).toBe(false);
            expect(wrapper.find('.sw-order-document-card').classes()).toContain('sw-order-document-card--is-empty');
        });

        it('keeps the search and hides the empty state when a search finds nothing', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();
            setUpDocuments([]);

            // ACT
            await searchDocuments(wrapper, 'nothing');

            // ASSERT
            expect(wrapper.find('.sw-card-filter').exists()).toBe(true);
            expect(wrapper.find('.sw-order-document-card__empty-state').exists()).toBe(false);
            expect(getDocumentRows(wrapper)).toHaveLength(0);
        });
    });

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

    describe('the document actions of document generation v2', () => {
        beforeEach(() => grantPrivileges('document.viewer'));

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('offers a PDF for a document without files', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA', { documentFiles: [] })]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(findDocumentAction(wrapper, 0, 'download', 'pdf').exists()).toBe(true);
            expect(findDocumentAction(wrapper, 0, 'download-all').exists()).toBe(false);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('downloads all formats as an archive', async () => {
            // ARRANGE
            setUpDocuments([
                createDocument('documentA', { documentFiles: [{ documentFormat: 'pdf' }, { documentFormat: 'html' }] }),
            ]);
            const wrapper = await mountDocumentCard();

            // ACT
            await chooseDocumentAction(wrapper, 0, 'download-all');

            // ASSERT
            expect(getServices().documentV2ApiService.getDocumentArchive).toHaveBeenCalledWith(['documentA']);
            expect(getOpenedFiles()).toEqual([{ url: 'blob:archive-file', fileName: 'documents.zip', inNewTab: false }]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            [
                [],
                [
                    'open',
                    'download',
                    'send',
                    'mark-sent',
                    'mark-unsent',
                    'delete',
                ],
            ],
            [['document.viewer'], ['send', 'mark-unsent', 'delete']],
            [['document.viewer', 'api_send_email', 'document.deleter'], ['mark-unsent']],
        ])('disables the actions a merchant with %j may not use', async (privileges, disabledActions) => {
            // ARRANGE
            grantPrivileges(...privileges);
            setUpDocuments([createDocument('documentA')]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            const actions = [
                'open',
                'download',
                'send',
                'mark-sent',
                'mark-unsent',
                'delete',
            ] as const;
            const disabled = actions.filter(
                (action) => findDocumentAction(wrapper, 0, action).attributes('disabled') !== undefined,
            );
            expect(disabled).toEqual(disabledActions);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('offers marking as sent or unsent by state', async () => {
            // ARRANGE
            setUpDocuments([createDocument('documentA'), createDocument('documentB', { sent: true })]);

            // ACT
            const wrapper = await mountDocumentCard();

            // ASSERT
            expect(findDocumentAction(wrapper, 0, 'mark-sent').attributes('disabled')).toBeUndefined();
            expect(findDocumentAction(wrapper, 0, 'mark-unsent').attributes('disabled')).toBeDefined();
            expect(findDocumentAction(wrapper, 1, 'mark-sent').attributes('disabled')).toBeDefined();
            expect(findDocumentAction(wrapper, 1, 'mark-unsent').attributes('disabled')).toBeUndefined();
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            ['mark-sent', false, true],
            ['mark-unsent', true, false],
        ] as const)('lets %s change a document and reloads the list', async (action, sent, expected) => {
            // ARRANGE
            setUpDocuments([createDocument('documentA', { sent })]);
            const wrapper = await mountDocumentCard();

            // ACT
            await chooseDocumentAction(wrapper, 0, action);

            // ASSERT
            expect(getStoredDocuments()[0].sent).toBe(expected);
            expect(getDocumentSearches()).toHaveLength(2);
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('leaves a document unsent on closing sending', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'api_send_email');
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();
            await chooseDocumentAction(wrapper, 0, 'send');

            // ACT
            await emitFromModal(wrapper, MODALS.sendDocument, 'modal-close');

            // ASSERT
            expect(getStoredDocuments()[0].sent).toBe(false);
            expect(getOpenModals(wrapper)).toEqual([]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('keeps a document when the deletion is canceled', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'document.deleter');
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();
            await chooseDocumentAction(wrapper, 0, 'delete');

            // ACT
            await cancelDeletion(wrapper);

            // ASSERT
            expect(wrapper.find('.sw-modal').exists()).toBe(false);
            expect(getStoredDocuments()).toHaveLength(1);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('shows the empty state after the last deletion', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'document.deleter');
            setUpDocuments([createDocument('documentA')]);
            const wrapper = await mountDocumentCard();
            await chooseDocumentAction(wrapper, 0, 'delete');

            // ACT
            await confirmDeletion(wrapper);

            // ASSERT
            expect(wrapper.find('.sw-order-document-card__empty-state').exists()).toBe(true);
            expect(wrapper.find('.sw-card-filter').exists()).toBe(false);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('reports a document that could not be deleted', async () => {
            // ARRANGE
            grantPrivileges('document.viewer', 'document.deleter');
            setUpDocuments([createDocument('documentA')]);
            getServices().documentRepository.delete.mockRejectedValueOnce(new Error('failed'));
            const wrapper = await mountDocumentCard();
            await chooseDocumentAction(wrapper, 0, 'delete');

            // ACT
            await confirmDeletion(wrapper);

            // ASSERT
            expect(getNotifications()).toEqual([
                { variant: 'error', message: 'sw-order.documentCard.notificationDeleteErrorMessage' },
            ]);
        });

        it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK']).each([
            ['open', 'openDocument', 'getDocument'],
            ['download', 'downloadDocument', 'getDocument'],
            ['download-all', 'downloadDocumentArchive', 'getDocumentArchive'],
        ] as const)('reports a document that could not be handled by %s', async (action, error, endpoint) => {
            // ARRANGE
            setUpDocuments([
                createDocument('documentA', { documentFiles: [{ documentFormat: 'pdf' }, { documentFormat: 'html' }] }),
            ]);
            getServices().documentV2ApiService[endpoint].mockRejectedValueOnce(new Error('failed'));
            const wrapper = await mountDocumentCard();

            // ACT
            await chooseDocumentAction(wrapper, 0, action, action === 'download-all' ? undefined : 'pdf');

            // ASSERT
            expect(getNotifications()).toEqual([{ variant: 'error', message: `sw-order.documentCard.error.${error}` }]);
            expect(getOpenedFiles()).toEqual([]);
        });
    });

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
