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
        global.activeAclRoles = [];
        wrapper = await createWrapper();
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
