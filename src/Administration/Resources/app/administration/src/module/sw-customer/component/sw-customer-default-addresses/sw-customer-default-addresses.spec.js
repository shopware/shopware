import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

async function createWrapper(defaultShippingAddress = {}, defaultBillingAddress = {}) {
    return mount(await wrapTestComponent('sw-customer-default-addresses', { sync: true }), {
        props: {
            customer: {
                defaultShippingAddress,
                defaultBillingAddress,
            },
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-address': await wrapTestComponent('sw-address', { sync: true }),
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
