import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */
const { Classes: { ShopwareError } } = Shopware;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-address-modal', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-customer-address-form': await wrapTestComponent('sw-customer-address-form'),
                'sw-customer-address-form-options': await wrapTestComponent('sw-customer-address-form-options'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                'sw-empty-state': true,
                'sw-address': await wrapTestComponent('sw-address'),
                'sw-icon': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
        props: {
            customer: {
                id: 'id',
                company: null,
            },
            address: {},
            addAddressModalTitle: '',
            editAddressModalTitle: '',
            cart: {},
        },
    });
}

describe('src/module/sw-order/component/sw-order-create-address-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should dispatch error with invalid company field', async () => {
        await wrapper.setData({
            addresses: [{ id: '12345', isNew: () => {} }, { id: '02', isNew: () => {} }],
        });

        const btn = wrapper.findAll('.sw-order-create-address-modal__edit-btn')[0];
        await btn.trigger('click');

        const swModalEditAddress = wrapper.findAll('.sw-modal')[0];

        expect(Shopware.State.get('error').api.customer_address).toBeUndefined();

        // submit form
        await swModalEditAddress.find('.sw-button--primary').trigger('click');

        expect(Shopware.State.get('error').api).toHaveProperty('customer_address.12345.company');
        expect(Shopware.State.get('error').api.customer_address['12345'].company).toBeInstanceOf(ShopwareError);
    });
});
