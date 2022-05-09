import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/component/sw-customer-default-addresses';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/_action/country/formatting-address',
    status: 200,
    response: {
        data: 'random-address',
    }
});

function createWrapper(defaultShippingAddress = {}, defaultBillingAddress = {}) {
    return shallowMount(Shopware.Component.build('sw-customer-default-addresses'), {
        propsData: {
            customer: {
                defaultShippingAddress,
                defaultBillingAddress,
            },
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-card-section': {
                template: '<div class="sw-card-section"><slot></slot></div>'
            },
            'sw-address': true,
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

describe('module/sw-customer/page/sw-customer-base-info', () => {
    let wrapper;

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render formatting address for billing address and shipping address', async () => {
        const shippingAddress = {
            id: 'address1',
            country: {
                useDefaultAddressFormat: false,
                advancedAddressFormatPlain: 'random-format',
            }
        };

        const billingAddress = {
            id: 'address1',
            country: {
                useDefaultAddressFormat: false,
                advancedAddressFormatPlain: 'random-format',
            }
        };

        wrapper = await createWrapper(shippingAddress, billingAddress);
        await wrapper.vm.$nextTick();

        const shippingSwAddress = wrapper.find('sw-address-stub[headline="sw-customer.detailBase.titleDefaultShippingAddress"]');
        const billingSwAddress = wrapper.find('sw-address-stub[headline="sw-customer.detailBase.titleDefaultBillingAddress"]');

        expect(shippingSwAddress.attributes()['formatting-address']).toBe('random-address');
        expect(billingSwAddress.attributes()['formatting-address']).toBe('random-address');
    });
});
