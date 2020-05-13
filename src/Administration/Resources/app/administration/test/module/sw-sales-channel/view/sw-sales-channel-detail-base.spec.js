import { shallowMount } from '@vue/test-utils';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-base';

Shopware.State.registerModule('swSalesChannel', state);

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-base', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('googleShoppingService', () => {
            return {
                disconnectGoogle: (salesChannelId) => disconnectGoogleMock(salesChannelId)
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(
            Shopware.Component.build('sw-sales-channel-detail-base'),
            {
                store: Shopware.State._store,

                propsData: {
                    salesChannel: {},
                    productExport: {},
                    customFieldSets: [],
                    templateOptions: []
                },

                stubs: {
                    'router-link': true,
                    'sw-card': true,
                    'sw-field': true,
                    'sw-button': true,
                    'sw-icon': true,
                    'sw-container': true,
                    'sw-multi-tag-ip-select': true,
                    'sw-entity-single-select': true,
                    'sw-sales-channel-defaults-select': true
                },

                mocks: {
                    $tc: key => key
                },

                provide: {
                    salesChannelService: {
                        generateKey: () => generateKeyMock()
                    },
                    productExportService: {
                        generateKey: () => generateKeyMock()
                    },
                    repositoryFactory: {
                        create: () => repositoryFactoryMock()
                    }
                }
            }
        );
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should change state when onDisconnect() successfully', async () => {
        wrapper.setProps({ salesChannel: { id: 'd3d7a15521ad429b9b8959241580be0e' } });

        await wrapper.vm.$nextTick();

        await wrapper.vm.onDisconnect();

        expect(wrapper.vm.isDisconnectSuccessful).toEqual(true);
    });

    it('should show error notification when onDisconnect() failly', async () => {
        wrapper.setProps({ salesChannel: { id: '' } });
        wrapper.setMethods({ createNotificationError: jest.fn() });

        const spy = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.$nextTick();

        await wrapper.vm.onDisconnect();

        expect(spy).toHaveBeenCalled();

        spy.mockRestore();
    });

    it('should change state when onDisconnect() either successfully or failly', async () => {
        wrapper.setProps({ salesChannel: { id: 'd3d7a15521ad429b9b8959241580be0e' } });

        await wrapper.vm.$nextTick();

        await wrapper.vm.onDisconnect();

        expect(wrapper.vm.isDisconnectLoading).toEqual(false);
    });
});

function generateKeyMock() {
    return {
        create: () => Promise.resolve()
    };
}

function repositoryFactoryMock() {
    return {
        create: () => Promise.resolve()
    };
}

function disconnectGoogleMock(salesChannelId) {
    if (salesChannelId) {
        return Promise.resolve();
    }

    const exception = {
        response: {
            data: {
                errors: [
                    {
                        code: 'This is an error code',
                        detail: 'This is a detail error message'
                    }
                ]
            }
        }
    };

    return Promise.reject(exception);
}
