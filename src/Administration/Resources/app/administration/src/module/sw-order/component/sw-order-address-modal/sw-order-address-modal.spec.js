import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderAddressModal from 'src/module/sw-order/component/sw-order-address-modal';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-address-modal', swOrderAddressModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-address-modal'), {
        localVue,
        stubs: {
            'sw-modal': true,
            'sw-tabs': true,
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
        propsData: {
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

    afterEach(() => {
        wrapper.destroy();
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
