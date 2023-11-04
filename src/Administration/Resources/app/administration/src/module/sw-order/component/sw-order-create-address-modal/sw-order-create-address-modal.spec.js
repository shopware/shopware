import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderCreateAddressModal from 'src/module/sw-order/component/sw-order-create-address-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-card';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-create-address-modal', swOrderCreateAddressModal);

const { Classes: { ShopwareError } } = Shopware;

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-create-address-modal'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-customer-address-form': true,
            'sw-customer-address-form-options': true,
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-ignore-class': true,
            'sw-extension-component-section': true,
            'sw-card-filter': true,
            'sw-empty-state': true,
            'sw-address': true,
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
        propsData: {
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

    afterEach(() => {
        wrapper.destroy();
    });

    it('should dispatch error with invalid company field', async () => {
        await wrapper.setData({
            addresses: [{ id: '12345', isNew: () => {} }, { id: '02', isNew: () => {} }],
        });

        const btn = wrapper.findAll('.sw-order-create-address-modal__edit-btn').at(0);
        await btn.trigger('click');

        const swModalEditAddress = wrapper.findAll('.sw-modal').at(1);

        expect(Shopware.State.get('error').api.customer_address).toBeUndefined();

        // submit form
        await swModalEditAddress.find('.sw-button--primary').trigger('click');

        expect(Shopware.State.get('error').api).toHaveProperty('customer_address.12345.company');
        expect(Shopware.State.get('error').api.customer_address['12345'].company).toBeInstanceOf(ShopwareError);
    });
});
