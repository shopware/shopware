import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';

const swBulkEditState = {
    namespaced: true,
    state() {
        return {
            orderDocuments: {
                invoice: {
                    isChanged: true,
                    value: {
                        documentDate: null,
                        documentComment: null,
                    },
                },
                download: {
                    isChanged: true,
                    value: [
                        {
                            technicalName: 'invoice',
                            selected: true,
                            translated: {
                                name: 'invoice'
                            }
                        },
                    ],
                },
            },
        };
    },
    getters: {
        documentTypeConfigs: () => {
            return [
                {
                    fileType: 'pdf',
                    type: 'invoice',
                    config: {
                        documentDate: null,
                        documentComment: null,
                    },
                },
            ];
        }
    },
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-save-modal-process'), {
        stubs: {
            'sw-alert': true,
            'sw-icon': true,
            'sw-label': true,
        },
        provide: {
            orderDocumentApiService: {
                create: () => {
                    return Promise.resolve();
                },
            },
        }
    });
}

describe('sw-bulk-edit-save-modal-process', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    });

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should create documents when component created', async () => {
        wrapper.vm.createDocuments = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.createDocuments).toHaveBeenCalled();
        wrapper.vm.createDocuments.mockRestore();
    });

    it('should generate documents successful', async () => {
        wrapper.vm.orderDocumentApiService.generate = jest.fn(() => Promise.resolve());

        await wrapper.vm.createDocument('invoice', [
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

        expect(wrapper.vm.document.invoice.isReached).toBe(100);
        wrapper.vm.orderDocumentApiService.create.mockRestore();
    });

    it('should create documents successful', async () => {
        wrapper.vm.orderDocumentApiService.create = jest.fn(() => Promise.resolve());

        await wrapper.vm.createDocument('invoice', [
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

        expect(wrapper.vm.document.invoice.isReached).toBe(100);
        wrapper.vm.orderDocumentApiService.create.mockRestore();
    });

    it('should compute selectedDocumentTypes correctly', () => {
        expect(wrapper.vm.selectedDocumentTypes).toEqual([]);

        Shopware.State.commit('swBulkEdit/setOrderDocumentsIsChanged', {
            type: 'invoice',
            isChanged: true,
        });

        Shopware.State.commit('swBulkEdit/setOrderDocumentsIsChanged', {
            type: 'download',
            isChanged: true,
        });

        Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
            type: 'invoice',
            value: {
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            },
        });

        Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
            type: 'download',
            value: [],
        });

        expect(wrapper.vm.selectedDocumentTypes).toStrictEqual([]);

        Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
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
