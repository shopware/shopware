import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper({ featureActive = false } = {}) {
    return mount(await wrapTestComponent('sw-order-address-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-tabs': {
                    name: 'sw-tabs',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                    },
                    template:
                        '<div class="sw-tabs"><slot :active="defaultItem"></slot><slot name="content" :active="defaultItem"></slot></div>',
                },
                'sw-tabs-item': true,
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: [
                        'new-item-active',
                    ],
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                    template: '<div class="mt-tabs"></div>',
                },
                'mt-button': {
                    emits: [
                        'click',
                    ],
                    props: [
                        'block',
                        'disabled',
                        'size',
                        'variant',
                    ],
                    template: '<button class="mt-button" @click="$emit(\'click\')"><slot></slot></button>',
                },
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
                acl: {
                    can: (privilege) => {
                        return (global.activeAclRoles || []).includes(privilege);
                    },
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
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
        global.activeAclRoles = [];
        wrapper = await createWrapper();
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', () => {
        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-address-modal');
        expect(tabs.props('defaultItem')).toBe('edit');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        wrapper = await createWrapper({ featureActive: true });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-address-modal');
        expect(tabs.props('defaultItem')).toBe('edit');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-order.addressSelection.headlineTabEditAddress',
                name: 'edit',
            },
            {
                label: 'sw-order.addressSelection.headlineTabSelectAddress',
                name: 'addresses',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.findComponent('.sw-customer-address-form').exists()).toBe(true);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        wrapper = await createWrapper({ featureActive: true });

        await wrapper.setData({
            availableAddresses: [
                {
                    id: 'address-id',
                    company: 'Test company',
                    salutation: null,
                    street: 'Test street',
                    zipcode: '12345',
                    city: 'Test city',
                    country: {
                        name: 'Test country',
                    },
                },
            ],
            selectedAddressId: 'address-id',
        });

        wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'addresses');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('addresses');
        expect(wrapper.vm.selectedAddressId).toBe(0);
        expect(wrapper.findComponent('.sw-customer-address-form').exists()).toBe(false);
        expect(wrapper.find('[data-analytics-id="sw-order-address-modal.select-existing-address"]').exists()).toBe(true);
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
