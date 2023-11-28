import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-address-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([{ addresses: [] }]);
                        },
                        save: () => {
                            return Promise.resolve();
                        },
                    }),
                },
            },
        },
        props: {
            address: {},
            countries: [],
            order: {
                orderCustomer: {
                    customerId: 'customerId',
                },
            },
            versionContext: {},
        },
    });
}

describe('src/module/sw-order/component/sw-order-address-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get customer information on creation', async () => {
        wrapper.vm.getCustomerInfo = jest.fn();

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getCustomerInfo).toHaveBeenCalled();

        wrapper.vm.getCustomerInfo.mockRestore();
    });

    it('should not get customer information on creation', async () => {
        wrapper.vm.getCustomerInfo = jest.fn();

        await wrapper.setProps({
            order: {
                orderCustomer: {
                    customerId: null,
                },
            },
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getCustomerInfo).not.toHaveBeenCalled();

        wrapper.vm.getCustomerInfo.mockRestore();
    });
});
