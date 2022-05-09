import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-delivery-metadata';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/_action/country/formatting-address',
    status: 200,
    response: {
        data: 'random-address',
    }
});

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-delivery-metadata'), {
        propsData: {
            delivery: {
                shippingMethod: {
                    translated: {
                        name: ''
                    }
                },
                shippingOrderAddress: {
                    country: {
                        useDefaultAddressFormat: false,
                        advancedAddressFormatPlain: 'random-format',
                    },
                },
            },
            order: {
                currency: {
                    shortName: 'EUR',
                    symbol: '€'
                },
            },
        },
        stubs: {
            'sw-container': true,
            'sw-address': true,
            'sw-card': true,
            'sw-description-list': true,
        },
        provide: {
            countryAddressService: {
                formattingAddress() {
                    return Promise.resolve('random-address');
                }
            }
        }
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

        const billingAddress = wrapper.find('sw-address-stub[headline="sw-order.detailBase.headlineDeliveryAddress"]');
        expect(billingAddress.attributes()['formatting-address']).toBe('random-address');
    });
});
