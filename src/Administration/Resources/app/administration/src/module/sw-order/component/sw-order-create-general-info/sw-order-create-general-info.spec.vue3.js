import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */

const cart = {
    price: {
        totalPrice: 20.01,
    },
};

const context = {
    paymentMethod: {
        translated: {
            distinguishableName: 'Cash on Delivery',
        },
    },
    shippingMethod: {
        translated: {
            name: 'Express',
        },
    },
    customer: {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@doe.dev',
    },
    currency: {
        totalRounding: {
            decimals: 2,
        },
        translated: {
            isoCode: 'EUR',
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-general-info', { sync: true }), {
        props: {
            context,
            cart,
            isLoading: false,
        },
        global: {
            provide: {
            },
            stubs: {
                'sw-order-state-select-v2': true,
                'sw-entity-tag-select': true,
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-create-general-info', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct summary header', async () => {
        const customerInfo = wrapper.find('.sw-order-create-general-info__summary-main-header');
        expect(customerInfo.exists()).toBeTruthy();
        expect(customerInfo.text()).toBe('John Doe (john@doe.dev)');

        const totalInfo = wrapper.find('.sw-order-create-general-info__summary-main-total');
        expect(totalInfo.exists()).toBeTruthy();
        expect(totalInfo.text()).toBe('€20.01');

        const methodInfo = wrapper.find('.sw-order-create-general-info__summary-sub');
        expect(methodInfo.exists()).toBeTruthy();
        expect(methodInfo.text()).toContain('Cash on Delivery');
        expect(methodInfo.text()).toContain('Express');
    });
});
