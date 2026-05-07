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
                'sw-tabs': {
                    props: ['defaultItem'],
                    template:
                        '<div class="sw-tabs"><slot :active="defaultItem"></slot><slot name="content" :active="defaultItem"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-customer-address-form': {
                    name: 'sw-customer-address-form',
                    props: ['disabled'],
                    template: '<div class="sw-customer-address-form"></div>',
                },
                'sw-custom-field-set-renderer': {
                    name: 'sw-custom-field-set-renderer',
                    props: ['disabled'],
                    template: '<div class="sw-custom-field-set-renderer"></div>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: {
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            default: null,
                        },
                        defaultItem: {
                            type: String,
                            default: '',
                        },
                    },
                    emits: ['new-item-active'],
                    template: '<div class="mt-tabs-stub"></div>',
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
        global.activeFeatureFlags = [];
        global.activeAclRoles = [];
        wrapper = await createWrapper();
    });

    it('should render mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper();

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-order-address-modal');
        expect(tabs.props('defaultItem')).toBe('edit');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({ label: 'sw-order.addressSelection.headlineTabEditAddress', name: 'edit' }),
            expect.objectContaining({ label: 'sw-order.addressSelection.headlineTabSelectAddress', name: 'addresses' }),
        ]);

        await wrapper.setData({ selectedAddressId: 'address-id' });
        await tabs.vm.$emit('new-item-active', 'addresses');

        expect(wrapper.vm.activeTab).toBe('addresses');
        expect(wrapper.vm.selectedAddressId).toBe(0);
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

    it('should disable address form fields without order edit permissions', async () => {
        expect(wrapper.getComponent('.sw-customer-address-form').props('disabled')).toBe(true);
        expect(wrapper.getComponent('.sw-custom-field-set-renderer').props('disabled')).toBe(true);
    });

    it('should enable address form fields with order edit permissions', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-customer-address-form').props('disabled')).toBe(false);
        expect(wrapper.getComponent('.sw-custom-field-set-renderer').props('disabled')).toBe(false);
    });
});
