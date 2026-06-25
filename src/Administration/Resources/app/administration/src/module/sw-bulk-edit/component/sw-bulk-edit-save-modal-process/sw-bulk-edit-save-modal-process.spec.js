/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const selectedOrderIds = [
    'order-id-1',
    'order-id-2',
];
const documentIds = [
    'document-id-1',
    'document-id-2',
];

const deleteDocumentTypesFixtures = [
    {
        id: 'invoice-id',
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
        selected: true,
    },
    {
        id: 'credit-note-id',
        technicalName: 'credit_note',
        translated: { name: 'Credit note' },
        selected: true,
    },
];

const documentRepositoryMock = {
    searchIds: jest.fn(() =>
        Promise.resolve({
            data: documentIds,
            total: documentIds.length,
        }),
    ),
};

const repositoryFactoryMock = {
    create: (entity) => {
        if (entity === 'document') {
            return documentRepositoryMock;
        }
        return null;
    },
};

const syncServiceMock = {
    sync: jest.fn(() => Promise.resolve()),
};

async function createWrapper(selectedDocumentTypes = deleteDocumentTypesFixtures) {
    Shopware.Store.get('swBulkEdit').selectedIds = selectedOrderIds;
    Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
        type: 'delete',
        value: [...selectedDocumentTypes],
    });
    Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
        type: 'delete',
        isChanged: true,
    });

    return mount(
        await wrapTestComponent('sw-bulk-edit-save-modal-process', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-loader': true,
                    'sw-label': true,
                },
                provide: {
                    orderDocumentApiService: {
                        create: () => {
                            return Promise.resolve();
                        },
                        generate: (_documentType, payload) =>
                            Promise.resolve({
                                data: {
                                    data: payload.map(() => ({})),
                                    errors: {},
                                },
                            }),
                    },
                    syncService: syncServiceMock,
                    repositoryFactory: repositoryFactoryMock,
                },
            },
        },
    );
}

