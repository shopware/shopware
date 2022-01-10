import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';

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
                        },
                    ],
                },
            },
        };
    },
    getters: {
        documentTypeConfigs: () => {
            return {
                invoice: {
                    documentDate: null,
                    documentComment: null,
                },
            };
        }
    },
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-save-modal-success'), {
        stubs: {
            'sw-label': true,
            'sw-icon': true,
        },
        provide: {
            orderDocumentApiService: {
                create: () => {
                    return Promise.resolve();
                },
                download: () => {
                    return Promise.resolve();
                },
            },
            feature: {
                isActive: () => true,
            },
        }
    });
}

describe('sw-bulk-edit-save-modal-success', () => {
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

    it('should be able to download order documents', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.orderDocumentApiService.download = jest.fn(() => {
            return Promise.resolve({ status: 200 });
        });

        await wrapper.find('.sw-bulk-edit-save-modal-success__download-order-documents').trigger('click');
        expect(wrapper.vm.createNotificationError).not.toBeCalled();

        wrapper.vm.createNotificationError.mockRestore();
        wrapper.vm.orderDocumentApiService.download.mockRestore();
    });
});
