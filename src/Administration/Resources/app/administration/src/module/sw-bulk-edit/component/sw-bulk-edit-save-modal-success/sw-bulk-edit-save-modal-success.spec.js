/**
 * @sw-package fundamentals@framework
 */
import { flushPromises, mount } from '@vue/test-utils';

async function createWrapper(
    repositoryMocks = {
        documentSearch: () => Promise.resolve([]),
        orderSearch: () => Promise.resolve([]),
    },
) {
    const documentSearch = repositoryMocks.documentSearch ?? repositoryMocks.search ?? (() => Promise.resolve([]));
    const orderSearch = repositoryMocks.orderSearch ?? repositoryMocks.search ?? (() => Promise.resolve([]));

    return mount(
        await wrapTestComponent('sw-bulk-edit-save-modal-success', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-label': true,
                    'mt-banner': {
                        props: [
                            'title',
                            'variant',
                        ],
                        template: '<div class="mt-banner">{{ title }}<slot /></div>',
                    },
                    'sw-bulk-edit-document-generation-failed-list': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                search: entity === 'order' ? orderSearch : documentSearch,
                            };
                        },
                    },
                    orderDocumentApiService: {
                        create: () => {
                            return Promise.resolve();
                        },
                        download: () => {
                            return Promise.resolve();
                        },
                    },
                },
            },
        },
    );
}

