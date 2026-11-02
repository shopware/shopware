import {
    MODALS,
    cancelDeletion,
    chooseDocumentAction,
    confirmDeletion,
    createDocument,
    emitFromModal,
    findDocumentAction,
    getDocumentSearches,
    getNotifications,
    getOpenModals,
    getOpenedFiles,
    getServices,
    getStoredDocuments,
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
});
