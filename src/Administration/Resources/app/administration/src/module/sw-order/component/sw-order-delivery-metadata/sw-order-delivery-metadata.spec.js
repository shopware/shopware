import { shallowMount } from '@vue/test-utils';
import SwOrderDeliveryMetadata from 'src/module/sw-order/component/sw-order-delivery-metadata/index';
import 'src/app/component/base/sw-address';

/**
 * @package checkout
 */

Shopware.Component.register('sw-order-delivery-metadata', SwOrderDeliveryMetadata);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-delivery-metadata'), {
        propsData: {
            delivery: {
                shippingMethod: {
                    translated: {
                        name: '',
                    },
                },
                shippingOrderAddress: {
                    country: {
                        addressFormat: [[{ type: 'snippet', value: 'address/company' }]],
                    },
                },
            },
            order: {
                currency: {
                    shortName: 'EUR',
                    symbol: '€',
                },
            },
        },
        stubs: {
            'sw-container': true,
            'sw-address': await Shopware.Component.build('sw-address'),
            'sw-card': true,
            'sw-description-list': true,
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

describe('module/sw-order/component/sw-order-delivery-metadata', () => {
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render formatting address for delivery address', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-address .sw-address__formatting').text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
    });
});