describe('sw-bulk-edit-save-modal-success', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('swBulkEdit').selectedIds = ['orderId'];
    });

    beforeEach(async () => {
        const bulkEditStore = Shopware.Store.get('swBulkEdit');

        bulkEditStore.resetDocumentGenerationResult();
        bulkEditStore.setOrderDocumentsIsChanged({
            type: 'download',
            isChanged: false,
        });
        bulkEditStore.setOrderDocumentsValue({
            type: 'download',
            value: [],
        });

        wrapper = await createWrapper();
    });

    it('should contain a correct selectedIds computed property', async () => {
        expect(wrapper.vm.selectedIds).toEqual(expect.arrayContaining(['orderId']));
    });

    it('should get latest documents when component created', async () => {
        wrapper.vm.getLatestDocuments = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getLatestDocuments).toHaveBeenCalled();
        wrapper.vm.getLatestDocuments.mockRestore();
    });

    it('should not be able to get latest documents', async () => {
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'download',
            isChanged: false,
        });

        await wrapper.vm.getLatestDocuments();

        expect(wrapper.vm.latestDocuments).toEqual({});
    });

    it('should show document generation warning when documents failed', async () => {
        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(5, 2);
        await flushPromises();

        const warning = wrapper.find('.sw-bulk-edit-save-modal-success__warning-document-generation');

        expect(wrapper.vm.hasDocumentGenerationErrors).toBe(true);
        expect(warning.exists()).toBe(true);
        expect(warning.text()).toBe('sw-bulk-edit.modal.success.documentGenerationFailed');
    });

    it('should show skipped document generation info', async () => {
        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(5, 0, 2);
        await flushPromises();

        const info = wrapper.find('.sw-bulk-edit-save-modal-success__info-document-generation');

        expect(wrapper.vm.hasSkippedDocuments).toBe(true);
        expect(info.exists()).toBe(true);
        expect(info.text()).toBe('sw-bulk-edit.modal.success.documentGenerationSkipped');
    });

    it('should be able to get latest documents', async () => {
        wrapper.unmount();

        wrapper = await createWrapper({
            search: () => {
                return Promise.resolve([
                    {
                        id: '1',
                        documentTypeId: '1',
                        orderId: '1',
                        createdAt: '2020-01-01',
                        deepLinkCode: '123',
                        fileType: 'pdf',
                        orderVersionId: '1',
                    },
                    {
                        id: '2',
                        documentTypeId: '1',
                        orderId: '1',
                        createdAt: '2020-01-01',
                        deepLinkCode: '123',
                        fileType: 'pdf',
                        orderVersionId: '1',
                    },
                    {
                        id: '3',
                        documentTypeId: '2',
                        orderId: '1',
                        createdAt: '2020-01-01',
                        deepLinkCode: '123',
                        fileType: 'pdf',
                        orderVersionId: '1',
                    },
                ]);
            },
        });

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'download',
            isChanged: true,
        });
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
                {
                    id: '2',
                    name: 'Credit note',
                    selected: true,
                    technicalName: 'credit_note',
                    translated: {
                        name: 'Credit note',
                    },
                },
            ],
        });

        await wrapper.vm.getLatestDocuments();

        expect(wrapper.vm.latestDocuments).toEqual(
            expect.objectContaining({
                invoice: expect.arrayContaining(['1']),
                credit_note: expect.arrayContaining(['3']),
            }),
        );
    });

    it('should load order numbers for failed document rows', async () => {
        wrapper.unmount();

        const orderSearch = jest.fn(() =>
            Promise.resolve([
                {
                    id: 'orderId',
                    orderNumber: '10089',
                },
            ]),
        );

        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(1, 1, 0, [
            {
                orderId: 'orderId',
                documentType: 'invoice',
            },
        ]);

        wrapper = await createWrapper({
            orderSearch,
        });
        await flushPromises();

        expect(orderSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.orderNumbers).toEqual({
            orderId: '10089',
        });
    });

    it('should group failed document rows by order id', async () => {
        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(4, 3, 0, [
            {
                orderId: 'orderId',
                documentType: 'delivery_note',
            },
            {
                orderId: 'orderId',
                documentType: 'invoice',
            },
            {
                orderId: 'orderId',
                documentType: 'invoice',
            },
            {
                orderId: 'orderId2',
                documentType: 'credit_note',
            },
        ]);

        await wrapper.setData({
            orderNumbers: {
                orderId: '10089',
                orderId2: '10090',
            },
        });

        expect(wrapper.vm.failedDocumentRows).toEqual([
            {
                id: 'orderId',
                orderId: 'orderId',
                orderNumber: '10089',
                documentTypes: [
                    'invoice',
                    'delivery_note',
                ],
                documentTypesLabel: [
                    'sw-bulk-edit.modal.success.failedDocuments.documentTypes.invoice',
                    'sw-bulk-edit.modal.success.failedDocuments.documentTypes.deliveryNote',
                ].join(', '),
            },
            {
                id: 'orderId2',
                orderId: 'orderId2',
                orderNumber: '10090',
                documentTypes: [
                    'credit_note',
                ],
                documentTypesLabel: 'sw-bulk-edit.modal.success.failedDocuments.documentTypes.creditNote',
            },
        ]);
    });

    it('should create document generation result file content and file name', async () => {
        jest.useFakeTimers().setSystemTime(new Date(2026, 5, 8, 10, 58));

        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(1, 1, 0, [
            {
                orderId: 'orderId',
                documentType: 'invoice',
            },
        ]);

        await wrapper.setData({
            orderNumbers: {
                orderId: '10089',
            },
        });

        expect(wrapper.vm.getDocumentGenerationResultFileContent()).toBe(
            [
                'sw-bulk-edit.modal.success.failedDocuments.downloadHeadline',
                '',
                '10089 - sw-bulk-edit.modal.success.failedDocuments.documentTypes.invoice',
            ].join('\n'),
        );
        expect(wrapper.vm.getDocumentGenerationResultFileName()).toBe(
            'sw-bulk-edit.modal.success.failedDocuments.downloadFileName-2026-06-08-10-58.txt',
        );

        jest.useRealTimers();
    });

    it('should add download result button when failed document rows exist', async () => {
        Shopware.Store.get('swBulkEdit').setDocumentGenerationResult(1, 1, 0, [
            {
                orderId: 'orderId',
                documentType: 'invoice',
            },
        ]);

        await flushPromises();
        wrapper.vm.updateButtons();

        const emittedButtons = wrapper.emitted('buttons-update').at(-1)[0];

        expect(emittedButtons).toEqual([
            expect.objectContaining({
                key: 'download-result',
                variant: 'secondary',
            }),
            expect.objectContaining({
                key: 'close',
                label: 'global.default.close',
                variant: 'primary',
            }),
        ]);
    });

    it('should be able to download documents', async () => {
        window.URL.createObjectURL = jest.fn();

        wrapper.vm.orderDocumentApiService.download = jest.fn(() =>
            Promise.resolve({
                data: null,
            }),
        );

        await wrapper.setData({
            latestDocuments: {
                invoice: {
                    foo: 'bar',
                },
            },
        });
        await wrapper.vm.downloadDocument('invoice');

        expect(wrapper.vm.orderDocumentApiService.download).toHaveBeenCalled();
        expect(wrapper.vm.document.invoice.isDownloading).toBe(false);

        wrapper.vm.orderDocumentApiService.download = jest.fn(() =>
            Promise.resolve({
                headers: {
                    'content-disposition': 'filename=example.pdf',
                },
                data: 'http://downloadlink',
            }),
        );

        await wrapper.vm.downloadDocument('invoice');

        expect(wrapper.vm.orderDocumentApiService.download).toHaveBeenCalled();
        expect(wrapper.vm.document.invoice.isDownloading).toBe(false);

        wrapper.vm.orderDocumentApiService.download.mockRestore();
    });

    it('should not be able to download documents', async () => {
        wrapper.vm.orderDocumentApiService.download = jest.fn(() => Promise.resolve());

        await wrapper.setData({
            latestDocuments: {},
        });
        await wrapper.vm.downloadDocument('invoice');

        expect(wrapper.vm.orderDocumentApiService.download).not.toHaveBeenCalled();
        wrapper.vm.orderDocumentApiService.download.mockRestore();
    });

    it('should call download documents with error', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.orderDocumentApiService.download = jest
            .fn()
            .mockImplementation(() => Promise.reject(new Error('error occured')));

        await wrapper.setData({
            latestDocuments: {
                invoice: {
                    foo: 'bar',
                },
            },
        });

        await wrapper.vm.downloadDocument('invoice');

        expect(wrapper.vm.orderDocumentApiService.download).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
        expect(wrapper.vm.document.invoice.isDownloading).toBe(false);
        wrapper.vm.orderDocumentApiService.download.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should compute selectedDocumentTypes correctly', async () => {
        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'download',
            isChanged: true,
        });

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
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
});
