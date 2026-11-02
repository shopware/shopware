import {
    BUTTON_CREATE_DOCUMENT,
    MODALS,
    chooseDocumentAction,
    confirmDeletion,
    createDocument,
    createDocumentInModal,
    createOrder,
    emitFromModal,
    findDocumentAction,
    getColumnLabels,
    getDocumentSearches,
    getOpenModals,
    getOpenedFiles,
    getServices,
    getShownDocumentNumbers,
    getStoredDocuments,
    grantPrivileges,
    mountDocumentCard,
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

    /**
     * @requirement A merchant sees the documents of an order on the order.
     * Invoices, delivery notes and credit notes belong to the order they were made for.
     */
    it('shows the documents of the order', async () => {
        // ARRANGE
        setUpDocuments([
            createDocument('documentA'),
            createDocument('documentB'),
        ]);

        // ACT
        const wrapper = await mountDocumentCard({ order: createOrder({ documents: [{ id: 'documentA' }] }) });

        // ASSERT
        expect(getShownDocumentNumbers(wrapper)).toEqual([
            'documentA-number',
            'documentB-number',
        ]);
    });

    /**
     * @requirement A merchant can search the documents of an order by a term, such as a document number.
     * Orders with many documents are hard to scan.
     */
    it('searches the documents by a term', async () => {
        // ARRANGE
        setUpDocuments([createDocument('documentA')]);
        const wrapper = await mountDocumentCard({ order: createOrder({ documents: [{ id: 'documentA' }] }) });

        // ACT
        await searchDocuments(wrapper, '1000');

        // ASSERT
        expect(getDocumentSearches().at(-1)?.term).toBe('1000');
    });

    /**
     * @requirement Only a merchant who may view documents can create one.
     */
    it('does not let a merchant without document privileges create a document', async () => {
        // ARRANGE
        grantPrivileges('order.viewer');

        // ACT
        const wrapper = await mountDocumentCard();

        // ASSERT
        expect(wrapper.find(BUTTON_CREATE_DOCUMENT).attributes('disabled')).toBeDefined();
    });

    /**
     * @requirement A merchant saves the changes of an order before creating a document for it.
     * A document shows the order as it is saved, so unsaved changes would be missing from it.
     */
    it('does not let a merchant create a document while the order is being edited', async () => {
        // ARRANGE
        grantPrivileges('document.viewer');
        startEditingOrder();

        // ACT
        const wrapper = await mountDocumentCard();

        // ASSERT
        expect(wrapper.find(BUTTON_CREATE_DOCUMENT).attributes('disabled')).toBeDefined();
    });

    /**
     * @requirement A merchant creates a document of a chosen type for the order, and then sees it in the list.
     * The order page reloads too, because a document can change the order.
     */
    it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('creates a document of the chosen type', async () => {
        // ARRANGE
        grantPrivileges('document.viewer');
        const wrapper = await mountDocumentCard();
        setUpDocuments([createDocument('createdDocumentId')]);

        // ACT
        await createDocumentInModal(wrapper, 'invoice', { requestedFileFormats: ['pdf'] });

        // ASSERT
        expect(getServices().documentV2ApiService.createDocument.mock.calls[0].slice(0, 3)).toEqual([
            'orderId',
            'invoice',
            ['pdf'],
        ]);
        expect(getShownDocumentNumbers(wrapper)).toEqual(['createdDocumentId-number']);
        expect(wrapper.emitted('document-save')).toHaveLength(1);
    });

    /**
     * @requirement A merchant can upload a document made elsewhere, such as an invoice from an accounting tool.
     */
    it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('uploads a document', async () => {
        // ARRANGE
        grantPrivileges('document.viewer');
        const wrapper = await mountDocumentCard();
        const file = new File(['pdf'], 'invoice.pdf', { type: 'application/pdf' });

        // ACT
        await uploadDocumentInModal(wrapper, 'invoice', { documentNumber: '1000' }, { file });

        // ASSERT
        expect(getServices().documentV2ApiService.uploadDocument).toHaveBeenCalledWith(
            'orderId',
            'orderVersionId',
            'invoice',
            'pdf',
            '1000',
            undefined,
            file,
        );
        expect(wrapper.emitted('document-save')).toHaveLength(1);
    });

    /**
     * @requirement A merchant can open and download a document in each file format it exists in.
     */
    it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('opens and downloads a document in a chosen format', async () => {
        // ARRANGE
        grantPrivileges('document.viewer');
        setUpDocuments([
            createDocument('documentA', { documentFiles: [{ documentFormat: 'pdf' }, { documentFormat: 'html' }] }),
        ]);
        const wrapper = await mountDocumentCard();

        // ACT
        await chooseDocumentAction(wrapper, 0, 'open', 'html');
        await chooseDocumentAction(wrapper, 0, 'download', 'pdf');

        // ASSERT
        expect(getOpenedFiles()).toEqual([
            { url: 'blob:documentA-file', fileName: '', inNewTab: true },
            { url: 'blob:documentA-file', fileName: 'documentA.pdf', inNewTab: false },
        ]);
    });

    /**
     * @requirement A merchant can send a document to the customer, and the document is then marked as sent.
     */
    it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('sends a document and marks it as sent', async () => {
        // ARRANGE
        grantPrivileges('document.viewer', 'api_send_email');
        setUpDocuments([createDocument('documentA')]);
        const wrapper = await mountDocumentCard();

        // ACT
        await chooseDocumentAction(wrapper, 0, 'send');
        await emitFromModal(wrapper, MODALS.sendDocument, 'document-sent');

        // ASSERT
        expect(getStoredDocuments()[0].sent).toBe(true);
        expect(getOpenModals(wrapper)).toEqual([]);
    });

    /**
     * @requirement A merchant can delete a document after confirming it.
     */
    it.activeFeatureFlags(['DOCUMENT_GENERATION_REWORK'])('deletes a document after confirmation', async () => {
        // ARRANGE
        grantPrivileges('document.viewer', 'document.deleter');
        setUpDocuments([createDocument('documentA')]);
        const wrapper = await mountDocumentCard();

        // ACT
        await chooseDocumentAction(wrapper, 0, 'delete');
        await confirmDeletion(wrapper);

        // ASSERT
        expect(getStoredDocuments()).toEqual([]);
        expect(getShownDocumentNumbers(wrapper)).toEqual([]);
    });

    /**
     * @requirement When a merchant changes the state of an order, they pick documents to attach to the mail.
     * The card only offers the choice there, not the actions that change documents.
     */
    it('lets a merchant pick documents to attach in the attach view', async () => {
        // ARRANGE
        setUpDocuments([createDocument('documentA')]);

        // ACT
        const wrapper = await mountDocumentCard({ attachView: true }, { routeName: 'sw.order.detail.general' });

        // ASSERT
        expect(getColumnLabels(wrapper)).toContain('sw-order.documentCard.labelAttach');
        expect(findDocumentAction(wrapper, 0, 'send').exists()).toBe(false);
        expect(wrapper.find(BUTTON_CREATE_DOCUMENT).exists()).toBe(false);
    });
});
