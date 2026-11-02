import {
    BUTTON_CREATE_DOCUMENT,
    createDocument,
    getColumnLabels,
    getDocumentRows,
    getDocumentSearches,
    getServices,
    grantPrivileges,
    mountDocumentCard,
    searchDocuments,
    setUpDocuments,
    unmountDocumentCards,
} from '../document-card.harness';

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
});