describe('sw-bulk-edit-save-modal-process', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should create documents when component created', async () => {
        wrapper.vm.createDocuments = jest.fn();

        await wrapper.vm.createdComponent();
        await flushPromises();

        expect(wrapper.vm.createDocuments).toHaveBeenCalled();
        wrapper.vm.createDocuments.mockRestore();
    });

    it('should not be able to create documents', async () => {
        wrapper.vm.createDocument = jest.fn();
        Shopware.Store.get('swBulkEdit').selectedIds = [];

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.createDocument).not.toHaveBeenCalled();
        wrapper.vm.createDocument.mockRestore();
    });

    it('should be able to create invoice document', async () => {
        wrapper.vm.createDocument = jest.fn().mockResolvedValue({ requested: 1, failed: 0 });
        Shopware.Store.get('swBulkEdit').selectedIds = ['orderId'];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: true,
        });

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.createDocument).toHaveBeenCalledWith(
            'invoice',
            expect.arrayContaining([
                expect.objectContaining({
                    config: expect.objectContaining({
                        documentComment: null,
                    }),
                    fileType: 'pdf',
                    orderId: 'orderId',
                    type: 'invoice',
                }),
            ]),
        );
        wrapper.vm.createDocument.mockRestore();
    });

    it('should be able to create storno document', async () => {
        wrapper.vm.createDocument = jest.fn().mockResolvedValue({ requested: 1, failed: 0 });
        Shopware.Store.get('swBulkEdit').selectedIds = ['orderId'];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'storno',
            isChanged: true,
        });

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.createDocument).toHaveBeenCalledWith(
            'storno',
            expect.arrayContaining([
                expect.objectContaining({
                    config: expect.objectContaining({
                        documentComment: null,
                    }),
                    fileType: 'pdf',
                    orderId: 'orderId',
                    type: 'storno',
                }),
            ]),
        );
        wrapper.vm.createDocument.mockRestore();
    });

    it('should be able to create delivery note document', async () => {
        wrapper.vm.createDocument = jest.fn().mockResolvedValue({ requested: 1, failed: 0 });
        Shopware.Store.get('swBulkEdit').selectedIds = ['orderId'];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'delivery_note',
            isChanged: true,
        });

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.createDocument).toHaveBeenCalledWith(
            'delivery_note',
            expect.arrayContaining([
                expect.objectContaining({
                    config: expect.objectContaining({
                        documentComment: null,
                    }),
                    fileType: 'pdf',
                    orderId: 'orderId',
                    type: 'delivery_note',
                }),
            ]),
        );
        wrapper.vm.createDocument.mockRestore();
    });

    it('should be able to create credit note document', async () => {
        wrapper.vm.createDocument = jest.fn().mockResolvedValue({ requested: 1, failed: 0 });
        Shopware.Store.get('swBulkEdit').selectedIds = ['orderId'];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'credit_note',
            isChanged: true,
        });

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.createDocument).toHaveBeenCalledWith(
            'credit_note',
            expect.arrayContaining([
                expect.objectContaining({
                    config: expect.objectContaining({
                        documentComment: null,
                    }),
                    fileType: 'pdf',
                    orderId: 'orderId',
                    type: 'credit_note',
                }),
            ]),
        );
        wrapper.vm.createDocument.mockRestore();
    });

    it('should create document successful', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn(() =>
            Promise.resolve({
                data: {
                    data: [{}],
                    errors: {},
                },
            }),
        );

        const result = await wrapper.vm.createDocument('invoice', [
            {
                config: {
                    documentDate: 'documentDate',
                    documentComment: 'documentComment',
                },
                fileType: 'pdf',
                orderId: 'orderId',
                type: 'invoice',
            },
        ]);

        expect(result).toEqual({
            requested: 1,
            failed: 0,
            skipped: 0,
            failedItems: [],
        });
        expect(wrapper.vm.document.invoice.isReached).toBe(100);
        wrapper.vm.orderDocumentApiService.generate.mockRestore();
    });

    it('should count document generation errors from response data', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn(() =>
            Promise.resolve({
                data: {
                    data: [{}],
                    errors: {
                        orderId2: [
                            {
                                code: 'DOCUMENT_GENERATION_FAILED',
                                detail: 'Document generation failed',
                            },
                        ],
                    },
                },
            }),
        );

        const result = await wrapper.vm.createDocument('invoice', [
            {
                config: {
                    documentDate: 'documentDate',
                    documentComment: 'documentComment',
                },
                fileType: 'pdf',
                orderId: 'orderId',
                type: 'invoice',
            },
            {
                config: {
                    documentDate: 'documentDate',
                    documentComment: 'documentComment',
                },
                fileType: 'pdf',
                orderId: 'orderId2',
                type: 'invoice',
            },
        ]);

        expect(result).toEqual({
            requested: 2,
            failed: 1,
            skipped: 0,
            failedItems: [
                {
                    orderId: 'orderId2',
                    documentType: 'invoice',
                    errorCode: 'DOCUMENT_GENERATION_FAILED',
                    detail: 'Document generation failed',
                },
            ],
        });
        expect(wrapper.vm.document.invoice.isReached).toBe(100);
        wrapper.vm.orderDocumentApiService.generate.mockRestore();
    });

    it('should count skipped documents from response data', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn(() =>
            Promise.resolve({
                data: {
                    data: [{}],
                    errors: {},
                },
            }),
        );

        const result = await wrapper.vm.createDocument('invoice', [
            {
                config: {},
                fileType: 'pdf',
                orderId: 'orderId',
                type: 'invoice',
            },
            {
                config: {},
                fileType: 'pdf',
                orderId: 'orderId2',
                type: 'invoice',
            },
        ]);

        expect(result).toEqual({
            requested: 2,
            failed: 0,
            skipped: 1,
            failedItems: [],
        });
        wrapper.vm.orderDocumentApiService.generate.mockRestore();
    });

    it('should reject malformed document generation responses', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn(() =>
            Promise.resolve({
                data: {
                    errors: {},
                },
            }),
        );

        await expect(
            wrapper.vm.createDocument('invoice', [
                {
                    config: {},
                    fileType: 'pdf',
                    orderId: 'orderId',
                    type: 'invoice',
                },
            ]),
        ).rejects.toThrow('Invalid document generation response');

        wrapper.vm.orderDocumentApiService.generate.mockRestore();
    });

    it('should break down the request to generate the document', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn((_documentType, payload) =>
            Promise.resolve({
                data: {
                    data: payload.map(() => ({})),
                    errors: {},
                },
            }),
        );

        Shopware.Store.get('swBulkEdit').selectedIds = [
            'orderId',
            'orderId2',
            'orderId3',
            'orderId4',
            'orderId5',
            'orderId6',
        ];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: true,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'storno',
            isChanged: false,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'delivery_note',
            isChanged: false,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'credit_note',
            isChanged: false,
        });

        await wrapper.vm.createDocuments();

        expect(wrapper.vm.orderDocumentApiService.generate).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.document.invoice.isReached).toBe(100);

        wrapper.vm.orderDocumentApiService.generate.mockRestore();
    });

    it('should store aggregated document generation results', async () => {
        wrapper.vm.createDocument = jest
            .fn()
            .mockResolvedValueOnce({
                requested: 2,
                failed: 1,
                skipped: 0,
                failedItems: [
                    {
                        orderId: 'orderId',
                        documentType: 'invoice',
                    },
                ],
            })
            .mockResolvedValueOnce({
                requested: 2,
                failed: 0,
                skipped: 1,
                failedItems: [],
            });

        Shopware.Store.get('swBulkEdit').selectedIds = [
            'orderId',
            'orderId2',
        ];
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: true,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'delivery_note',
            isChanged: true,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'storno',
            isChanged: false,
        });
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'credit_note',
            isChanged: false,
        });

        await wrapper.vm.createDocuments();

        expect(Shopware.Store.get('swBulkEdit').documentGenerationResult).toEqual({
            requested: 4,
            failed: 1,
            skipped: 1,
            failedItems: [
                {
                    orderId: 'orderId',
                    documentType: 'invoice',
                },
            ],
        });
        wrapper.vm.createDocument.mockRestore();
    });

    it('should compute selectedDocumentTypes correctly', async () => {
        expect(wrapper.vm.selectedDocumentTypes).toEqual([]);

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: true,
        });

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'download',
            isChanged: true,
        });

        Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
            type: 'invoice',
            value: {
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            },
        });

        Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
            type: 'download',
            value: [],
        });

        expect(wrapper.vm.selectedDocumentTypes).toStrictEqual([]);

        Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
            type: 'download',
            value: [
                {
                    id: '1',
                    name: 'Invoice',
                    selected: true,
                    technicalName: 'invoice',
                    translated: {
                        name: 'Invoice',
                    },
                },
            ],
        });

        expect(wrapper.vm.selectedDocumentTypes).toStrictEqual([
            {
                id: '1',
                name: 'Invoice',
                selected: true,
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
        ]);
    });

    describe('delete documents', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        it('should not call searchIds for document when no document type is selected', async () => {
            await createWrapper([]);
            await flushPromises();

            expect(documentRepositoryMock.searchIds).not.toHaveBeenCalled();
        });

        it('should call searchIds for document with the selected order ids and selected document types', async () => {
            await createWrapper();
            await flushPromises();

            expect(documentRepositoryMock.searchIds).toHaveBeenCalledTimes(1);

            const criteria = documentRepositoryMock.searchIds.mock.calls[0][0];
            const orderIdFilter = criteria.filters.find((filter) => filter.field === 'orderId');
            const documentTypeFilter = criteria.filters.find((filter) => filter.field === 'documentType.technicalName');

            expect(orderIdFilter).toBeDefined();
            expect(orderIdFilter.value).toContain(selectedOrderIds[0]);
            expect(orderIdFilter.value).toContain(selectedOrderIds[1]);
            expect(documentTypeFilter).toBeDefined();
            expect(documentTypeFilter.value).toContain(deleteDocumentTypesFixtures[0].technicalName);
            expect(documentTypeFilter.value).toContain(deleteDocumentTypesFixtures[1].technicalName);
        });

        it('should not call sync when when search ids for documents returns no document ids', async () => {
            documentRepositoryMock.searchIds.mockResolvedValueOnce({ data: [], total: 0 });
            await createWrapper();
            await flushPromises();

            expect(syncServiceMock.sync).not.toHaveBeenCalled();
        });

        it('should pass all found document ids to sync', async () => {
            await createWrapper();
            await flushPromises();

            const syncCall = syncServiceMock.sync;
            expect(syncCall).toHaveBeenCalledTimes(1);

            const syncPayload = syncCall.mock.calls[0][0];
            const documentIdsInPayload = syncPayload['delete-order_document'].payload.map((item) => item.id);

            expect(documentIdsInPayload).toContain(documentIds[0]);
            expect(documentIdsInPayload).toContain(documentIds[1]);
        });

        it('should show notification message when deleting documents that have depending documents', async () => {
            const errorMessage = 'cannot delete: credit_note 1000 (id1), credit_note 1001 (id2)';
            const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

            syncServiceMock.sync.mockRejectedValueOnce({
                response: {
                    data: {
                        errors: [
                            {
                                status: '422',
                                code: 'ERROR_CODE',
                                detail: errorMessage,
                            },
                        ],
                    },
                },
            });

            wrapper = await createWrapper();
            await flushPromises();

            expect(notificationSpy).toHaveBeenCalledTimes(1);
            expect(notificationSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    variant: 'error',
                    message: errorMessage,
                }),
            );
        });

        it('should truncate notification message when deleting documents that have more than 10 depending documents', async () => {
            const dependentDocuments = Array.from({ length: 12 }, (_, index) => `credit_note ${1000 + index} (id${index})`);
            const errorMessage = 'cannot delete: ' + dependentDocuments.join(', ');

            const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

            syncServiceMock.sync.mockRejectedValueOnce({
                response: {
                    data: {
                        errors: [
                            {
                                status: '422',
                                code: 'ERROR_CODE',
                                detail: errorMessage,
                            },
                        ],
                    },
                },
            });

            wrapper = await createWrapper();
            await flushPromises();

            const notificationMessage = notificationSpy.mock.calls[0][0].message;
            expect(notificationMessage).toContain('cannot delete: credit_note 1000 (id0), credit_note 1001 (id1), ');
            expect(notificationMessage).toContain('... (and 2 more)');
            expect(notificationMessage).not.toContain('), credit_note 1010 (id10), credit_note 1011 (id11)');
        });
    });
});
