import { shallowMount } from '@vue/test-utils';
import swOrderCreateGeneralInfo from 'src/module/sw-order/component/sw-order-create-general-info';

/**
 * @package checkout
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

Shopware.Component.register('sw-order-create-general-info', swOrderCreateGeneralInfo);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-create-general-info'), {
        propsData: {
            context,
            cart,
            isLoading: false,
        },
        provide: {
        },
        stubs: {
            'sw-order-state-select-v2': true,
            'sw-entity-tag-select': true,
        },
    });
}

describe('src/module/sw-order/component/sw-order-create-general-info', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.destroy();
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
