import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-address-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-customer-address-form': true,
                'sw-custom-field-set-renderer': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-loader': true,
                'mt-button': true,
                'mt-tabs': {
                    props: [
                        'items',
                        'defaultItem',
                        'positionIdentifier',
                    ],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                },
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
        global.activeFeatureFlags = [''];
        wrapper = await createWrapper();
    });

    it('should render legacy tabs when the major feature flag is inactive', async () => {
        await flushPromises();

        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper();
        await flushPromises();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-order.addressSelection.headlineTabEditAddress',
                name: 'edit',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.addressSelection.headlineTabSelectAddress',
                name: 'addresses',
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('edit');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-order-address-modal');
        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(false);
    });

    it('should switch active content in the meteor tabs branch', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ selectedAddressId: 'selected-address-id' });
        wrapper.getComponent('mt-tabs-stub').props('items')[1].onClick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('addresses');
        expect(wrapper.vm.selectedAddressId).toBe(0);
        expect(wrapper.find('sw-customer-address-form-stub').exists()).toBe(false);
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
