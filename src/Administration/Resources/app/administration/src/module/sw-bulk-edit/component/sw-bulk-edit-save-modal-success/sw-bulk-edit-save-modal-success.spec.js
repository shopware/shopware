/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(
    repositoryMocks = {
        search: () => Promise.resolve([]),
    },
) {
    return mount(
        await wrapTestComponent('sw-bulk-edit-save-modal-success', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-label': true,
                    'sw-icon': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: repositoryMocks.search,
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
        Shopware.Store.get('shopwareApps').selectedIds = ['orderId'];
    });

    beforeEach(async () => {
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
