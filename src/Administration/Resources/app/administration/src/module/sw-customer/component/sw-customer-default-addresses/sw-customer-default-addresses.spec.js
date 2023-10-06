import { shallowMount } from '@vue/test-utils';
import SwCustomerDefaultAddresses from 'src/module/sw-customer/component/sw-customer-default-addresses/index';
import 'src/app/component/base/sw-address';

/**
 * @package checkout
 */

Shopware.Component.register('sw-customer-default-addresses', SwCustomerDefaultAddresses);

async function createWrapper(defaultShippingAddress = {}, defaultBillingAddress = {}) {
    return shallowMount(await Shopware.Component.build('sw-customer-default-addresses'), {
        propsData: {
            customer: {
                defaultShippingAddress,
                defaultBillingAddress,
            },
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>',
            },
            'sw-card-section': {
                template: '<div class="sw-card-section"><slot></slot></div>',
            },
            'sw-address': await Shopware.Component.build('sw-address'),
        },
        provide: {
            customSnippetApiService: {
                render() {
                    return Promise.resolve({
                        rendered: 'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>',
                    });
                },
            },
        },
    });
}

describe('module/sw-customer-default-addresses', () => {
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render formatting address for billing address and shipping address', async () => {
        const shippingAddress = {
            id: 'address1',
            country: {
                addressFormat: [[{ type: 'snippet', value: 'address/company' }]],
            },
        };

        const billingAddress = {
            id: 'address1',
            country: {
                addressFormat: [[{ type: 'snippet', value: 'address/company' }]],
            },
        };

        wrapper = await createWrapper(shippingAddress, billingAddress);

        await wrapper.vm.$nextTick();

        const swAddress = wrapper.findAll('.sw-address');

        const shippingSwAddress = swAddress.at(0).find('.sw-address__formatting');
        const billingSwAddress = swAddress.at(1).find('.sw-address__formatting');

        expect(shippingSwAddress.text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
        expect(billingSwAddress.text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
    });
});
